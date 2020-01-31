<?php
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
session_start();

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/utilities.php';

$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

// constants from environment variables
$BASE_URL = $_SERVER['BASE_URL'];
$CLIENT_ID = $_SERVER['CLIENT_ID'];
$CARRIER_DISCOVERY_URL = $_SERVER['CARRIER_DISCOVERY_URL'];

// Carrier Discovery:
// To learn the mccmnc, we send the user to the ZenKey discovery endpoint.
// This endpoint will redirect the user back to our app, giving us the mccmnc that identifies the user’s carrier.

// save a random state value to prevent request forgeries
$zenkeySession = new stdClass();
$zenkeySession->state = random();
$_SESSION['zenkey'] = json_encode($zenkeySession);

// send the user to the carrier discovery endpoint
$REDIRECT_URI = "{$BASE_URL}/auth/cb.php";
header("Location: {$CARRIER_DISCOVERY_URL}?client_id={$CLIENT_ID}&redirect_uri={$REDIRECT_URI}&state={$zenkeySession->state}");
