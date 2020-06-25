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
from base64 import b64encode
import os
import json
import urllib.parse
from oic import rndstr
from oic.oauth2.message import Message
from oic.oauth2.message import ParamDefinition
from oic.oauth2.message import (SINGLE_OPTIONAL_STRING, SINGLE_REQUIRED_STRING)
from oic.oic.message import (AuthorizationResponse, AccessTokenResponse)
from oic.exception import (MessageException, PyoidcError)
import requests

OIDC_PROVIDER_CONFIG_ENDPOINT = os.getenv('OIDC_PROVIDER_CONFIG_URL')
CARRIER_DISCOVERY_ENDPOINT = os.getenv('CARRIER_DISCOVERY_URL')

def msg_ser(inst, sformat, lev=0):
    if sformat in ["urlencoded", "json"]:
        if isinstance(inst, Message):
            res = inst.serialize(sformat, lev)
        else:
            res = inst
    elif sformat == "dict":
        if isinstance(inst, Message):
            res = inst.serialize(sformat, lev)
        elif isinstance(inst, dict):
            res = inst
        elif isinstance(inst, str):  # Iff ID Token
            res = inst
        else:
            raise MessageException("Wrong type: %s" % type(inst))
    else:
        raise PyoidcError("Unknown sformat", inst)

    return res

def name_deser(val, sformat="urlencoded"):
    if sformat in ["dict", "json"]:
        if not isinstance(val, str):
            val = json.dumps(val)
            sformat = "json"
        elif sformat == "dict":
            sformat = "json"
    return NameClaim().deserialize(val, sformat)

class NameClaim(Message):
    c_param = {
        "value": SINGLE_OPTIONAL_STRING,
        "given_name": SINGLE_OPTIONAL_STRING,
        "family_name": SINGLE_OPTIONAL_STRING
    }

def value_deser(val, sformat="urlencoded"):
    if sformat in ["dict", "json"]:
        if not isinstance(val, str):
            val = json.dumps(val)
            sformat = "json"
        elif sformat == "dict":
            sformat = "json"
    return ValueClaim().deserialize(val, sformat)

class ValueClaim(Message):
    c_param = {
        "value": SINGLE_OPTIONAL_STRING
    }

# Here we define our parameters for the ZenKey schema by providing a serializer
# and deserializer that tells Pyoidc how to read Userinfo JSON.
# See the Pyoidc implementation for examples of single nested parameters
# and ParamDefinition method signature:
# https://github.com/OpenIDC/pyoidc/blob/master/src/oic/oic/message.py
OPTIONAL_NAME = ParamDefinition(Message, False, msg_ser, name_deser, False)
OPTIONAL_NESTED_VALUE = ParamDefinition(Message, False, msg_ser, value_deser, False)

class ZenKeySchema(Message):
    """
    This is the schema of the data returned from the Userinfo endpoint.
    The default Pyoidc OpenIDSchema does not support double nested parameters,
    so we have to define our own using ParamDefinition above. 
    """
    c_param = {
        "sub": SINGLE_REQUIRED_STRING,
        "name": OPTIONAL_NAME,
        "email": OPTIONAL_NESTED_VALUE,
        "phone": OPTIONAL_NESTED_VALUE,
        "postal_code": OPTIONAL_NESTED_VALUE
    }

