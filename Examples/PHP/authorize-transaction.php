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
require __DIR__.'/SessionService.php';
require __DIR__.'/AuthorizationFlowHandler.php';

// protect against iFraming
header('X-Frame-Options: DENY');
// force HTTPS
header("Strict-Transport-Security:max-age=5184000");

$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

/**
 * Handle the transaction form, save the transaction details in the
 * session and kick off the auth flow.
 */
$amount = filter_input_fix(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?? '20.00';
$recipient = 'John Doe';
$context = "Send \${$amount} to {$recipient}";

$sessionService = new SessionService();
$authFlowHandler = new AuthorizationFlowHandler($sessionService);

$authFlowHandler->setAuthorizationDetails('transaction', $context, [
    'amount' => $amount,
    'recipient' => $recipient,
]);

header('Location: /auth.php');
