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
