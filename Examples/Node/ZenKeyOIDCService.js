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
const openIdClient = require("openid-client");
const constants = require("./constants");
const utilities = require("./utilities");
const SessionService = require("./SessionService");

const { Issuer } = openIdClient;

/**
 * This class deals with the ZenKey OAuth2/OpenID Connect flow
 *
 * the auth flow proceeds in this order:
 * 1. carrierDiscoveryRedirect()
 *      In order to discover the OIDC provider information, we need an MCCMNC.
 *      To get one, we redirect the user to carrier discovery where they select their carrier and authorize
 *      their browser.
 * 2. discoverOIDCClient()
 *      The carrier discovery screen redirects back to our app with an MCCMNC.
 *      We can use this MCCMNC to make a call to the OIDC discovery endpoint to get OIDC issuer information
 *      for the user's carrier (Verizon, AT&T, etc)
 * 3. requestAuthCodeRedirect()
 *      Now that we have the OIDC issuer endpoint info, we need an auth code. To get one, we redirect
 *      the user to the auth endpoint. They will be prompted to authorize this app.
 * 4. requestToken()
 *      The auth screen redirects back to our app with an auth code.
 *      We can exchange this code for an access token and ID token.
 *      Once we have these tokens, we know the user is authenticated and we can make requests
 *      to the Userinfo endpoint.
 */
class ZenKeyOIDCService {
  constructor(clientId, clientSecret, redirectUri) {
    this.redirectUri = redirectUri;
    this.clientId = clientId;
    this.clientSecret = clientSecret;
    this.sessionService = new SessionService();
  }

  getAuthCodeRequestURL(
    openIDClient,
    loginHintToken,
    state,
    nonce,
    urlOptions = {}
  ) {
    const { context, acrValues, scope } = urlOptions;
    const options = {
      login_hint_token: loginHintToken,
      redirect_uri: this.redirectUri,
      response_type: "code",
      scope: scope || "openid", // default to the openid scope only
      state,
      nonce
    };
    if (context) {
      options.context = encodeURIComponent(context);
    }
    if (acrValues) {
      options.acr_values = acrValues;
    }
    return openIDClient.authorizationUrl(options);
  }

  // Carrier Discovery:
  // To learn the mccmnc, we send the user to the ZenKey discovery endpoint.
  // This endpoint will redirect the user back to our app, giving us the mccmnc that identifies the user's carrier.
  carrierDiscoveryRedirect(req) {
    // save a random state value to prevent request forgeries
    const newState = utilities.randomState();
    this.sessionService.setState(req.session, newState);

    // send the user to the carrier discovery endpoint
    const carrierDiscoveryUrl =
      `${constants.carrierDiscoveryEndpoint}` +
      `?client_id=${encodeURIComponent(this.clientId)}` +
      `&redirect_uri=${encodeURIComponent(this.redirectUri)}` +
      `&state=${encodeURIComponent(newState)}`;
    return carrierDiscoveryUrl;
  }

  // Request a token
  // The authentication endpoint has redirected back to our app with an auth code. Now we can exchange the auth code for a token.
  // Once we have a token, we can make a userinfo request to get user profile information.
  requestToken(req, openIDClient) {
    // ingest the request parameters returned from ZenKey
    const tokenRequestParams = openIDClient.callbackParams(req);
    const checks = {
      response_type: "code",
      // the open id client will handle nonce and state checks
      // to prevent CSRF attacks
      state: this.sessionService.getState(req.session),
      nonce: this.sessionService.getNonce(req.session)
    };
    // make a token request using the auth code
    // this validates the ID token using checks and automatically validates the JWT and claims
    // returns a promise
    const tokenSetPromise = openIDClient.authorizationCallback(
      this.redirectUri,
      tokenRequestParams,
      checks
    );

    // delete the state stored in the session
    this.sessionService.clear(req.session);

    return tokenSetPromise;
  }

  // Request an auth code
  // The carrier discovery endpoint has redirected back to our app with the mccmnc.
  // Now we can start the authorize flow by requesting an auth code.
  // Send the user to the ZenKey authorization endpoint. After authorization, this endpoint will redirect
  // back to our app with an auth code.
  requestAuthCodeRedirect(req, openIDClient, urlOptions = {}) {
    const { login_hint_token: loginHintToken, mccmnc, state } = req.query;

    // prevent request forgeries by checking that the incoming state matches
    if (state !== this.sessionService.getState(req.session)) {
      throw new Error("state mismatch after carrier discovery");
    }

    // persist the mccmnc and a state value and a nonce value in the session
    // for the auth redirect
    const newState = utilities.randomState();
    const newNonce = utilities.randomState();
    this.sessionService.setState(req.session, newState);
    this.sessionService.setNonce(req.session, newNonce);
    this.sessionService.setMCCMNC(req.session, mccmnc);

    // send user to the ZenKey authorization endpoint to request an auth code
    const authorizationUrl = this.getAuthCodeRequestURL(
      openIDClient,
      loginHintToken,
      newState,
      newNonce,
      urlOptions
    );
    return authorizationUrl;
  }

  async discoverOIDCClient(mccmnc) {
    Issuer.defaultHttpOptions = { timeout: 20000 };
    const issuer = await Issuer.discover(
      `${constants.oidcProviderConfigEndpoint}?client_id=${this.clientId}&mccmnc=${mccmnc}`
    );

    return new issuer.Client({
      client_id: this.clientId,
      client_secret: this.clientSecret,
      redirect_uris: [this.redirectUri],
      response_types: ["code"]
    });
  }
}

module.exports = ZenKeyOIDCService;
