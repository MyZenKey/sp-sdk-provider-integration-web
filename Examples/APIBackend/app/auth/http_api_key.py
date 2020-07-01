from flask import current_app, request
from flask_httpauth import HTTPTokenAuth
from werkzeug.datastructures import Authorization
from werkzeug.exceptions import Unauthorized

# because this uses the current_app context it can only be usecd # during a request
class HTTPAPIKeyAuth(HTTPTokenAuth):
    """
    Extension of the HTTPTokenAuth class to allow sending a header like
    "X-API-KEY: my_api_key"
    It doesn't use the "Authorization" header name and doesn't use a scheme type
    """
    def __init__(self):
        super(HTTPAPIKeyAuth, self).__init__(None, None)

        self.verify_token_callback = None

    def get_auth(self):
        if 'X-API-Key' in request.headers:
            return Authorization('api_key', {'token': request.headers['X-API-Key']})
        return None

apiKeyAuth = HTTPAPIKeyAuth() # pylint: disable=invalid-name

@apiKeyAuth.verify_token
def verify_api_key(api_key):
    """
    verify an API get in the X-API-Key header by checking it against the environment
    """
    if api_key in current_app.config['API_KEYS']:
        return True
    return False

@apiKeyAuth.error_handler
def api_key_error_handler():
    '''handle unauthorized case in the HTTPAuth'''
    raise Unauthorized('Missing or invalid API key')
