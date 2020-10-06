![Logo](../../image/ZenKey_rgb.png)

# ZenKey Example Application in Node.js

This is an example application that demonstrates how to integrate ZenKey into a Node.js web application. If you have not read the [Web Integration Guide](https://developer.myzenkey.com/docs/web), read it before continuing.

## 1.0 Background

The example application is built using [Express](https://github.com/expressjs/express), a popular framework for building web application in Node.js. It uses [Passport](https://github.com/jaredhanson/passport) as authentication middleware and [openid-client](https://github.com/panva/node-openid-client) as the OpenID client.

Users can sign in using ZenKey via web browser. When authenticated, they can see their name and user attributes from their carrier.

After signing in, the user can simulate transferring money. This uses the ZenKey auth flow to prompt the user to authorize the transaction.

For simplicity this app does not use a database. It simply stores the user info received from ZenKey in the session.

### 1.1 Passport

Passport is a Node.js middleware that authenticates requests. The `zenkey` Passport strategy (`./ZenKeyStrategy.js`) contains an `authenticate` method that handles the ZenKey authentication flow. Then this method uses the `verify` callback to configure the user. Once authenticated, Passport stores the user in the session (using `passport.serializeUser`).

## 2.0 Getting Started

The ZenKey example application uses a few Node.js packages. To run the application, install the dependencies, then configure environment variables.

### 2.1 Installation

Install dependencies using [Yarn](https://yarnpkg.com/).

```
yarn install
```

### 2.2 Configure Environment

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
|`CLIENT_ID` | Your ZenKey `Client_Id` obtained from the Developer Portal. |  
|`CLIENT_SECRET` | Your ZenKey `Client_Secret` obtained from the Developer Portal.|
|`SECRET_KEY_BASE` | A randomly-generated key to encrypt sessions. |  
|`PORT` | The port your app should run on. |  
|`CARRIER_DISCOVERY_URL` | The URL to ZenKey's carrier discovery UI. |  
|  |  Use the value `https://discoveryui.myzenkey.com/ui/discovery-ui` |  
|`OIDC_PROVIDER_CONFIG_URL` | The URL to ZenKey's OpenID Connect provider configuration. |  
|  |  Use the value `https://discoveryissuer.myzenkey.com/.well-known/openid_configuration` |  

## 3.0 Start the Server

Start the server using the start script:

```
yarn start
```

## Support

For technical questions, contact [support](mailto:techsupport@myzenkey.com).

## Revision History

| Date      | Version | Description                                   |
| --------- | ------- | --------------------------------------------- |
| 11.13.2019 | 0.9.3  |  Simplified application by removing unnecessary code; added additional documentation and comments |
| 8.30.2019 | 0.9.2  |  Added section numbers; Added revision history; Clarified variables. |

<sub> Last Update:
Document Version 0.9.2 - August 30, 2019</sub>
