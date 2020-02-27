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
class SessionService:
    """
    a service for persisting items in the session
    """

    state_cache_key = "zenkey_state"
    mccmnc_cache_key = "zenkey_mccmnc"

    def __init__(self, session):
        self.session = session

    def clear(self):
        """
        clear the session storage
        """
        try:
            del self.session[self.state_cache_key]
        except KeyError:
            pass
        try:
            del self.session[self.mccmnc_cache_key]
        except KeyError:
            pass

    def set_state(self, state):
        """
        persist the state in the session
        """
        self.session[self.state_cache_key] = state

    def get_state(self):
        """
        get the state from the session
        """
        return self.session.get(self.state_cache_key)

    def set_mccmnc(self, mccmnc):
        """
        persist the MCCMNC in the session
        """
        self.session[self.mccmnc_cache_key] = mccmnc

    def get_mccmnc(self):
        """
        get the state from the session
        """
        return self.session.get(self.mccmnc_cache_key)
