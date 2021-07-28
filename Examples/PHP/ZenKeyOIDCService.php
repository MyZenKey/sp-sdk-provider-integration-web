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
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/OIDCProvider.php';

use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\ValidationData;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use CoderCat\JWKToPEM\JWKConverter;


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
   * @param string $clientId
   * @param string $clientSecret
   * @param string $redirectUri
   * @param string $oidcProviderConfigUrl
   * @param string $carrierDiscoveryUrl
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
    $query = urldecode(http_build_query(array(
      'client_id' => $this->clientId,
      'redirect_uri' => $this->redirectUri,
      'state' => $newState
    )));
    return "{$this->carrierDiscoveryUrl}?{$query}";
  }

  /**
   * Make an HTTP request to the ZenKey discovery issuer endpoint to access
   * the carrier’s OIDC configuration then build an OIDC client with the configuration.
   *
   * @param string $mccmnc
   *
   * @return OIDCProvider
   */
  public function discoverOIDCProvider($mccmnc)
  {
    // make an HTTP request to the endpoint
    $query = http_build_query(array(
      'client_id' => $this->clientId,
      'mccmnc' => $mccmnc
    ));
    $configUrl = "{$this->oidcProviderConfigUrl}?{$query}";
    $configResponse = curl_get_contents($configUrl);
    $openIdConfiguration = json_decode($configResponse);

    $oidcProvider = new OIDCProvider([
      'clientId' => $this->clientId,
      'clientSecret' => $this->clientSecret,
      'redirectUri' => $this->redirectUri,
      'urlAuthorize' => $openIdConfiguration->authorization_endpoint,
      'urlAccessToken' => $openIdConfiguration->token_endpoint,
      'urlResourceOwnerDetails' => $openIdConfiguration->userinfo_endpoint,
      'urlJWKs' => $openIdConfiguration->jwks_uri,
      'issuer' => $openIdConfiguration->issuer
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
   * @param string $loginHintToken
   * @param string $state
   * @param string $mccmnc
   * @param array $urlOptions
   *
   * @return string
   */
  public function getAuthCodeRedirectUrl($oidcProvider, $loginHintToken, $state, $mccmnc, $urlOptions)
  {
    // prevent request forgeries by checking that the incoming state matches
    if ($state !== $this->sessionService->getState()) {
      throw new Exception('state mismatch after carrier discovery');
    }

    // generate code verifier and code challenge for PKCE
    $codeVerifier = random(128);
    $codeChallengeMethod = 'S256';
    $codeChallenge = generateCodeVerifierHash($codeVerifier);

    $newNonce = random();
    $authorizeParams = [
      'scope' => 'openid', // default to just the basic openid scope
      'login_hint_token' => $loginHintToken,
      'nonce' => $newNonce,
      'code_challenge' => $codeChallenge,
      'code_challenge_method' => $codeChallengeMethod
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

    // persist the mccmnc, new nonce and a state value in the session
    // for the auth redirect
    $this->sessionService->setState($oidcProvider->getState());
    $this->sessionService->setNonce($newNonce);
    $this->sessionService->setMCCMNC($mccmnc);
    $this->sessionService->setCodeVerifier($codeVerifier);
    // TODO: can we remove this without breaking Verizon?
    // remove client_id param because it may break things
    // $authorizationUrl = preg_replace("/client_id=.+?(&|$)/", '', $authorizationUrl);
    // remove approval_prompt because it's not explicitly allowed (should be `prompt`)
    // $authorizationUrl = preg_replace("/approval_prompt=.+?(&|$)/", '', $authorizationUrl);
    return $authorizationUrl;
  }

  /**
   * find a matching key for this JWT from a list of JWKs
   *
   * @param array $keys - array of JWKs
   * @param Token $jwt
   * @return array - matching JWK associative array
   */
  private function getKeyForJWT($keys, $jwt)
  {
    $headerKid = $jwt->getHeader('kid');
    $headerAlg = $jwt->getHeader('alg');

    // find a matching key in the key list
    foreach ($keys as $key) {
      // look for RSA keys in the key list
      if ($key->kty === 'RSA') {
        // if the JWT doesn't specific a key ID (kid), use the first matching key
        // otherwise use the key with the matching kid
        if (!isset($headerKid) || $key->kid === $headerKid) {
          return $key;
        }
      } else {
        // look for keys in the key list with the same algorithm as our JWT
        // and the same key ID (kid) as our JWT
        if (isset($key->alg) && $key->alg === $headerAlg && $key->kid === $headerKid) {
          return $key;
        }
      }
    }
    if (isset($headerKid)) {
      throw new Exception('Unable to find a key for (algorithm, kid):' . $headerAlg . ', ' . $headerKid . ')');
    }

    throw new Exception('Unable to find a key for RSA');
  }

  /**
   * make an HTTP request to get the OIDC provider's JWKs from the jwks_uri
   *
   * @param string $jwksUri
   * @return array - JSON array object containing a list of JWKs
   */
  private function getProviderJWKs($jwksUri)
  {
    $jwks = json_decode(curl_get_contents($jwksUri));
    if ($jwks === NULL) {
      throw new Exception('Error decoding JSON from jwks_uri');
    }
    return $jwks->keys;
  }

  /**
   * validate the id token per the spec
   * https://openid.net/specs/openid-connect-core-1_0.html#IDTokenValidation
   *
   * @param OIDCProvider $oidcProvider
   * @param string $idToken
   * @param array $verificationValues - list of values to verify in the ID token
   * @return void
   *
   * possible $verificationValues
   * 'accessToken' - verify the at_hash value
   * 'acrValues' - verify the acr value
   * 'nonce' - verify the nonce value
   * 'context' - verify the context value
   * 'sub' - verify the sub value
   */
  private function verifyIdTokenClaims($oidcProvider, $idToken, $verificationValues = [])
  {
    // parse the token from a string
    $decodedIdToken = (new Parser())->parse((string)$idToken);

    // find public keys that this provider uses to sign the JWT
    $providerJWKs = $this->getProviderJWKs($oidcProvider->getJWKSUrl());
    // find the specific key used to sign this JWT
    $jwk = $this->getKeyForJWT($providerJWKs, $decodedIdToken);

    // convert the JWK to PEM format
    $pem = (new JWKConverter())->toPEM((array)$jwk);
    // verify the JWT signature
    $hasValidKey = $decodedIdToken->verify((new Sha256()), $pem);

    // verify the time-based token claims (nbf, iat, exp)
    // and the issuer and the subject
    $validationData = new ValidationData();
    $validationData->setIssuer($oidcProvider->getIssuer());
    $validationData->setAudience($this->clientId);
    if (isset($verificationValues['sub'])) {
      $validationData->setSubject($verificationValues['sub']);
    }
    $isValid = $decodedIdToken->validate($validationData);

    if (!$hasValidKey || !$isValid) {
      throw new Exception('Invalid ID token');
    }

    // validate nonce if required
    if (isset($verificationValues['nonce'])
      && $decodedIdToken->getClaim('nonce') !== $verificationValues['nonce']) {
      throw new Exception('invalid nonce value in ID token');
    }

    // verify the at_hash if present
    if ($decodedIdToken->hasClaim('at_hash')
      && isset($verificationValues['accessToken'])
      && !$this->verifyIdTokenAtHash($decodedIdToken, $verificationValues['accessToken'])) {
      throw new Exception('invalid at_hash value in ID token');
    }

    // validate context if required
    if (isset($verificationValues['context'])
      && $decodedIdToken->hasClaim('context')
      && $decodedIdToken->getClaim('context') !== $verificationValues['context']) {
      throw new Exception('invalid context value in ID token');
    }

    // validate acrValues if required
    if (isset($verificationValues['acrValues'])
      && $decodedIdToken->hasClaim('acr')
      && !in_array($decodedIdToken->getClaim('acr'), explode(' ', $verificationValues['acrValues']))) {
      throw new Exception('invalid acr value in ID token');
    }
  }

  /**
   * verify the at_hash in the token by hashing the access token and comparing the values
   *
   * @param Token $idToken
   * @param string $accessToken
   * @return boolean
   */
  private function verifyIdTokenAtHash($idToken, $accessToken)
  {
    $idTokenAtHash = $idToken->getClaim('at_hash');
    $alg = $idToken->getHeader('alg');

    if (isset($alg) && $alg !== 'none') {
      $bit = substr($alg, 2, 3);
    } else {
      $bit = '256';
    }
    $len = ((int)$bit) / 16;
    $actualAtHash = base64UrlEncode(substr(hash('sha' . $bit, $accessToken, true), 0, $len));

    if ($idTokenAtHash != $actualAtHash) {
      return false;
    }
    return true;
  }

  /**
   * We have an auth code, we can now exchange it for a token.
   *
   * @param GenericProvider $oidcProvider
   * @param string $code
   * @param string $state
   * @param array $tokenVerificationValues - list of values to verify in the ID token
   *
   * possible $tokenVerificationValues
   * 'acrValues' - verify the acr value
   * 'nonce' - verify the nonce value
   * 'context' - verify the context value
   * 'sub' - verify the sub value
   *
   * @return League\OAuth2\Client\Token\AccessTokenInterface
   */
  public function requestToken($oidcProvider, $code, $state, $tokenVerificationValues = [])
  {
    // prevent request forgeries by checking that the incoming state matches
    if ($state !== $this->sessionService->getState()) {
      throw new Exception('state mismatch');
    }

    $codeVerifier = $this->sessionService->getCodeVerifier();

    // exchange the auth code for a token
    $tokens = $oidcProvider->getAccessToken('authorization_code', [
      'code' => $code,
      'code_verifier' => $codeVerifier,
    ]);

    // make sure to verify the nonce
    $tokenVerificationValues['nonce'] = $this->sessionService->getNonce();

    // verify the tokens
    $this->verifyIdTokenClaims(
      $oidcProvider,
      $tokens->getValues()['id_token'],
      array_merge(['accessToken' => $tokens->getToken()], $tokenVerificationValues));

    // clear the state
    $this->sessionService->clearState();

    return $tokens;
  }

  /**
   * Make an API call to the carrier to get user info, using the token we received.
   *
   * @param GenericProvider $oidcProvider
   * @param League\OAuth2\Client\Token\AccessTokenInterface $accessToken - the access token array returned from requestToken
   *
   * @return League\OAuth2\Client\Provider\ResourceOwnerInterface
   */
  public function getUserinfo($oidcProvider, $accessToken)
  {
    return $oidcProvider->getResourceOwner($accessToken);
  }
}
