![Logo](../../image/ZenKey_rgb.png)

# ZenKey Example Application in PHP

This is an example application that demonstrates how to integrate ZenKey into a PHP web application. If you have not read the [Web Integration Guide](https://developer.myzenkey.com/web/), read it before continuing.

## 1.0 Background

The example application is built using vanilla PHP. It uses [oauth2-client](https://github.com/thephpleague/oauth2-client) as the OAuth 2.0 client.

Users can sign in using ZenKey via web browser. When authenticated, they can see their name.

For simplicity this app does not use a database. It simply stores the user info received from ZenKey in the session.

## 2.0 Getting Started

The ZenKey example application uses a few PHP packages. To run the application, install the dependencies, then configure environment variables.

### 2.1 Dependencies

- [oauth2-client](https://github.com/thephpleague/oauth2-client)

### 2.2 Installation

Install dependencies using [Composer](https://getcomposer.org/).

```
php composer.phar install
```

### 2.3 Configure Environment

Storing [configuration in the environment](http://12factor.net/config) is one of the tenets of a [twelve-factor app](http://12factor.net).

If running locally, create and configure a `.env` file based on `.env.example`.

```
cp .env.example .env
```

Otherwise, configure the environment variables in your server environment.

| Parameter        | Description  |
| ------------- | ------------- |  
|`BASE_URL`   |  The base domain of this application. |
|  |  Example: For auth.myapp.com, use `myapp.com` as the domain value |  
|`CLIENT_ID` | Your ZenKey `Client_Id` obtained from the SP Portal. |  
|`CLIENT_SECRET` | Your ZenKey `Client_Secret` obtained from the SP Portal.|
|`CARRIER_DISCOVERY_URL` | The URL to ZenKey's carrier discovery UI. |  
|  |  Use the value `https://discoveryui.myzenkey.com/ui/discovery-ui` |  
|`OIDC_PROVIDER_CONFIG_URL` | The URL to ZenKey's OpenID Connect provider configuration. |  
|  |  Use the value `https://discoveryissuer.myzenkey.com/.well-known/openid_configuration` |  

## 2.4 Running the Application

Deploy the application to your web server. Open `index.php` in a web browser.

## 2.5 Linting the Code

You can lint the code using [PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer). Follow its documented instructions
to install PHP-CS Fixer. For example, with Composer installed you can run 
```
composer global require friendsofphp/php-cs-fixer
```

Then run the linter using the project's `.php_cs.dist` linting config file:

```
php-cs-fixer fix ./ --verbose --config .php_cs.dist
```

## Support

For technical questions, contact [support](mailto:techsupport@myzenkey.com).

## License

Copyright 2020 ZenKey, LLC.

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

NOTICE: © 2020 ZenKey, LLC. ZENKEY IS A TRADEMARK OF ZenKey, LLC. ALL RIGHTS RESERVED. THE INFORMATION CONTAINED HEREIN IS NOT AN OFFER, COMMITMENT, REPRESENTATION OR WARRANTY AND IS SUBJECT TO CHANGE

## Revision History

| Date      | Version | Description                                   |
| --------- | ------- | --------------------------------------------- |
| 8.30.2019 | 0.9.3  |  Added section numbers; Added revision history; Clarified variables. |

<sub> Last Update:
Document Version 0.9.3 - August 30, 2019</sub>
