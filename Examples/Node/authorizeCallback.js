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
const utilities = require("./utilities");

const SessionService = require("./SessionService");

// this function uses ZenKey to authorize a user and then complete a transaction
// it demonstrates how we can authorize a user separate from login
// when an authorization is in progress, this function will be called iteratively as ZenKey redirects back to the app
// until authorization is complete
const authorizeCallback = async (
  req,
  res,
  next,
  zenkeyOIDCService,
  successCallback,
  authOptions
) => {
  let { code, error, state, mccmnc } = req.query;
  // sanitize input
  code = utilities.sanitizeString(code);
  error = utilities.sanitizeString(error);
  state = utilities.sanitizeString(state);
  mccmnc = utilities.sanitizeString(mccmnc);

  const sessionService = new SessionService();

  if (error) {
    throw new Error(error);
  }

  // use a cached MCCMNC if needed
  mccmnc = mccmnc || sessionService.getMCCMNC(req.session);

  // build our OpenID client
  let openIDClient = null;
  if (mccmnc) {
    openIDClient = await zenkeyOIDCService.discoverOIDCClient(mccmnc);
  }

  // we have an auth code, we can now get a token
  if (openIDClient && code && state) {
    const tokenSet = await zenkeyOIDCService.requestToken(req, openIDClient);

    successCallback(req, res, next, openIDClient, tokenSet);
    return;
  }

  // now that we have discovered the OIDC endpoint information, we can redirect
  // to ask the user to authorize and get an auth code
  if (openIDClient && state) {
    const redirectUrl = zenkeyOIDCService.requestAuthCodeRedirect(
      req,
      openIDClient,
      authOptions
    );
    res.redirect(redirectUrl);
    return;
  }

  // discover the carrier information so we can get OIDC endpoint information
  const redirectUrl = zenkeyOIDCService.carrierDiscoveryRedirect(req);
  res.redirect(redirectUrl);
};

module.exports = authorizeCallback;
