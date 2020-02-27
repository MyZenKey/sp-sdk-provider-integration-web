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

/**
 * a service for persisting items in the session.
 */
class SessionService
{
    private $stateCacheKey = 'zenkey_state';
    private $mccmncCacheKey = 'zenkey_mccmnc';
    private $userCacheKey = 'zenkey_userinfo';
    private $authorizationCacheKey = 'zenkey_session';

    public function __construct()
    {
        // start the session
        if ('' == session_id()) {
            session_start();
        }
    }

    /**
     * clear the session storage and authorization storage.
     */
    public function clear()
    {
        unset($_SESSION[$this->stateCacheKey], $_SESSION[$this->mccmncCacheKey], $_SESSION[$this->authorizationCacheKey]);

        // we avoid clearing the user so that the user is not logged out
    }

    /**
     * clear the cached state from the session.
     */
    public function clearState()
    {
        unset($_SESSION[$this->stateCacheKey]);
    }

    /**
     * persist the state in a session.
     *
     * @param string $state
     */
    public function setState($state)
    {
        $_SESSION[$this->stateCacheKey] = $state;
    }

    /**
     * get the state from the session.
     *
     * @return string|null
     */
    public function getState()
    {
        if (!isset($_SESSION[$this->stateCacheKey])) {
            return null;
        }

        return $_SESSION[$this->stateCacheKey];
    }

    /**
     * persist the MCCMNC in the session.
     *
     * @param string $mccmnc
     */
    public function setMCCMNC($mccmnc)
    {
        $_SESSION[$this->mccmncCacheKey] = $mccmnc;
    }

    /**
     * get the state from the session.
     *
     * @return string|null
     */
    public function getMCCMNC()
    {
        if (!isset($_SESSION[$this->mccmncCacheKey])) {
            return null;
        }

        return $_SESSION[$this->mccmncCacheKey];
    }

    /**
     * persist the current user in the session.
     *
     * @param array $user
     */
    public function setCurrentUser($user)
    {
        $_SESSION[$this->userCacheKey] = $user;
    }

    /**
     * get the current user from the session.
     *
     * @return array|null
     */
    public function getCurrentUser()
    {
        if (!isset($_SESSION[$this->userCacheKey])) {
            return null;
        }

        return $_SESSION[$this->userCacheKey];
    }

    /**
     * save the authorization information the session.
     *
     * @param League\OAuth2\Client\Provider\ResourceOwnerInterface $authorizationDetails
     */
    public function setAuthorizationDetails($authorizationDetails)
    {
        $_SESSION[$this->authorizationCacheKey] = $authorizationDetails;
    }

    /**
     * get the authorization information from the session.
     *
     * @return League\OAuth2\Client\Provider\ResourceOwnerInterface|null the authorization details
     */
    public function getAuthorizationDetails()
    {
        if (!isset($_SESSION[$this->authorizationCacheKey])) {
            return null;
        }

        return $_SESSION[$this->authorizationCacheKey];
    }

    /**
     * remove the authorization details from the session.
     */
    public function clearAuthorization()
    {
        unset($_SESSION[$this->authorizationCacheKey]);
    }
}