class ZenKeyOIDCService:
    """
    This class deals with the ZenKey OAuth2/OpenID Connect flow

    the auth flow proceeds in this order:
    1. carrierDiscoveryRedirect()
        In order to discover the OIDC provider information, we need an MCCMNC.
        To get one, we redirect the user to carrier discovery where they select
        their carrier and authorize their browser.
    2. discoverOIDCClient()
        The carrier discovery screen redirects back to our app with an MCCMNC.
        We can use this MCCMNC to make a call to the OIDC discovery endpoint to
        get OIDC issuer information for the user's carrier (Verizon, AT&T, etc)
    3. requestAuthCodeRedirect()
        Now that we have the OIDC issuer endpoint info, we need an auth code.
        To get one, we redirect the user to the auth endpoint. They will be
        prompted to authorize this app.
    4. requestToken()
        The auth screen redirects back to our app with an auth code.
        We can exchange this code for an access token and ID token.
        Once we have these tokens, we know the user is authenticated and we can make requests
        to the Userinfo endpoint.
    """

    def __init__(self, client_id, client_secret, redirect_uri, session_service):
        self.client_id = client_id
        self.client_secret = client_secret
        self.redirect_uri = redirect_uri
        self.session_service = session_service

    def carrier_discovery_redirect(self):
        """
        Carrier Discovery:
        To learn the mccmnc, we send the user to the ZenKey discovery endpoint.
        This endpoint will redirect the user back to our app, giving us
        the mccmnc that identifies the user's carrier.
        """
        # save a random state value to prevent request forgeries
        new_state = rndstr()
        self.session_service.set_state(new_state)

        return '%s?client_id=%s&redirect_uri=%s&state=%s' % (
            CARRIER_DISCOVERY_ENDPOINT,
            urllib.parse.quote(self.client_id, safe=''),
            urllib.parse.quote(self.redirect_uri, safe=''),
            urllib.parse.quote(new_state, safe=''))

    def discover_oidc_provider_metadata(self, mccmnc):
        """
        Make an HTTP request to the ZenKey discovery issuer endpoint to access
        the carrier’s OIDC configuration then build an OAuth2 client with the configuration
        """
        config_url = '%s?client_id=%s&mccmnc=%s' % (OIDC_PROVIDER_CONFIG_ENDPOINT,
                                                    self.client_id,
                                                    mccmnc)
        config_response = requests.get(config_url)
        config_json = config_response.json()
        if (config_json == {} or config_json['issuer'] is None):
            return None
        return config_json

    def get_auth_code_request_url(self, openid_client, login_hint_token, state, mccmnc, **kwargs):
        """
        Get the user an auth code
	    now that we have discovered the OIDC endpoint information, we can redirect
	    to ask the user to authorize and get an auth code

	    This will build an auth code URL and save the necessary state information

        kwargs:
        :key scope: a list of the scopes requested - we only need the openid
                    scope when authorizing a transaction
        :key context: this context will be shown to the user during authorization
        :key acr_values: request a3 ACR value for strong authentication assertion
        """
        # prevent request forgeries by checking that the incoming state matches
        if state != self.session_service.get_state():
            raise Exception('state mismatch after carrier discovery')

        # generate code verifier and code challenge for PKCE
        # and a state and nonce value for the auth redirect
        # persist the mccmnc and these generated values in the session
        pkce_args, code_verifier = openid_client.add_code_challenge()
        auth_request_state = rndstr()
        auth_request_nonce = rndstr()
        self.session_service.set_state(auth_request_state)
        self.session_service.set_nonce(auth_request_nonce)
        self.session_service.set_mccmnc(mccmnc)
        self.session_service.set_code_verifier(code_verifier)

        # default to just the basic openid scope
        scope = kwargs.get('scope', 'openid')
        context = kwargs.get('context')
        acr_values = kwargs.get('acr_values')

        # send user to the ZenKey authorization endpoint to request an auth code
        request_args = {
            "client_id": self.client_id,
            "response_type": "code",
            "scope": scope,
            "redirect_uri": self.redirect_uri,
            "state": auth_request_state,
            "nonce": auth_request_nonce,
            "login_hint_token": login_hint_token,
            "code_challenge": pkce_args['code_challenge'],
            "code_challenge_method": pkce_args['code_challenge_method']
        }
        if context is not None:
            request_args['context'] = context
        if acr_values is not None:
            request_args['acr_values'] = acr_values
        auth_request = openid_client.construct_AuthorizationRequest(request_args=request_args)
        return auth_request.request(openid_client.authorization_endpoint)

    def request_token(self, openid_client, query_string):
        """
        We have an auth code, we can now exchange it for a token
	    First parse the request information to make sure we got a code successfully
        """
        auth_response = openid_client.parse_response(AuthorizationResponse,
                                                     info=query_string,
                                                     sformat="urlencoded")

        # prevent request forgeries by checking that the incoming state matches
        if auth_response["state"] != self.session_service.get_state():
            raise Exception('state mismatch after receiving auth code')

        auth_code = auth_response["code"]
        code_verifier = self.session_service.get_code_verifier()

        # use an Authorization header to send the basic auth's client ID and secret
        client_id_secret = "%s:%s" % (self.client_id, self.client_secret)
        auth_secret = b64encode(client_id_secret.encode('utf-8'))
        token_request_headers = {
            'Authorization': 'Basic %s' % auth_secret.decode("ascii"),
        }

        # Pyoidc's do_access_token_request automatically includes a client_id param
        # which Verizon doesn't like. We need to make a manual POST request instead
        # if Verizon ever fixes their bug, we can use do-access_token_request again
        # token_response = openid_client.do_access_token_request(state=auth_response["state"],
        #                             request_args={
        #                                 "code": auth_response["code"],
        #                                 "redirect_uri": redirect_uri,
        #                             },
        #                             authn_method="client_secret_basic",
        #                             headers=token_request_headers
        #                             )

        token_request_payload = {
            'grant_type': 'authorization_code',
            'code': auth_code,
            'redirect_uri': self.redirect_uri,
            # code verifier is used for PKCE
            'code_verifier': code_verifier,
            # Don't include client_id param: Verizon doesn't like it
        }
        token_response = requests.post(openid_client.token_endpoint,
                                       data=token_request_payload,
                                       headers=token_request_headers,
                                       timeout=20)

        # pyoidc handles id_token token verification under the hood
        tokens = openid_client.parse_request_response(token_response, AccessTokenResponse,
                                                      body_type="json")

        if not isinstance(token_response, AccessTokenResponse):
            # clear the state and nonce
            self.session_service.clear()
            # return the error response object for handling
            return tokens

        # validate that the nonce matches the one we sent in the auth request
        if tokens['id_token']['nonce'] != self.session_service.get_nonce():
            raise Exception("The id_token nonce does not match.")

        # clear the state and nonce
        self.session_service.clear()

        return tokens

    def get_userinfo(self, openid_client, access_token):
        """
        Make an API call to the carrier to get user info, using the token we received
        """
        return openid_client.do_user_info_request(token=access_token,
                                                  behavior="use_authorization_header",
                                                  user_info_schema=ZenKeySchema,
                                                  method="GET")
