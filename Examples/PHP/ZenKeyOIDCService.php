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

use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\GenericProvider;

/**
 * This class deals with the ZenKey OAuth2/OpenID Connect flow
 * the auth flow proceeds in this order:
 * 1. carrierDiscoveryRedirect()
 *     In order to discover the OIDC provider information, we need an MCCMNC.
 *     To get one, we redirect the user to carrier discovery where they select
 *     their carrier and authorize their browser.
 * 2. discoverOIDCClient()
 *     The carrier discovery screen redirects back to our app with an MCCMNC.
 *     We can use this MCCMNC to make a call to the OIDC discovery endpoint to
 *     get OIDC issuer information for the user's carrier (Verizon, AT&T, etc)
 * 3. requestAuthCodeRedirect()
 *     Now that we have the OIDC issuer endpoint info, we need an auth code.
 *     To get one, we redirect the user to the auth endpoint. They will be
 *     prompted to authorize this app.
 * 4. requestToken()
 *     The auth screen redirects back to our app with an auth code.
 *     We can exchange this code for an access token and ID token.
 *     Once we have these tokens, we know the user is authenticated and we can make requests
 *     to the Userinfo endpoint.
 */
class ZenKeyOIDCService
{
    public $clientId;
    public $clientSecret;
    public $redirectUri;
    public $oidcProviderConfigUrl;
    public $carrierDiscoveryUrl;
    public $sessionService;

    /**
     * @param string         $clientId
     * @param string         $clientSecret
     * @param string         $redirectUri
     * @param string         $oidcProviderConfigUrl
     * @param string         $carrierDiscoveryUrl
     * @param SessionService $sessionService
     */
    public function __construct($clientId, $clientSecret, $redirectUri, $oidcProviderConfigUrl, $carrierDiscoveryUrl, $sessionService)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->oidcProviderConfigUrl = $oidcProviderConfigUrl;
        $this->carrierDiscoveryUrl = $carrierDiscoveryUrl;
        $this->sessionService = $sessionService;
    }

    /**
     * Carrier Discovery:To learn the mccmnc, we send the user to the ZenKey discovery endpoint.
     * This endpoint will redirect the user back to our app, giving usthe mccmnc that identifies the user's carrier.
     *
     * @return string
     */
    public function carrierDiscoveryRedirect()
    {
        // save a random state value to prevent request forgeries
        $newState = random();
        $this->sessionService->setState($newState);

        // return the user to the carrier discovery endpoint
        return "{$this->carrierDiscoveryUrl}?client_id={$this->clientId}&redirect_uri={$this->redirectUri}&state={$newState}";
    }

    /**
     * Make an HTTP request to the ZenKey discovery issuer endpoint to access
     * the carrier’s OIDC configuration then build an OIDC client with the configuration.
     *
     * @param string $mccmnc
     *
     * @return GenericProvider
     */
    public function discoverOIDCProvider($mccmnc)
    {
        // make an HTTP request to the endpoint
        $configUrl = "{$this->oidcProviderConfigUrl}?client_id={$this->clientId}&mccmnc={$mccmnc}";
        $configResponse = file_get_contents($configUrl);
        $openIdConfiguration = json_decode($configResponse);

        $oidcProvider = new GenericProvider([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'redirectUri' => $this->redirectUri,
            'urlAuthorize' => $openIdConfiguration->authorization_endpoint,
            'urlAccessToken' => $openIdConfiguration->token_endpoint,
            'urlResourceOwnerDetails' => $openIdConfiguration->userinfo_endpoint,
        ]);

        // configure the OIDC client to use basic auth with an Authorization header
        // instead of sending the client ID and secret in the body during token exchange
        $optionProvider = new HttpBasicAuthOptionProvider();
        $oidcProvider->setOptionProvider($optionProvider);

        return $oidcProvider;
    }

    /**
     * Get the user an auth code
     * now that we have discovered the OIDC endpoint information, we can redirect
     * to ask the user to authorize and get an auth code.
     *
     * This will build an auth code URL and save the necessary state information
     *
     * @param GenericProvider $oidcProvider
     * @param string          $loginHintToken
     * @param string          $state
     * @param string          $mccmnc
     * @param array           $urlOptions
     *
     * @return string
     */
    public function getAuthCodeRedirectUrl($oidcProvider, $loginHintToken, $state, $mccmnc, $urlOptions)
    {
        // prevent request forgeries by checking that the incoming state matches
        if ($state !== $this->sessionService->getState()) {
            throw new Exception('state mismatch after carrier discovery');
        }

        $authorizeParams = [
            'scope' => 'openid', // default to just the basic openid scope
            'login_hint_token' => $loginHintToken,
        ];

        // if extra params like "context" are needed, pass them in
        if (isset($urlOptions['scope'])) {
            $authorizeParams['scope'] = $urlOptions['scope'];
        }
        if (isset($urlOptions['context'])) {
            $authorizeParams['context'] = $urlOptions['context'];
        }
        if (isset($urlOptions['acr_values'])) {
            $authorizeParams['acr_values'] = $urlOptions['acr_values'];
        }

        // generate the URL for the ZenKey authorization endpoint to request an authorization code
        $authorizationUrl = $oidcProvider->getAuthorizationUrl($authorizeParams);

        // persist the mccmnc and a state value in the session
        // for the auth redirect
        $this->sessionService->setState($oidcProvider->getState());
        $this->sessionService->setMCCMNC($mccmnc);
        // TODO: can we remove this without breaking Verizon?
        // remove client_id param because it may break things
        // $authorizationUrl = preg_replace("/client_id=.+?(&|$)/", '', $authorizationUrl);
        // remove approval_prompt because it's not explicitly allowed (should be `prompt`)
        // $authorizationUrl = preg_replace("/approval_prompt=.+?(&|$)/", '', $authorizationUrl);
        return $authorizationUrl;
    }

    /**
     * We have an auth code, we can now exchange it for a token.
     *
     * @param GenericProvider $oidcProvider
     * @param string          $code
     * @param string          $state
     *
     * @return League\OAuth2\Client\Token\AccessTokenInterface
     */
    public function requestToken($oidcProvider, $code, $state)
    {
        // prevent request forgeries by checking that the incoming state matches
        if ($state !== $this->sessionService->getState()) {
            throw new Exception('state mismatch');
        }

        // exchange the auth code for a token
        $tokens = $oidcProvider->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        // clear the state
        $this->sessionService->clearState();

        return $tokens;
    }

    /**
     * Make an API call to the carrier to get user info, using the token we received.
     *
     * @param GenericProvider                                 $oidcProvider
     * @param League\OAuth2\Client\Token\AccessTokenInterface $accessToken  - the access token array returned from requestToken
     *
     * @return League\OAuth2\Client\Provider\ResourceOwnerInterface
     */
    public function getUserinfo($oidcProvider, $accessToken)
    {
        return $oidcProvider->getResourceOwner($accessToken);
    }
}
