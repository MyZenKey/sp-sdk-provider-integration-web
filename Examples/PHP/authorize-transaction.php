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
require __DIR__.'/SessionService.php';
require __DIR__.'/AuthorizationFlowHandler.php';

$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->load();

/**
 * Handle the transaction form, save the transaction details in the
 * session and kick off the auth flow.
 */
$amount = (isset($_POST['amount']) ? $_POST['amount'] : '20.00');
$recipient = 'John Doe';
$context = "Send \${$amount} to {$recipient}";

$sessionService = new SessionService();
$authFlowHandler = new AuthorizationFlowHandler($sessionService);

$authFlowHandler->setAuthorizationDetails('transaction', $context, [
    'amount' => $amount,
    'recipient' => $recipient,
]);

header('Location: /auth.php');
