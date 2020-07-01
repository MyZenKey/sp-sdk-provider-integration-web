from flask import Blueprint, current_app, request, jsonify
from werkzeug.exceptions import BadRequest

from app.auth.http_api_key import apiKeyAuth
from app.auth.http_access_token import accessTokenAuth
from app.models.user_model import UserModel
from app.utils.create_jwt import create_jwt
from app.utils.validate_client_credentials import validate_client_credentials
from app.utils.validate_params import validate_params
from app.utils.zenkey_oidc_service import zenkey_oidc_service
# from app.zenkey_oidc_service import ZenKeyOIDCService

clientInitiated = Blueprint('clientAuth', __name__) # pylint: disable=invalid-name

def parse_signin_request():
    """
    Passes request params to pass to the zenkey_oidc_service to get the user info

    This method has access to the request scope because it's called from the
    zenkey-signin route
    """
    # these parameters are required by zenkey oidc service
    required_params = validate_params(request, [
        'client_id',
        'code',
        'redirect_uri',
        'mccmnc'
    ])

    # validate client credentials and get the client secret
    _, client_secret = validate_client_credentials(
        current_app.config['ALLOWED_ZENKEY_CLIENTS'],
        required_params['client_id']
    )

    # add client_secret and oidc provider config endpoint, which are required paramters that
    # do not come from the client request
    required_params['client_secret'] = client_secret
    required_params['oidc_provider_config_endpoint'] = (
        current_app.config['OIDC_PROVIDER_CONFIG_ENDPOINT']
    )

    # these optional parameters are passed in the openid token request
    optional_token_request_params = validate_params(request, optional_params=[
        'correlation_id',
        'code_verifier',
        'sdk_version'
    ])

    # these optoinal parameters are used to validate the id_token in the oidc response
    id_token_validator_params = validate_params(request, optional_params=[
        'acr_values',
        'context',
        'nonce'
    ])

    return (required_params, optional_token_request_params, id_token_validator_params)

@clientInitiated.route('/auth/zenkey-signin', methods=['POST'])
@apiKeyAuth.login_required
def token_route():
    """
    Exchange an auth code for a token

    This endpoint makes a request to the ZenKey token endpoint to exchange
    an auth code for a token. It then calls the ZenKey user info endpoint to get
    user attributes. Once the user has been authorized with ZenKey, we return a JWT.
    This JWT can be used as an access token to authorize API calls. It must be included in
    an Authorization header with any sensitive API calls: `Authorization: Bearer {jwt}`.

    When using JWTs we would normally keep a blacklist of banned/expired JWTS. We've
    omitted this step for simplicity.
    """
    (
        required_params,
        optional_token_request_params,
        id_token_validator_params
    ) = parse_signin_request()

    zenkey_user_info = zenkey_oidc_service(
        required_params,
        optional_token_request_params,
        id_token_validator_params
    )

    existing_user = UserModel.find_zenkey_user(zenkey_user_info)

    if existing_user is None:
        # This user doesn't have an account in our database yet.
        # We need to tell the client to send the user to the registration flow
        # and pre-populate the registration form with the ZenKey user info.

        # In your application, you may prefer to auto-create a new user if you could not find
        # a user with a matching "sub" in your database.
        # Or you could attempt to link this ZenKey user with an existing user, allowing
        # the user to log in either with a username/password or Sign in with ZenKey.
        # Some ideas for account linking:
        # - auto-merge a ZenKey user with another user if both users have the
        #       same verified email address
        # - suggest similar accounts that the user can choose to merge
        # - allow the user to initiate account linking after they have logged in
        #   - merge two existing accounts
        #   - add a new ZenKey login method to an existing account

        # CARRIER MIGRATION
        # Learn more about carrier account migration at
        # https://developer.myzenkey.com/web/#40-account-migration
        # If a user has switched to a different carrier (i.e. Verizon -> Sprint)
        # they will appear as a new user with a new "sub" value.
        # This app will need to use the carrier migration flow to update the user
        # with their new "sub" value
        # As of March 2020 this feature has not yet been released

        return jsonify({
            'zenkey_attributes': zenkey_user_info.to_dict(),
            'error': 'ZenKey user does not exist',
            'error_description': 'Unable to find a user with a matching "zenkey_sub" value'
        }), 403

    jwt_token = create_jwt(existing_user,
                           current_app.config['TOKEN_EXPIRATION_TIME'],
                           current_app.config['BASE_URL'],
                           current_app.config['SECRET_KEY'])

    # we omit the refresh token for brevity in this example codebase
    # in production the API client should be able to optain a new token after this token expires

    return jsonify({
        'token': jwt_token,
        'refresh_token': 'omitted',
        'token_type': 'bearer',
        'expires': current_app.config['TOKEN_EXPIRATION_TIME'].total_seconds()
    })

@clientInitiated.route('/auth/token', methods=['POST'])
@apiKeyAuth.login_required
def refresh_token_route():
    """
    Use a refresh token to create a new token
    """
    required_params = ['grant_type', 'refresh_token']
    validated_params = validate_params(request, required_params)

    # refresh token functionality has been omitted for brevity in this example codebase
    # here you would deactive the user's current token and generate a new one for the user
    if validated_params['grant_type'] != 'refresh_token':
        raise BadRequest('Only "refresh_token" grant types are accepted')

    # here you would also validated the refresh token before issuing a new token

    return jsonify({
        # for brevity we just respond with a fake, unusable token
        'token': 'new_fake_token',
        # we omit the refresh token for brevity in this example
        'refresh_token': 'new_fake_refresh_token',
        'token_type': 'bearer',
        'expires': current_app.config['TOKEN_EXPIRATION_TIME'].total_seconds()
    })

@clientInitiated.route('/auth/token', methods=['DELETE'])
@accessTokenAuth.login_required
@apiKeyAuth.login_required
def delete_session_route():
    """
    Delete a session
    """
    # this functionality has been omitted for brevity in this example codebase
    # here you would deactivate the token and any refresh tokens. If using JWTs, you might add
    # this user's JWT to a blacklist
    return ""
