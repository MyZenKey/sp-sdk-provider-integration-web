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
