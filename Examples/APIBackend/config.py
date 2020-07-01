import os
from urllib.parse import urlparse
from datetime import (timedelta)

# create the app configuration baesd on environment variables

# The ALLOWED_ZENKEY_CLIENTS environment variable is a whitelist of
# ZenKey client IDs and secrets that this app can use when calling the ZenKey API.
# It uses the format "my_id:my_secret,my_other_id:my_other_secret".

# in real life, it's likely your app would only ever need one client ID/secret
# so this could be simplified to
# ZENKEY_CLIENT_ID=my_id
# ZENKEY_CLIENT_SECRET=my_secret
ALLOWED_ZENKEY_CLIENTS = os.getenv('ALLOWED_ZENKEY_CLIENTS')
# format is "my_id:my_secret,my_other_id:my_other_secret"
# split by comma and then by colon
ALLOWED_ZENKEY_CLIENTS = ([c.split(':') for c in ALLOWED_ZENKEY_CLIENTS.split(',')]
                          if ALLOWED_ZENKEY_CLIENTS
                          else [])

# The API_KEYS environment variable is a whitelist of API keys
# that can be used to connect to this API. API clients must send one of these
# keys with every request. This will discourage third party users from using this API backend.
# You may also wish to use CORS settings to prevent disallowed websites from using this API.
API_KEYS = os.getenv('API_KEYS')
API_KEYS = API_KEYS.split(',') if API_KEYS else []

# we use a very long expiration value because this example app does
# not support refresh tokens. Your production code should use a much shorter expiration time
TOKEN_EXPIRATION_TIME = timedelta(days=30)

# Endpoint from which to get oidc provider configuration
OIDC_PROVIDER_CONFIG_ENDPOINT = os.getenv('OIDC_PROVIDER_CONFIG_URL')

BASE_URL = os.getenv('BASE_URL')
PARSED_URL = urlparse(BASE_URL)
HOSTNAME = PARSED_URL.hostname
IS_LOCAL = HOSTNAME == 'localhost'

# the following configuration values are used internally by Flask

# no session cookies on localhost
SESSION_COOKIE_DOMAIN = False if IS_LOCAL else HOSTNAME

SECRET_KEY = os.getenv('SECRET_KEY_BASE')

SERVER_NAME = PARSED_URL.hostname
if PARSED_URL.port:
    SERVER_NAME = '%s:%s' % (SERVER_NAME, PARSED_URL.port)
