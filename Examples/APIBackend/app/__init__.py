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

from flask import Flask, request, jsonify, render_template, json
from flask_cors import CORS
from flask_talisman import Talisman
from werkzeug.exceptions import HTTPException
from werkzeug.wrappers import Response

from app.routes.server_initiated import serverInitiated
from app.routes.client_initiated import clientInitiated
from app.routes.users import users

logging.basicConfig(level=logging.DEBUG)

# set up Flask
application = Flask(__name__) # pylint: disable=invalid-name
# use Talisman to add security headers
Talisman(application, content_security_policy=None)

# load configuration from config.py
application.config.from_object('config')

# we default to allowing all domains for simplicity
CORS(application)

# add routes for client initiated auth
application.register_blueprint(clientInitiated)

# add routes for server initiated auth
application.register_blueprint(serverInitiated)

# add user routes
application.register_blueprint(users)

# add error handler
@application.errorhandler(Exception)
def handle_exception(error):
    """Return JSON instead of HTML for HTTP errors."""
    if not isinstance(error, HTTPException):
        application.logger.exception(('Unhandled Exception: %s', (error)), extra={'stack': True})

    # create the response body
    data = {
        "error": getattr(error, 'name', type(error).__name__),
        "error_description": getattr(error, 'description', ''),
        "error_code": getattr(error, 'code', 500),
    }

    if hasattr(error, 'get_response'):
        # start with the correct headers and status code from the error
        response = error.get_response()
        response.data = json.dumps(data)
        response.mimetype = 'application/json'
    else:
        response = application.response_class(response=json.dumps(data),
                                  status=getattr(error, 'code', 500),
                                  mimetype='application/json')
    return response

# add index route for status
@application.route('/')
def index_route():
    """status page"""
    status = "Service is UP and running"
    if request.is_json:
        return jsonify({"status": status})

    return status


# add swagger route
@application.route('/swagger')
def swagger():
    """
    Swagger UI documentation
    """
    return render_template('swagger.html')
