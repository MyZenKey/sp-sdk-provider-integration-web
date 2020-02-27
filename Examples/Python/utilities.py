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
import json
from json import JSONDecodeError

def normalize_port(port):
    """Normalize a port into a number or false."""
    if port is None:
        return False
    try:
        return int(port)
    except ValueError:
        return False

def get_current_user(flask_session):
    """get the current user info from the session"""
    if 'userinfo' in flask_session:
        try:
            return json.loads(flask_session['userinfo'])
        except JSONDecodeError:
            return None
    return None
