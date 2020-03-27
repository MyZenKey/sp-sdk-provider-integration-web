<?php
require __DIR__.'/vendor/autoload.php';

use League\OAuth2\Client\Provider\GenericProvider;

/**
 * Override `oauth2-client`'s GenericProvider with a version for OIDC that has more
 * required parameters
 */
class OIDCProvider extends GenericProvider {
    protected $urlJWKs;
    protected $issuer;

    /**
     * Returns all options that are required.
     *
     * @return array
     */
    protected function getRequiredOptions() {
        $oauthRequiredOptions = parent::getRequiredOptions();
        // require the JWK url
        return array_merge(['urlJWKs', 'issuer'], $oauthRequiredOptions);
    }

    /**
     * @return string
     */
    public function getJWKSUrl() {
        return $this->urlJWKs;
    }

    /**
     * @return string
     */
    public function getIssuer() {
        return $this->issuer;
    }
}
