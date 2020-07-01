import logging

from flask import current_app, g
from flask_httpauth import HTTPTokenAuth
import jwt
from jwt.exceptions import InvalidTokenError
from werkzeug.exceptions import Unauthorized

# TODO make logging global
logging.basicConfig(level=logging.DEBUG)

accessTokenAuth = HTTPTokenAuth(scheme="Bearer") # pylint: disable=invalid-name

# because this uses the current_app and global contexts it can only be usecd
# during a request
@accessTokenAuth.verify_token
def verify_access_token(access_token):
    """
    verify an access token by checking it against the Authorization: Bearer header
    """
    try:
        # this method will throw an error if the access token has been tampered with
        decoded = jwt.decode(access_token, current_app.config['SECRET_KEY'], algorithms='HS256')
        g.current_user = decoded
        return True
    except InvalidTokenError as error:
        logging.exception(error)
        return False

@accessTokenAuth.error_handler
def access_token_error_handler():
    '''handle unauthorized case in the HTTPAuth'''
    raise Unauthorized('Missing or invalid access token')
