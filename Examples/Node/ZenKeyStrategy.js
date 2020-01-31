/*
 * Copyright 2019 ZenKey, LLC.
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
const passport = require("passport-strategy");

const ZenKeyOIDCService = require("./ZenKeyOIDCService");
const SessionService = require("./SessionService");

// this function is called after user verification is complete. It handles error and success cases
function verified(error, user) {
  if (error) {
    this.error(error);
  } else {
    this.success(user);
  }
}

class ZenKeyStrategy extends passport.Strategy {
  constructor(clientId, clientSecret, redirectUri, scope, verify) {
    super();

    this.name = "zenkey";
    this.verify = verify;
    this.scope = scope;

    this.zenkeyOIDCService = new ZenKeyOIDCService(
      clientId,
      clientSecret,
      redirectUri
    );
  }

  async authenticate(req) {
    try {
      const { code, error, state } = req.query;
      const sessionService = new SessionService();

      if (error) {
        throw new Error(error);
      }

      // use a cached MCCMNC if needed
      const mccmnc = req.query.mccmnc || sessionService.getMCCMNC(req.session);

      // build our OpenID client
      let openIDClient = null;
      if (mccmnc) {
        openIDClient = await this.zenkeyOIDCService.discoverOIDCClient(mccmnc);
      }

      // // we have an auth code, we can now get a token
      if (openIDClient && code && state) {
        const tokenSet = await this.zenkeyOIDCService.requestToken(
          req,
          openIDClient
        );

        // make a userinfo request to get user information
        const userInfo = await openIDClient.userinfo(tokenSet);

        // call the verify callback to look up the user in our local database
        this.verify(tokenSet, userInfo, verified.bind(this));
        return;
      }

      // now that we have discovered the OIDC endpoint information, we can redirect
      // to ask the user to authorize and get an auth code
      if (openIDClient && state) {
        const redirectUrl = this.zenkeyOIDCService.requestAuthCodeRedirect(
          req,
          openIDClient,
          {
            context: null, // no context message for login
            acrValues: null, // use default acrValues for login
            scope: this.scope
          }
        );
        this.redirect(redirectUrl);
        return;
      }

      // discover the carrier information so we can get OIDC endpoint information
      const redirectUrl = this.zenkeyOIDCService.carrierDiscoveryRedirect(req);
      this.redirect(redirectUrl);
    } catch (e) {
      this.error(e);
    }
  }
}

module.exports = ZenKeyStrategy;
