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

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../utilities.php';

use League\OAuth2\Client\Provider\GenericProvider;

$dotenv = Dotenv\Dotenv::create(__DIR__.'/..');
$dotenv->load();

// constants from environment variables
$BASE_URL = $_SERVER['BASE_URL'];
$CLIENT_ID = $_SERVER['CLIENT_ID'];
$CLIENT_SECRET = $_SERVER['CLIENT_SECRET'];
$CARRIER_DISCOVERY_URL = $_SERVER['CARRIER_DISCOVERY_URL'];
$OIDC_PROVIDER_CONFIG_URL = $_SERVER['OIDC_PROVIDER_CONFIG_URL'];

$REDIRECT_URI = "{$BASE_URL}/auth/cb.php";

$SCOPE = 'openid profile';

try {
    if (isset($_GET['error'])) {
        throw new Exception($_GET['error']);
    }

    // Request an auth code
    // The carrier discovery endpoint has redirected back to our app with the mccmnc.
    // Now we can start the authorize flow by requesting an auth code.
    // Send the user to the ZenKey authorization endpoint. After authorization, this endpoint will redirect back to our app with an auth code.
    if (isset($_GET['mccmnc'], $_GET['state'])) {
        // prevent request forgeries by checking that the incoming state matches
        checkState($_GET['state']);

        // build our OpenID client
        $oidcProvider = discoverOIDCProvider($CLIENT_ID, $CLIENT_SECRET, $REDIRECT_URI, $_GET['mccmnc']);

        // persist the mccmnc and a state value in the session
        // for the auth redirect
        $zenkeySession = new stdClass();
        $zenkeySession->mccmnc = $_GET['mccmnc'];
        $zenkeySession->state = $oidcProvider->getState();
        $_SESSION['zenkey'] = json_encode($zenkeySession);

        // send user to the ZenKey authorization endpoint to request an authorization code
        $authorizationUrl = "{$oidcProvider->getAuthorizationUrl()}&login_hint_token={$_GET['login_hint_token']}&scope={$scope}";
        header("Location: {$authorizationUrl}");

        return;
    }

    // Request a token
    // The authentication endpoint has redirected back to our app with an auth code. Now we can exchange the auth code for a token.
    // Once we have a token, we can make a userinfo request to get user profile information.
    if (isset($_GET['code'], $_GET['state'])) {
        // prevent request forgeries by checking that the incoming state matches
        checkState($_GET['state']);

        // get the mccmnc from the session
        $zenkeySession = json_decode($_SESSION['zenkey']);
        $mccmnc = $zenkeySession->mccmnc;

        // build our OpenID client
        $oidcProvider = discoverOIDCProvider($CLIENT_ID, $CLIENT_SECRET, $REDIRECT_URI, $mccmnc);

        // exchange the auth code for a token
        $accessToken = $oidcProvider->getAccessToken('authorization_code', [
            'code' => $_GET['code'],
        ]);

        // make a userinfo request to get user information
        $userInfo = $oidcProvider->getResourceOwner($accessToken);

        // this is where a real app might look up the user in the database using the "sub" value
        // we could also create a new user or show a registration form
        // the $userInfo object contains values like sub, name, and email (depending on which scopes were requested)
        // these values can be saved for the user or used to auto-populate a registration form

        // save the user info in the session
        $_SESSION['signedIn'] = true;
        $_SESSION['name'] = $userInfo->toArray()['name'];

        // now that we are logged in, return to the homepage
        header('Location: /');

        return;
    }

    // If we have no mccmnc, begin the carrier discovery process
    header('Location: /auth.php');
} catch (Exception $e) {
    echo $e->getMessage();
}

/**
 * prevent request forgeries by checking that the incoming state matches.
 *
 * @param mixed $state
 */
function checkState($state)
{
    $zenkeySession = json_decode($_SESSION['zenkey']);

    if ($state !== $zenkeySession->state) {
        throw new Exception('state mismatch');
    }
}

/**
 * Make an HTTP request to discover the OIDC configuration using its published .well-known endpoint
 * and return the provider metadata.
 *
 * @param mixed $clientId
 * @param mixed $clientSecret
 * @param mixed $redirectUri
 * @param mixed $mccmnc
 */
function discoverOIDCProvider($clientId, $clientSecret, $redirectUri, $mccmnc)
{
    global $OIDC_PROVIDER_CONFIG_URL, $CLIENT_ID;

    // make an HTTP request to the endpoint
    $configUrl = "{$OIDC_PROVIDER_CONFIG_URL}?client_id={$CLIENT_ID}&mccmnc={$mccmnc}";
    $configResponse = file_get_contents($configUrl);
    $openIdConfiguration = json_decode($configResponse);

    $oidcProvider = new GenericProvider([
        'clientId' => $clientId,
        'clientSecret' => $clientSecret,
        'redirectUri' => $redirectUri,
        'urlAuthorize' => $openIdConfiguration->authorization_endpoint,
        'urlAccessToken' => $openIdConfiguration->token_endpoint,
        'urlResourceOwnerDetails' => $openIdConfiguration->userinfo_endpoint,
    ]);

    return $oidcProvider;
}
