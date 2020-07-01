from flask import Blueprint, current_app, request, jsonify, g

from app.auth.http_api_key import apiKeyAuth
from app.auth.http_access_token import accessTokenAuth
from app.models.user_model import UserModel
from app.utils.create_jwt import create_jwt
from app.utils.validate_params import validate_params

users = Blueprint('users', __name__) # pylint: disable=invalid-name

@users.route('/users', methods=['POST'])
@apiKeyAuth.login_required
def create_user_route():
    """
    Register a new user

    When the client called /auth/signin, we couldn't find the ZenKey user in the database
    so we returned the ZenKey user info and told the client it was a new user.
    The client displayed a registration form pre-populated with the ZenKey user data,
    including the unique ZenKey "sub"
    Here we receive the contents of that registration form, create a new user,
    log the user in and return a token
    """
    # the new user must include the ZenKey "sub" to match them with a ZenKey account
    required_params = ['zenkey_sub']
    optional_params = ['name',
                       'phone_number',
                       'postal_code',
                       'email',
                       'username',
                       'password']
    new_user_params = validate_params(request, required_params, optional_params)
    new_user = UserModel.create_new_user(new_user_params)
    g.current_user = new_user

    jwt_token = create_jwt(new_user,
                           current_app.config['TOKEN_EXPIRATION_TIME'],
                           current_app.config['BASE_URL'],
                           current_app.config['SECRET_KEY'])

    return jsonify({
        'token': jwt_token,
        # we omit the refresh token for brevity in this example codebase
        'refresh_token': 'fake_refresh_token',
        'token_type': 'bearer',
        'expires': current_app.config['TOKEN_EXPIRATION_TIME'].total_seconds()
    }), 201

@users.route('/users/me', methods=['GET'])
@accessTokenAuth.login_required
@apiKeyAuth.login_required
def current_user_route():
    """
    look up user information in the database
    """
    user = UserModel.find_user(g.current_user)

    return jsonify(user)
