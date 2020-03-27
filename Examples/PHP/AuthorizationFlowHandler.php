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
 * This class helps with the ZenKey non-login authorize flow. It saves
 * authorization details in the session and contains the authorize success callbacks.
 */
class AuthorizationFlowHandler
{
    public $sessionService;

    /**
     * @param SessionService $sessionService
     */
    public function __construct($sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Check whether a non-login authorization is in progress: if so there will be
     * details saved in the session.
     *
     * @return bool
     */
    public function authorizationInProgress()
    {
        if ($this->getAuthorizationDetails()) {
            return true;
        }

        return false;
    }

    /**
     * Remove the in-progress authorization details from the session.
     */
    public function deleteAuthorizationDetails()
    {
        $this->sessionService->clearAuthorization();
    }

    /**
     * Persist in-progress authorization details in the session After the ZenKey
     * auth flow redirects, the app will recognize that an authorization is still in
     * progress by looking for this information in the session.
     *
     * @param string $authType
     * @param string $context
     * @param array  $options
     */
    public function setAuthorizationDetails($authType, $context, $options)
    {
        $this->sessionService->setAuthorizationDetails([
            'type' => $authType,
            'context' => $context,
            'options' => $options,
        ]);
    }

    /**
     * Get authorization details from the session.
     *
     * @return League\OAuth2\Client\Provider\ResourceOwnerInterface|null
     */
    public function getAuthorizationDetails()
    {
        return $this->sessionService->getAuthorizationDetails();
    }

    /**
     * Call this after the authorization flow is successful
     * It will call a different success method depending on the type of authorization in progress.
     *
     * @return mixed
     */
    public function successRouter()
    {
        if (!$this->authorizationInProgress()) {
            // no explicit authorization in progress
            header('Location: /');

            return;
        }

        $sessionAuthorizeDetails = $this->getAuthorizationDetails();
        $authType = $sessionAuthorizeDetails['type'];

        switch ($authType) {
      case 'transaction':
        return $this->transactionAuthorizeSuccess();
        break;
      case 'adduser':
        return $this->addUserAuthorizeSuccess();
        break;
      default:
        throw new Exception('Unknown authorization type');
    }
    }

    /**
     * once a transaction has been authorized using ZenKey, this function is called to
     * complete the transaction.
     *
     * SUCCESS:
     * now we have authorized the user with ZenKey
     * this is where you would add the business logic to complete the transaction
     * first verify that the token is for this user
     * start by getting the logged in user's "sub" value
     *
     * When an transaction authorization is successful, check that the token matches
     * the current user
     * If it does, then send a success message to the homepage
     */
    public function transactionAuthorizeSuccess()
    {
        // pull the authorization details out of the session so we can build a success message
        $sessionAuthorizeDetails = $this->getAuthorizationDetails();
        if (!$sessionAuthorizeDetails) {
            return;
        }

        $options = $sessionAuthorizeDetails['options'];
        $amount = $options['amount'];
        $recipient = $options['recipient'];

        // If this were a fully functional app, you might call a function to complete transaction here

        // after completion, remove the authorization details from the session
        $this->deleteAuthorizationDetails();

        // return to the homepage with a message
        $message = "Success: \${$amount} was sent to {$recipient}";
        header('Location: /?message='.$message);
    }

    /**
     * You can use ZenKey authorization for multiple things, like authorizing a
     * newly added user on the account.
     *
     * @throws Exception
     */
    public function addUserAuthorizeSuccess()
    {
        throw new Exception('Not implemented');
    }
}
