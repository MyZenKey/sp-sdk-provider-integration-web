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
require __DIR__.'/vendor/autoload.php';
require __DIR__.'/utilities.php';
require __DIR__.'/ZenKeyOIDCService.php';
require __DIR__.'/SessionService.php';

// protect against iFraming
header('X-Frame-Options: DENY');
// force HTTPS
header("Strict-Transport-Security:max-age=5184000");

$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

// constants from environment variables
$BASE_URL = filter_input_fix(INPUT_ENV, 'BASE_URL', FILTER_SANITIZE_URL);
$CLIENT_ID = filter_input_fix(INPUT_ENV, 'CLIENT_ID', FILTER_SANITIZE_STRING);
$CLIENT_SECRET = filter_input_fix(INPUT_ENV, 'CLIENT_SECRET', FILTER_SANITIZE_STRING);
$CARRIER_DISCOVERY_URL = filter_input_fix(INPUT_ENV, 'CARRIER_DISCOVERY_URL', FILTER_SANITIZE_URL);
$OIDC_PROVIDER_CONFIG_URL = filter_input_fix(INPUT_ENV, 'OIDC_PROVIDER_CONFIG_URL', FILTER_SANITIZE_URL);

$REDIRECT_URI = "{$BASE_URL}";

$sessionService = new SessionService();
$zenkeyOIDCService = new ZenKeyOIDCService($CLIENT_ID, $CLIENT_SECRET, $REDIRECT_URI, $OIDC_PROVIDER_CONFIG_URL, $CARRIER_DISCOVERY_URL, $sessionService);

// Carrier Discovery:
// To learn the mccmnc, we send the user to the ZenKey discovery endpoint.
// This endpoint will redirect the user back to our app, giving us the mccmnc that identifies the user’s carrier.
$carrierDiscoveryUrl = $zenkeyOIDCService->carrierDiscoveryRedirect();
header("Location: {$carrierDiscoveryUrl}");
