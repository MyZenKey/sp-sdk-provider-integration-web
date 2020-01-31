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
const { encode } = require("base64url");
const { randomBytes } = require("crypto");

// Passport helper function to serialize the user for storage in the session
function serializeUser(user, done) {
  if (user) {
    return done(null, JSON.stringify(user));
  }

  return done(new Error("Cannot serialize user."));
}

// Passport helper function to deserialize the user from the ID in the session
async function deserializeUser(id, done) {
  try {
    const user = JSON.parse(id);
    return done(null, user);
  } catch (e) {
    return done(new Error("Cannot deserialize user."));
  }
}

// middleware to log out if there is no user
function deserializeUserMiddleware(err, req, res, next) {
  if (err && err.message === "Cannot deserialize user.") {
    req.logout();
    res.redirect("/");
  } else {
    next();
  }
}

// return a random value to be used as the OAuth 2 state param
function randomState(bytes = 32) {
  return encode(randomBytes(bytes));
}

// middleware to set a local user value
function userMiddleware({ user }, { locals }, next) {
  // eslint-disable-next-line no-param-reassign
  locals.user = user;
  next();
}

// Normalize a port into a number, string, or false.
function normalizePort(val) {
  const port = parseInt(val, 10);

  if (Number.isNaN(port)) {
    // named pipe
    return val;
  }

  if (port >= 0) {
    // port number
    return port;
  }

  return false;
}

module.exports = {
  serializeUser,
  deserializeUser,
  deserializeUserMiddleware,
  randomState,
  userMiddleware,
  normalizePort
};
