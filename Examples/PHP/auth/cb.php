<?php
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
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../utilities.php';
require __DIR__.'/../ZenKeyOIDCService.php';
require __DIR__.'/../SessionService.php';
require __DIR__.'/../AuthorizationFlowHandler.php';

$dotenv = Dotenv\Dotenv::create(__DIR__.'/..');
$dotenv->load();

// constants from environment variables
$BASE_URL = filter_input_fix(INPUT_ENV, 'BASE_URL', FILTER_SANITIZE_URL);
$CLIENT_ID = filter_input_fix(INPUT_ENV, 'CLIENT_ID', FILTER_SANITIZE_STRING);
$CLIENT_SECRET = filter_input_fix(INPUT_ENV, 'CLIENT_SECRET', FILTER_SANITIZE_STRING);
$CARRIER_DISCOVERY_URL = filter_input_fix(INPUT_ENV, 'CARRIER_DISCOVERY_URL', FILTER_SANITIZE_URL);
$OIDC_PROVIDER_CONFIG_URL = filter_input_fix(INPUT_ENV, 'OIDC_PROVIDER_CONFIG_URL', FILTER_SANITIZE_URL);

$REDIRECT_URI = "{$BASE_URL}/auth/cb.php";

$SCOPE = 'openid name email phone postal_code';

$sessionService = new SessionService();
$zenkeyOIDCService = new ZenKeyOIDCService($CLIENT_ID, $CLIENT_SECRET, $REDIRECT_URI, $OIDC_PROVIDER_CONFIG_URL, $CARRIER_DISCOVERY_URL, $sessionService);
$authFlowHandler = new AuthorizationFlowHandler($sessionService);

// protect against iFraming
header('X-Frame-Options: DENY');
// force HTTPS
header("Strict-Transport-Security:max-age=5184000");

// use a cached MCCMNC if needed
$mccmnc = filter_input_fix(INPUT_GET, 'mccmnc', FILTER_SANITIZE_NUMBER_INT) ?? $sessionService->getMCCMNC();
$error = filter_input_fix(INPUT_GET, 'error', FILTER_SANITIZE_STRING);
$state = filter_input_fix(INPUT_GET, 'state', FILTER_SANITIZE_STRING);
$code = filter_input_fix(INPUT_GET, 'code', FILTER_SANITIZE_STRING);
$loginHintToken = filter_input_fix(INPUT_GET, 'login_hint_token', FILTER_SANITIZE_STRING);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <title>ZenKey-DemoApp-PHP</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
  <link rel="stylesheet" href="/stylesheets/zk-btn.css">
  <link rel="stylesheet" href="/stylesheets/style.css">
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top"><a class="navbar-brand" href="/">ZenKey-DemoApp-PHP</a>
  <ul class="navbar-nav ml-auto">
    <li class="nav-item">
       <a class="nav-link" href="/logout.php">Sign Out</a>
      ?>
    </li>
  </ul>
</nav>
<main class="container">

  <h1>Home</h1>

  <p>Welcome back.</p>



</main>
</body>
</html>
