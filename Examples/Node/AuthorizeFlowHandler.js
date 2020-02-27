/*
 * Copyright 2020 ZenKey, LLC.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
const authorizeCallback = require("./authorizeCallback");
const SessionService = require("./SessionService");

/**
 * This class helps with the ZenKey non-login authorize flow
 * It saves authorization details in the session and contains the authorize success callbacks
 *
 */
const AuthorizeFlowHandler = {
  authorizationInProgress: session => {
    if (session.authorize == null) {
      return false;
    }
    return true;
  },
  deleteAuthorizationDetails: session => {
    try {
      delete session.authorize; // eslint-disable-line no-param-reassign
    } catch (e) {} // eslint-disable-line no-empty
  },
  setAuthorizationDetails: (session, type, context, options = {}) => {
    // eslint-disable-next-line no-param-reassign
    session.authorize = {
      type,
      context,
      options
    };
  },
  getAuthorizationDetails: session => {
    return session.authorize;
  },

  /**
   * determine which kind of authorize flow is in progress
   * and return the callback that will handle the authorize process
   */
  router: (authorizationDetails, zenkeyOIDCService) => {
    const { type: authorizationType, context } = authorizationDetails;
    const urlOptions = {
      context, // this context will be shown to the user during authorization
      acrValues: "a3", // request a3 ACR value for strong authentication assertion
      scope: "openid" // we only need the openid scope when authorizing a transaction
    };

    switch (authorizationType) {
      case "transaction":
        return (req, res, next) => {
          // authenticate the user for a transaction
          authorizeCallback(
            req,
            res,
            next,
            zenkeyOIDCService,
            AuthorizeFlowHandler.transactionAuthorizeSuccess,
            urlOptions
          ).catch(e => {
            AuthorizeFlowHandler.transactionAuthorizeError(req, res, next, e);
          });
        };
      case "adduser":
        // this is where you might call the authorizeCallback for a different kind of authorization
        // for example: authorizing the addition of a new user to the account
        // return (req, res, next) => {
        //   authorizeCallback( req, res, next, zenkeyOIDCService, AuthorizeFlowHandler.addUserAuthorizeSuccess);
        // };
        throw new Error("Not implemented");
      default:
        throw new Error("Unknown authorization type");
    }
  },

  // once a transaction has been authorized using ZenKey, this function is called to
  // complete the transaction
  transactionAuthorizeSuccess: (req, res, next, openIDClient, tokenSet) => {
    // SUCCESS:
    // now we have authorized the user with ZenKey
    // this is where you would add the business logic to complete the transaction
    let amount;
    let recipient;

    try {
      ({ amount, recipient } = AuthorizeFlowHandler.getAuthorizationDetails(
        req.session
      ).options);
    } finally {
      // after completion, remove the authorization details from the session
      AuthorizeFlowHandler.deleteAuthorizationDetails(req.session);
    }

    // verify that the token is correct for this user
    if (tokenSet.claims.sub !== req.user.sub) {
      next(new Error("Token does not match user sub"));
    }

    // return to the homepage with a message
    res.redirect(`/?message=Success: $${amount} was sent to ${recipient}`);
  },

  // handle errors in the auth flow
  transactionAuthorizeError: (req, res, next, error) => {
    try {
      // if an error happens, delete the auth information saved in the session
      AuthorizeFlowHandler.deleteAuthorizationDetails(req.session);
      const sessionService = new SessionService();
      sessionService.clear(req.session);
    } catch (newError) {
      // if there's an error in the error handler, show that error
      return next(newError);
    }
    return next(error);
  },

  addUserAuthorizeSuccess: () => {
    throw new Error("Not implemented");
  }
};

module.exports = AuthorizeFlowHandler;
