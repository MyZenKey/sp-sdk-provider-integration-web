# Copyright 2020 ZenKey, LLC.
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
import logging
import os
from urllib.parse import urlparse
import json
from flask import Flask, redirect, render_template, request, session
from flask.helpers import url_for
from werkzeug.exceptions import Unauthorized
from oic.oauth2.message import TokenErrorResponse
from oic.oic import Client
from oic.oic.message import (ProviderConfigurationResponse,
                             RegistrationResponse)
from oic.utils.authn.client import CLIENT_AUTHN_METHOD
from oic.utils.http_util import Redirect
from zenkey_oidc_service import ZenKeyOIDCService, ZenKeySchema
from authorization_flow_handler import AuthorizationFlowHandler
from utilities import get_current_user
from session_service import SessionService

logging.basicConfig(level=logging.DEBUG)

# get variables from environment
CLIENT_ID = os.getenv('CLIENT_ID')
CLIENT_SECRET = os.getenv('CLIENT_SECRET')
SECRET_KEY_BASE = os.getenv('SECRET_KEY_BASE')
BASE_URL = os.getenv('BASE_URL')

# configure the app based on the base URL
PARSED_URL = urlparse(BASE_URL)
HOSTNAME = PARSED_URL.hostname
# no session cookies on localhost
IS_LOCAL = HOSTNAME == 'localhost'
SESSION_COOKIE_DOMAIN = None if IS_LOCAL else HOSTNAME

SERVER_NAME = PARSED_URL.hostname
if PARSED_URL.port:
    SERVER_NAME = '%s:%s' % (SERVER_NAME, PARSED_URL.port)

# set up Flask
application = Flask(__name__) # pylint: disable=invalid-name
application.config.update({'SERVER_NAME': SERVER_NAME,
                           'SESSION_COOKIE_DOMAIN': SESSION_COOKIE_DOMAIN,
                           'SECRET_KEY': SECRET_KEY_BASE})

SCOPE = ['openid', 'name', 'email', 'phone', 'postal_code']
PROVIDER_NAME = 'zenkey'

session_service = SessionService(session) # pylint: disable=invalid-name

@application.errorhandler(500)
def internal_server_error(error):
    """Show error details"""
    return ("Error: \n" + repr(error)), 500

@application.route('/')
def index():
    """homepage route"""
    current_user = get_current_user(session)
    message = request.args.get('message')
    return render_template('home.html', current_user=current_user, message=message)

@application.route('/auth')
def carrier_discovery():
    """
    Carrier Discovery:
    To learn the mccmnc, we send the user to the ZenKey discovery endpoint.
    This endpoint will redirect the user back to our app, giving us
    the mccmnc that identifes the user's carrier.
    """
    redirect_uri = url_for('auth_callback',
                           _external=True,
                           _scheme=('http' if IS_LOCAL else 'https'))

    zenkey_oidc_service = ZenKeyOIDCService(CLIENT_ID, CLIENT_SECRET, redirect_uri, session_service)
    carrier_discovery_url = zenkey_oidc_service.carrier_discovery_redirect()
    return redirect(carrier_discovery_url)


