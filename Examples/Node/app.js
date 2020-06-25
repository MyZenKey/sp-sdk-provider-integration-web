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
const cookieParser = require("cookie-parser");
const cookieSession = require("cookie-session");
const createError = require("http-errors");
const express = require("express");
const helmet = require("helmet");
const logger = require("morgan");
const passport = require("passport");
const path = require("path");
const validator = require("validator");

const utilities = require("./utilities");
const ZenKeyStrategy = require("./ZenKeyStrategy");
const ZenKeyOIDCService = require("./ZenKeyOIDCService");
const AuthorizeFlowHandler = require("./AuthorizeFlowHandler");
const SessionService = require("./SessionService");

function app() {
  const clientId = process.env.CLIENT_ID;
  const clientSecret = process.env.CLIENT_SECRET;
  const redirectUri = `${process.env.BASE_URL}/auth/cb`;
  const scope = "name email phone postal_code openid";

  passport.use(
    "zenkey",
    new ZenKeyStrategy(
      clientId,
      clientSecret,
      redirectUri,
      scope,
      // verify callback to look up the user based on the credentials
      async (tokenSet, userInfo, done) => {
        // this is where a real app might look up the user in the database using the "sub" value
        // we could also create a new user or show a registration form
        // the userInfo object contains values like sub, name, and email (depending on which scopes were requested)
        // these values can be saved for the user or used to auto-populate a registration form

        // store user info in the session
        const user = userInfo;
        return done(null, user);
      }
    )
  );

  passport.serializeUser(utilities.serializeUser);
  passport.deserializeUser(utilities.deserializeUser);

  const expressApp = express();
  // set security headers
  expressApp.use(helmet());

  // set up view engine
  expressApp.set("views", path.join(__dirname, "views"));
  expressApp.set("view engine", "pug");

  // log HTTP requests
  expressApp.use(logger("dev"));
  // parse JSON in the request body
  expressApp.use(express.json());
  // parse URL encoded forms in the request body
  expressApp.use(express.urlencoded({ extended: false }));
  // parse cookies in the request
  expressApp.use(cookieParser());
  expressApp.use(
    // cookieSession stores session data in cookies that the user can read
    // this is not very secure, but is sufficient for this example code
    // in a production application you should store session data in a server-side session store (like Redis or a database)
    cookieSession({
      name: "session",
      keys: [process.env.SECRET_KEY_BASE],
      maxAge: 24 * 60 * 60 * 1000 // 24 hours
    })
  );

  // route for static files
  expressApp.use(express.static(path.join(__dirname, "public")));

  // set up passport auth
  expressApp.use(passport.initialize());
  expressApp.use(passport.session());

  // log out if there is no user
  expressApp.use(utilities.deserializeUserMiddleware);
  // set a local user value
  expressApp.use(utilities.userMiddleware);

  // route for homepage
  expressApp.get("/", (req, res) => {
    let { message } = req.query;
    message = utilities.sanitizeString(message);
    message = message ? validator.escape(message) : null;
    res.render("home", { message });
  });
  // route to start ZenKey authentication
  const zenkeyAuthOptions = {
    successRedirect: "/",
    failureRedirect: "/auth"
  };
  expressApp.get("/auth", passport.authenticate("zenkey", zenkeyAuthOptions));

  // route for ZenKey authentication callback
  expressApp.get(
    "/auth/cb",
    // if not logged in, authenticate with Passport
    (req, res, next) => {
      if (req.user) {
        return next();
      }
      return passport.authenticate("zenkey", zenkeyAuthOptions)(req, res, next);
    },
    // if an authorization is in progress, continue the auth flow
    (req, res, next) => {
      if (!AuthorizeFlowHandler.authorizationInProgress(req.session)) {
        return next();
      }

      // there is an authorization in progress: call our auth callback with the relevant authorization details
      const zenkeyOIDCService = new ZenKeyOIDCService(
        clientId,
        clientSecret,
        redirectUri
      );
      const callback = AuthorizeFlowHandler.router(
        AuthorizeFlowHandler.getAuthorizationDetails(req.session),
        zenkeyOIDCService
      );
      return callback(req, res, next);
    },
    // nothing else to do: return to the homepage
    (req, res) => {
      return res.redirect("/");
    }
  );

  // route for logging out
  expressApp.get("/logout", function logout(req, res) {
    const sessionService = new SessionService();
    sessionService.clear(req.session);
    AuthorizeFlowHandler.deleteAuthorizationDetails(req.session);
    req.logout();
    res.redirect("/");
  });
  // route for authorize flow (authorize a transaction request for logged in user)
  expressApp.post("/authorize-transaction", (req, res) => {
    if (!req.user) {
      res.redirect("/");
      return;
    }

    let { amount } = req.body;
    // only allow numeric values for amount
    amount = amount ? validator.toFloat(amount) : null;
    if (Number.isNaN(amount)) {
      amount = "20.00";
    } else {
      amount = amount.toFixed(2);
    }
    const recipient = "John Doe";

    // save the authorization information in the session so we can use it after the user is authenticated
    AuthorizeFlowHandler.setAuthorizationDetails(
      req.session,
      "transaction",
      `Send $${amount} to ${recipient}`,
      {
        amount,
        recipient
      }
    );

    // begin the auth flow
    res.redirect("/auth/cb");
  });

  // catch 404 and forward to error handler
  expressApp.use(function catch404(req, res, next) {
    next(createError(404));
  });

  // error handler
  expressApp.use(function errorHandler(err, req, res) {
    // set locals, only providing error in development
    res.locals.message = err.message;
    res.locals.error = req.app.get("env") === "development" ? err : {};

    // render the error page
    res.status(err.status || 500);
    res.render("error");
  });

  return expressApp;
}

module.exports = app;
