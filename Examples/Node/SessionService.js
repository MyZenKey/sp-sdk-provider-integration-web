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

/* eslint no-param-reassign: ["error", { "props": false }] */

class SessionService {
  constructor() {
    this.stateCacheKey = "zenkey_state";
    this.nonceCacheKey = "zenkey_nonce";
    this.codeVerifierCacheKey = "zenkey_code_verifier";
    this.mccmncCacheKey = "zenkey_mccmnc";
    this.authorizationCacheKey = "zenkey_authorize";
  }

  clear(session) {
    try {
      delete session[this.stateCacheKey];
    } catch (e) {} // eslint-disable-line no-empty
    try {
      delete session[this.nonceCacheKey];
    } catch (e) {} // eslint-disable-line no-empty
    try {
      delete session[this.mccmncCacheKey];
    } catch (e) {} // eslint-disable-line no-empty
    try {
      delete session[this.codeVerifierCacheKey];
    } catch (e) {} // eslint-disable-line no-empty
  }

  setState(session, state) {
    session[this.stateCacheKey] = state;
  }

  getState(session) {
    if (!(this.stateCacheKey in session)) {
      return null;
    }
    return session[this.stateCacheKey];
  }

  setNonce(session, nonce) {
    session[this.nonceCacheKey] = nonce;
  }

  getNonce(session) {
    if (!(this.nonceCacheKey in session)) {
      return null;
    }
    return session[this.nonceCacheKey];
  }

  setCodeVerifier(session, codeVerifier) {
    session[this.codeVerifierCacheKey] = codeVerifier;
  }

  getCodeVerifier(session) {
    if (!(this.codeVerifierCacheKey in session)) {
      return null;
    }
    return session[this.codeVerifierCacheKey];
  }

  setMCCMNC(session, mccmnc) {
    session[this.mccmncCacheKey] = mccmnc;
  }

  getMCCMNC(session) {
    if (!(this.mccmncCacheKey in session)) {
      return null;
    }
    return session[this.mccmncCacheKey];
  }

  deleteAuthorizationDetails(session) {
    try {
      delete session[this.authorizationCacheKey];
    } catch (e) {} // eslint-disable-line no-empty
  }

  setAuthorizationDetails(session, type, context, options = {}) {
    const value = {
      type,
      context,
      options
    };
    session[this.authorizationCacheKey] = value;
  }

  getAuthorizationDetails(session) {
    if (!(this.authorizationCacheKey in session)) {
      return null;
    }
    return session[this.authorizationCacheKey];
  }
}

module.exports = SessionService;
