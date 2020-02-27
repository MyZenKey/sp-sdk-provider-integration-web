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
    this.mccmncCacheKey = "zenkey_mccmnc";
  }

  clear(session) {
    try {
      delete session[this.stateCacheKey];
    } catch (e) {} // eslint-disable-line no-empty
    try {
      delete session[this.mccmncCacheKey];
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

  setMCCMNC(session, mccmnc) {
    session[this.mccmncCacheKey] = mccmnc;
  }

  getMCCMNC(session) {
    if (!(this.mccmncCacheKey in session)) {
      return null;
    }
    return session[this.mccmncCacheKey];
  }
}

module.exports = SessionService;
