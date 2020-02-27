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
from flask import redirect
from utilities import get_current_user

class AuthorizationFlowHandler:
    """
    This class helps with the ZenKey non-login authorize flow. It saves
    authorization details in the session and contains the authorize success
    callbacks
    """

    sessionKey = 'authorization'
    session = None

    def __init__(self, session):
        self.session = session

    def authorization_in_progress(self):
        """
        Check whether a non-login authorization is in progress: if so there will be
        details saved in the session
        """
        if self.get_authorization_details() is None:
            return False
        return True

    def delete_authorization_details(self):
        """
        Remove the in-progress authorization details from the session
        """
        try:
            del self.session[self.sessionKey]
        except KeyError:
            pass

    def set_authorization_details(self, auth_type, context, options):
        """
        Persist in-progress authorization details in the session After the ZenKey
        auth flow redirects, the app will recognize that an authorization is still in
        progress by looking for this information in the session
        """
        self.session[self.sessionKey] = {
            'type': auth_type,
            'context': context,
            'options': options
        }

    def get_authorization_details(self):
        """
        Get authorization details from the session
        """
        if self.sessionKey in self.session:
            return self.session[self.sessionKey]
        return None

    def success_router(self, tokens):
        """
        Call this after the authorization flow is successful
        It will call a different success method depending on the type of authorization in progress
        """
        if not self.authorization_in_progress():
            # no explicit authorization in progress
            return redirect('/')

        session_authorize_details = self.get_authorization_details()
        auth_type = session_authorize_details['type']

        if auth_type == "transaction":
            return self.transaction_authorize_success(tokens)
        elif auth_type == "adduser":
            return self.add_user_authorize_success()
        else:
            raise Exception("Unknown authorization type")

    def transaction_authorize_success(self, tokens):
        """
        once a transaction has been authorized using ZenKey, this function is called to
        complete the transaction

        SUCCESS:
        now we have authorized the user with ZenKey
        this is where you would add the business logic to complete the transaction
        first verify that the token is for this user
        start by getting the logged in user's "sub" value

        When an transaction authorization is successful, check that the token matches
	    the current user
        If it does, then send a success message to the homepage
        """
        logged_in_user = get_current_user(self.session)
        logged_in_subject = logged_in_user['sub']

        # get the "sub" value from the token
        sub = tokens['id_token']['sub']

        # check that the sub values match
        if logged_in_subject != sub:
            raise Exception("Token does not match user sub")

        # pull the authorization details out of the session so we can build a success
        # message
        session_authorize_details = self.get_authorization_details()
        if session_authorize_details is None:
            return

        options = session_authorize_details['options']

        amount = options['amount']
        recipient = options['recipient']

        # If this were a fully functional app, you might call a function to complete
        # transaction here

        # after completion, remove the authorization details from the session
        self.delete_authorization_details()

        # return to the homepage with a message
        message = "Success: $%s was sent to %s" % (amount, recipient)
        return redirect("/?message=%s" % message)

    def add_user_authorize_success(self):
        """
        You can use ZenKey authorization for multiple things, like authorizing a
	    newly added user on the account
        """
        raise Exception('Not implemented')
