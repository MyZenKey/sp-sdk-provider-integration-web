from base64 import b64encode
import json
from oic.oauth2.message import Message, TokenErrorResponse, ParamDefinition
from oic.oauth2.message import (SINGLE_OPTIONAL_STRING, SINGLE_REQUIRED_STRING)
from oic.oic import Client
from oic.oic.message import AccessTokenResponse, ProviderConfigurationResponse, RegistrationResponse
from oic.utils.authn.client import CLIENT_AUTHN_METHOD
from oic.exception import (MessageException, PyoidcError)
from werkzeug.exceptions import Unauthorized
import requests

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

"""
This function deals with the ZenKey OAuth2/OpenID Connect flow

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
4. requestAccessToken()
    The auth screen redirects back to our app with an auth code.
    We can exchange this code for an access token and ID token.
    Once we have these tokens, we know the user is authenticated and we can make requests
    to the Userinfo endpoint.
"""
def zenkey_oidc_service(required_params, optional_token_request_params, id_token_validator_params):
    """
    Execute entire flow necessary to:
    - discover provider configuration (based on mccmnc)
    - exchange auth code for an access token
    - use that access token to request user info

    Returns user's zenkey info

    required_params:
	client_id:
	client_secret:
	redirect_uri:
	code: authorization code that will be exchanged for a token
	oidc_provider_config_endpoint: zenkey discovery issuer endpoint
    optional_token_request_params:
	correlation_id (string): correlation ID to be added to SP logs,
				 to correlate API requests
	code_verifier (string): PKCE code verifier
	sdk_version (string): version of this SDK for tracking purposes
    id_token_vvalidator_params: (also optional)
	nonce (string): nonce sent in the authorization request
	acr_values (string): acr_values sent in the authorization request
	context (string): context sent in the authorization request
    """
    client_id = required_params['client_id']
    client_secret = required_params['client_secret']
    redirect_uri = required_params['redirect_uri']
    auth_code = required_params['code']
    oidc_provider_config_endpoint = required_params['oidc_provider_config_endpoint']
    mccmnc = required_params['mccmnc']

    # enforce required parameters
    if (client_id is None or
            client_secret is None or
            redirect_uri is None or
            auth_code is None or
            oidc_provider_config_endpoint is None or
            mccmnc is None):
        raise Exception('missing required parameters for zenkey oidc service')

    oidc_provider_config = discover_oidc_provider_config(
        oidc_provider_config_endpoint,
        client_id,
        mccmnc
    )

    openid_client = create_openid_client(
        oidc_provider_config,
        client_id,
        client_secret
    )

    token_request_payload = create_token_request_payload(
        auth_code,
        redirect_uri,
        optional_token_request_params
    )

    tokens = request_access_token(
        openid_client,
        token_request_payload,
        client_id,
        client_secret
    )

    # throws error if id token is invalid
    validate_id_token(tokens['id_token'], id_token_validator_params)
    zenkey_user_info = request_user_info(openid_client, tokens['access_token'])
    return zenkey_user_info

def discover_oidc_provider_config(oidc_provider_config_endpoint, client_id, mccmnc):
    """
    Make an HTTP request to the ZenKey discovery issuer endpoint to access
        the carrier’s OIDC configuration
    """
    oidc_provider_config_url = '%s?client_id=%s&mccmnc=%s' % (
        oidc_provider_config_endpoint,
        client_id,
        mccmnc
    )
    config_response = requests.get(oidc_provider_config_url)
    config_json = config_response.json()
    if (config_json == {} or config_json.get('issuer') is None):
        raise Exception('unable to fetch provider metadata')
    return config_json

def create_openid_client(oidc_provider_config, client_id, client_secret):
    """
    Build an OIDC client using the oidc provider configuration
    """
    # build our OpenID client
    openid_client = Client(client_authn_method=CLIENT_AUTHN_METHOD, client_id=client_id)

    # save the client information to the OIDC client
    client_registration_info = RegistrationResponse(**{
        "client_id": client_id,
        "client_secret": client_secret})
    openid_client.store_registration_info(client_registration_info)

    # save the provider config to the OIDC client
    provider_configuration = ProviderConfigurationResponse(**oidc_provider_config)
    openid_client.handle_provider_config(
        provider_configuration,
        provider_configuration['issuer'],
        True,
        True)

    return openid_client

def create_token_request_payload(auth_code, redirect_uri, optional_token_request_params):
    """
    Construct payload for access token request
    """
    token_request_payload = {
        # Don't include client_id param: Verizon doesn't like it
        'grant_type': 'authorization_code',
        'code': auth_code,
        'redirect_uri': redirect_uri,
        **optional_token_request_params
    }
    return token_request_payload

def request_access_token(openid_client, token_request_payload, client_id, client_secret):
    """
    Exchange an auth code for a token and validate the token response
    """
    # build a client secret header
    client_id_secret = "%s:%s" % (client_id, client_secret)
    auth_secret = b64encode(client_id_secret.encode('utf-8'))
    token_request_headers = {
        'Authorization': 'Basic %s' % auth_secret.decode("ascii"),
    }

    # Pyoidc's do_access_token_request automatically includes a client_id param
    # which Verizon doesn't like. We need to make a manual POST request instead
    # if Verizon ever fixes their bug, we can use do-access_token_request again
    token_response = requests.post(openid_client.token_endpoint,
                                   data=token_request_payload,
                                   headers=token_request_headers,
                                   timeout=20)

    # pyoidc handles id_token token verification under the hood
    tokens = openid_client.parse_request_response(token_response,
                                                  AccessTokenResponse,
                                                  body_type="json")

    if isinstance(tokens, TokenErrorResponse):
        raise Unauthorized("%s: %s" % (tokens.get('error'),
                                       tokens.get('error_description')))

    return tokens

def validate_id_token(id_token, id_token_validator_params):
    """
    Manually verify that the ACR, context, and nonce values in the id token match
    those sent in the authorization request
    """
    # manually verify that the ACR values in the id_token match those sent in the
    # authorization request
    acr_values = id_token_validator_params.get('acr_values')
    context = id_token_validator_params.get('context')
    nonce = id_token_validator_params.get('nonce')

    err_message = None

    if (acr_values and 'acr' in id_token and id_token['acr'] not in acr_values):
        err_message = "ACR value in ID token does not match"

    if (context and 'context' in id_token and id_token['context'] != context):
        err_message = "Context value in ID token does not match"

    if (nonce and id_token['nonce'] != nonce):
        err_message = "Nonce value in ID token does not match"

    if err_message:
        raise Unauthorized("Invalid ID Token: %s" % err_message)

def request_user_info(openid_client, access_token):
    """
    Make an API call to the carrier to get user info, using the token we received
    """
    zenkey_user_info = openid_client.do_user_info_request(
        token=access_token,
        behavior="use_authorization_header",
        user_info_schema=ZenKeySchema,
        method="GET")

    if not isinstance(zenkey_user_info, ZenKeySchema):
        # the user_info request failed
        raise Unauthorized("%s: %s" % (zenkey_user_info.get('error'),
                                       zenkey_user_info.get('error_description')))

    return zenkey_user_info