@application.route('/auth/cb')
def auth_callback():
    """
    Auth callback: authenticate the user and get an access token
    """
    login_hint_token = request.args.get('login_hint_token')
    error = request.args.get('error')
    state = request.args.get('state')
    code = request.args.get('code')
    current_user = get_current_user(session)
    redirect_uri = url_for('auth_callback', _external=True,
                           _scheme=('http' if IS_LOCAL else 'https'))

    zenkey_oidc_service = ZenKeyOIDCService(CLIENT_ID, CLIENT_SECRET, redirect_uri, session_service)
    auth_flow_handler = AuthorizationFlowHandler(session)

    # handle errors returned from ZenKey
    if error is not None:
        # if an error happens, delete the auth information saved in the session
        auth_flow_handler.delete_authorization_details()
        session_service.clear()
        raise Exception(error)

    # check if the user is already logged in
    if current_user is not None and not auth_flow_handler.authorization_in_progress():
        return redirect('/')

    # use a cached MCCMNC if needed
    mccmnc = request.args.get('mccmnc', session_service.get_mccmnc())

    # If we have no mccmnc, begin the carrier discovery process
    if mccmnc is None:
        return redirect('/auth')

    if state is None:
        # if an error happens, delete the auth information saved in the session
        auth_flow_handler.delete_authorization_details()
        raise Exception('missing state')

    # build our OpenID client
    openid_client = Client(client_authn_method=CLIENT_AUTHN_METHOD, client_id=CLIENT_ID)
    # save the client information to the OIDC client
    client_registration_info = RegistrationResponse(**{
        "client_id": CLIENT_ID,
        "client_secret": CLIENT_SECRET})
    openid_client.store_registration_info(client_registration_info)
    # discover the carrier OIDC endpoint configuration
    oidc_configuration = zenkey_oidc_service.discover_oidc_provider_metadata(mccmnc)
    # save the provider config to the OIDC client after we've discovered it
    provider_configuration = ProviderConfigurationResponse(**oidc_configuration)
    openid_client.handle_provider_config(
        provider_configuration,
        provider_configuration['issuer'],
        True,
        True)

    if code is None:
        # Request an auth code
        # The carrier discovery endpoint has redirected back to our app with the mccmnc.
        # Now we can start the authorize flow by requesting an auth code.
        # Send the user to the ZenKey authorization endpoint. After authorization, this endpoint
        # will redirect back to our app with an auth code.


        if auth_flow_handler.authorization_in_progress():
            # authorization is in progress
            auth_kwargs = {
                # only openid scope is needed for this auth request
                'scope':  ['openid'],
                # add the context and acr value to the auth request
                'context': auth_flow_handler.get_authorization_details().get('context'),
                'acr_values': 'a3'
            }
        else:
            # no authorization in progress: do a standard login authorization
            auth_kwargs = {
                'scope': SCOPE
            }

        authorization_url = zenkey_oidc_service.get_auth_code_request_url(openid_client,
                                                                          login_hint_token,
                                                                          state,
                                                                          mccmnc,
                                                                          **auth_kwargs)
        return Redirect(authorization_url)

    if code:
        # Token exchange:
        # Now that the Auth redirect has returned to our app with an auth code, we can
        # do the token exchange.
        # Exchange the auth code for a token and then call the userinfo endpoint.

        token_response = zenkey_oidc_service.request_token(openid_client,
                                                           request.environ['QUERY_STRING'])

        if isinstance(token_response, TokenErrorResponse):
            raise Unauthorized("%s: %s" % (token_response.get('error'),
                                           token_response.get('error_description')))

        # if auth in progress, do the auth thing
        # otherwise do the userinfo call and login
        if auth_flow_handler.authorization_in_progress():
            return auth_flow_handler.success_router(token_response)

        # fetch the userinfo from the API
        userinfo = zenkey_oidc_service.get_userinfo(openid_client, token_response["access_token"])

        if not isinstance(userinfo, ZenKeySchema):
            # the userinfo request failed
            raise Unauthorized("%s: %s" % (userinfo.get('error'),
                                           userinfo.get('error_description')))

        # this is where a real app might look up the user in the database using the "sub" value
        # we could also create a new user or show a registration form
        # the userinfo object contains values like sub, name, and email (depending on which
        # scopes were requested)
        # these values can be saved for the user or used to auto-populate a registration form

        # save the userinfo in the session and return to the homepage: now the user is logged in
        session['userinfo'] = json.dumps(userinfo.to_dict())
        return redirect('/')

    # If we have no mccmnc, begin the carrier discovery process
    return redirect('/auth')

@application.route('/authorize-transaction', methods=['POST'])
def authorize_transaction():
    """
    Handle the transaction form, save the transaction details in the
    session and kick off the auth flow
    """
    amount = request.form.get('amount')
    recipient = 'John Doe'

    auth_flow_handler = AuthorizationFlowHandler(session)

    context = 'Send $%s to %s' % (amount, recipient)
    auth_flow_handler.set_authorization_details('transaction', context, {
        'amount': amount,
        'recipient': recipient
    })
    return redirect('/auth')

@application.route('/logout')
def logout():
    """log the user out and return to the homepage"""
    session.clear()
    return redirect('/')
