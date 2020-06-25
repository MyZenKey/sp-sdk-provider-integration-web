![Logo](../../image/ZenKey_rgb.png)

# ZenKey Example Application in Python

This is an example application that demonstrates how to integrate ZenKey into a Python web application. If you have not read the [Web Integration Guide](https://developer.myzenkey.com/web/), read it before continuing.

## 1.0 Background

The example application is built on the Python [Flask](http://flask.pocoo.org/) framework. It uses the [Pyoidc](https://github.com/OpenIDC/pyoidc) library to interact with ZenKey through OpenID Connect.

Users can sign in using ZenKey via web browser. When authenticated, they can see their name and user attributes from their carrier on the home screen.

After signing in, the user can simulate transferring money. This uses the ZenKey auth flow to prompt the user to authorize the transaction.

For simplicity this app does not use a database. It simply stores the user info received from ZenKey in the session.

## 2.0 Getting Started

The ZenKey example application uses various Python libraries. To run the application, add the libraries, then install the dependencies and configure environment variables.

### 2.1 Installation

Using the `Pipfile` file in the root directory, use `pipenv` to install the required dependencies and create a virtualenv. If you don't have `pipenv` installed, follow the system specific installation instructions [here](https://github.com/pypa/pipenv).

1. Open a terminal in your project's root directory
2. Run `pipenv install` to install all of the dependencies specified in the Pipfile

### 2.2 Environment Configuration

The `.env` file needs to be set up. Specific parameters can be found in the `.env.example` file, detailed below:

| Parameter        | Description  |
| ------------- | ------------- |  
|`BASE_URL`   |  The base domain of this application. |
|  |  Example: For auth.myapp.com, use `myapp.com` as the domain value |  
|`CLIENT_ID` | Your ZenKey `Client_Id` obtained from the SP Portal. |  
|`CLIENT_SECRET` | Your ZenKey `Client_Secret` obtained from the SP Portal.|
|`SECRET_KEY_BASE` | A randomly-generated key to encrypt sessions. |  
|`PORT` | The port your app should run on. |  
|`CARRIER_DISCOVERY_URL` | The URL to ZenKey's carrier discovery UI. |  
|  |  Use the value `https://discoveryui.myzenkey.com/ui/discovery-ui` |  
|`OIDC_PROVIDER_CONFIG_URL` | The URL to ZenKey's OpenID Connect provider configuration. |  
|  |  Use the value `https://discoveryissuer.myzenkey.com/.well-known/openid_configuration` |  

## 3.0 Running the Application

After installing the required dependencies and configuring the environment variables, use `application.py` as the entry point to run this application.

```
pipenv run FLASK_APP=application.py FLASK_ENV=development FLASK_RUN_PORT=5000 flask run
```

or

```
pipenv run python application.py
```

### 3.1 Parsing the `id_token`

After a user successfully logs in, the `get_current_user` is called to parse through the `id_token` in session. In this application, we demonstrate basic parsing by displaying the user's full name.

## Support

For technical questions, contact [support](mailto:techsupport@myzenkey.com).

## Revision History

| Date      | Version | Description                                   |
| --------- | ------- | --------------------------------------------- |
| 8.30.2019 | 0.9.2  |  Added section numbers; Added revision history; Clarified variables. |

<sub> Last Update:
Document Version 0.9.2 - August 30, 2019</sub>
