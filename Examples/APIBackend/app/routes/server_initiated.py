import secrets
from flask import Blueprint, current_app, request, jsonify

from app.auth.http_api_key import apiKeyAuth
from app.models.user_model import UserModel
from app.utils.create_jwt import create_jwt
from app.utils.validate_params import validate_params

serverInitiated = Blueprint('serverAuth', __name__) # pylint: disable=invalid-name

# send header: "X-API-Key: my_api_key"
@serverInitiated.route('/auth/zenkey-async-signin', methods=['POST'])
@apiKeyAuth.login_required
def async_token_request():
    """
    Exchange an auth code for an auth request id

    This endpoint makes a request to the ZenKey token endpoint to exchange
    an auth code for a token. It then calls the ZenKey userinfo endpoint to get
    user attributes. Once the user has been authorized with ZenKey, the server
    creates a JWT that will be returned to get requests on the
    '/auth/zenkey-async-signin/{auth_request_id}' endpoints.  This JWT can be
    used as an access token to authorize API calls. It must be included in
    an Authorization header with any sensitive API calls: `Authorization: Bearer {jwt}`.

    NOTE: at the moment this endpoint is only a mock, no request is actually
    made
    """
    required_params = ['login_hint',
                       'client_id',
                       'scope',
                       'mccmnc',
                       'redirect_uri']
    optional_params = ['correlation_id']
    validated_params = validate_params(request, required_params, optional_params)

    # if this was not a mock we would request a token from zenkey

    # create mock auth req id
    auth_req_id = validated_params['login_hint'] + '_' + str(secrets.SystemRandom().randrange(100000))

    return jsonify({
        'auth_req_id': auth_req_id,
        'expires_in': 3600
    })

@serverInitiated.route('/auth/zenkey-async-signin/<string:auth_req_id>', methods=['GET'])
@apiKeyAuth.login_required
def async_token_result(auth_req_id):
    """
    Report the result of an authentication request

    This endpoint returns the result of the request made in the
    '/auth/zenkey-async-signin' endpoint, identified by auth_req_id. If the
    request was successful, then the server makes and returns a token which can
    be used as to authorize API calls.  It must be included in an Authorization
    header with any sensitive API calls: `Authorization: Bearer {jwt}`.

    NOTE: at the moment this endpoint is only a mock, there is no request for
    which to return the result
    """

    # create a new user based on auth request so that each auth request returns a different token
    new_user_params = {
        'zenkey_sub': auth_req_id,
        'name': 'Mock User',
        'phone_number': '+15555555555',
        'postal_code': '55555',
        'email': 'mockuser@mock.user',
        'username': 'mockuser',
        'password': 'mockuser'
    }
    new_user = UserModel.create_new_user(new_user_params)
    jwt_token = create_jwt(new_user,
                           current_app.config['TOKEN_EXPIRATION_TIME'],
                           current_app.config['BASE_URL'],
                           current_app.config['SECRET_KEY'])

    return jsonify({
        'auth_req_id': auth_req_id,
        'token': jwt_token,
        'token_type': 'bearer',
        # we omit the refresh token for brevity in this example codebase
        'refresh_token': 'omitted',
        'expires': current_app.config['TOKEN_EXPIRATION_TIME'].total_seconds()
    })

@serverInitiated.route('/auth/zenkey-async-signin/<string:auth_req_id>', methods=['POST'])
@apiKeyAuth.login_required
def async_token_retry(auth_req_id):
    """
    Retry an authentication request
This endpoint retries the request made in the '/auth/zenkey-async-signin'
    endpoint, identified by auth_req_id, and has the same return value.

    NOTE: at the moment this endpoint is only a mock, there is no request to
    retry
    """
    return jsonify({'auth_req_id': auth_req_id})

@serverInitiated.route('/auth/zenkey-async-signin/<string:auth_req_id>', methods=['DELETE'])
@apiKeyAuth.login_required
def async_token_cancel(auth_req_id): #pylint: disable=unused-argument
    """
    Cancel an authentication request

    This endpoint cancels the request made in the '/auth/zenkey-async-signin'
    endpoint, identified by auth_req_id, and just returns a status, 200 if
    successful.

    NOTE: at the moment this endpoint is only a mock, there is no request to
    cancel
    """
    return ""

@serverInitiated.route('/auth/zenkey-async-signin/notification', methods=['POST'])
# add bearer token validation
def async_token_grant():
    """
    Grant token request

    This endpoint grants the token request made in the '/auth/zenkey-async-signin'
    endpoint, identified by auth_req_id, and has the same return value. The
    ZenKey carrier hits this endpoint

    NOTE: at the moment this endpoint is only a mock, there is no actual token
    request to grant
    """
    required_params = ['auth_req_id',
                       'state',
                       'scope']
    optional_params = ['access_token',
                       'expires_in',
                       'refresh_token',
                       'id_token',
                       'error',
                       'error_description',
                       'correlation_id']
    validate_params(request, required_params, optional_params)

    # if this was not a mock we would save the ranted token infromation to a db

    return ""
