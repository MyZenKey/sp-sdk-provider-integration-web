# ZenKey Example API Backend

An example of an API backend that communicates with ZenKey to authorize a user.

This application demonstrates how mobile apps, single-page web apps, or other frontend clients can use a backend to authorize a user with ZenKey. If you have not read the [Web Integration Guide](https://developer.myzenkey.com/docs/web), read it before continuing.

**Warning**

This example codebase shows how to use ZenKey, but it is NOT production ready. Certain features have been omitted for clarity and simplicity including
- a database/persistence layer
- logging out
- blacklisting tokens
- refreshing tokens
- CORS limitations

## 1.0 Background

The example application is built on Python 3.7 and the [Flask](http://flask.pocoo.org/) framework. It uses the [Pyoidc](https://github.com/OpenIDC/pyoidc) library to interact with ZenKey through OpenID Connect.

### 1.1 API Endpoints

You can learn about the API endpoints and how to call them in the Swagger UI documentation hosted at the `/swagger` endpoint or by reading the [Swagger file](./static/swagger.yml).

### 1.2 Security

This example backend requires clients to send an API key with all requests, indicating that the client has permission to use the backend for authorization. Before calling this backend for ZenKey authorization, clients must establish a session. Once a session is established, all API calls to this backend must include both the session token and the API key.

Clients must send the API key in an `X-API-Key` header:
```
curl -H "X-API-KEY: my_api_key" -X POST http://localhost:5000/token
```

Clients must send the access token in an `Authorization` header using the `Bearer` scheme:
```
curl -H "Authorization: Bearer my_access_token" -X POST http://localhost:5000/token
```

Most requests will require both headers:
```
curl -H "X-API-KEY: my_api_key" -H "Authorization: Bearer my_access_token" -X POST http://localhost:5000/token
```

### 1.3 Identifying a ZenKey user in your app

After a user has succesfully authenticated with ZenKey, your app can look up the user's ZenKey attributes that they have consented to share with you. Their unique identifier is the "sub" attribute. 

When logging a user in after a ZenKey authentication, you will look in your database for a user associated with the ZenKey sub. If you find a matching user, you can log that user in. If there is no record of the sub, it is likely a new user. In this example, we return the user's consented ZenKey attributes to the client in order to populate a new user registration form.

In your application, you may prefer to auto-create a new user if you could not find a user with a matching "sub" in your database.
Or you could attempt to link this ZenKey user with an existing user, allowing the user to log in either with a username/password or Sign in with ZenKey.
Some ideas for account linking:
- auto-merge a ZenKey user with another user if both users have the same verified email address
- suggest similar accounts that the user can choose to merge
- allow the user to initiate account linking after they have logged in
  - merge two existing accounts
  - add a new ZenKey login method to an existing account

#### 1.3.1 Carrier Migration
If a user has switched to a different carrier (i.e. Verizon -> AT&T) they will appear as a new user with a new "sub" value.
This app will need to use the carrier migration flow to update the user with their new "sub" value.

[Learn more about carrier account migration.](https://developer.myzenkey.com/docs/managing-carrier-account-migration)

This feature will be released in a future version of Zenkey.


## 2.0 Getting Started

The ZenKey example application uses various Python libraries. To run the application, add the libraries, then install the dependencies and configure environment variables.

Once the app is running, you can view and interact with the API endpoints using Swagger UI at `/swagger`. Or load the file `static/swagger.yml` into the [official Swagger editor](https://editor.swagger.io/).

### 2.1 Installation

Using the local `reqirements.txt` file in the root directory, use the pip installer to add the required dependencies.

1. Open a terminal in your project's root directory
2. Run `pip install -r ./requirements.txt`

(Optional) You may want to use Virtualenv to isolate your Python environment. Before running `pip install`, initialize Virtualenv and then activate it:
```
virtualenv venv
source venv/bin/activate
```

### 2.2 Environment Configuration

The `.env` file needs to be set up. Specific parameters can be found in the `.env.example` file, detailed below:

| Parameter        | Description  |
| ------------- | ------------- |  
|`BASE_URL`   |  The base domain of this application. |
|  |  Example: For auth.myapp.com, use `myapp.com` as the domain value |  
|`ALLOWED_ZENKEY_CLIENTS` | List of ZenKey project IDs that frontend clients can use with this API backend. ZenKey clients are obtained from the Developer Portal. |  
|  |  This should be a comma-separated list containing client IDs and secrets separated by a colon: `my_id:my_secret,my_other_id:my_other_secret` |  
|`API_KEYS` | A comma-separated whitelist of valid API keys that clients can use to authenticate requests. For simplicity we store this list in an environment variable, but you may want to store it in your database and associate API keys with specific clients. |
|  |  Example: `my_api_key,my_other_api_key` |  
|`SECRET_KEY_BASE` | A randomly-generated key to encrypt sessions. |  
|`PORT` | The port your app should run on. |  
|`OIDC_PROVIDER_CONFIG_URL` | The URL to ZenKey's OpenID Connect provider configuration. |  
|  |  Use the value `https://discoveryissuer.myzenkey.com/.well-known/openid_configuration` |  

### 2.3 Project Organization

- `application.py` - this is the dev server
- `config.py` - this is where application wide values are set, all requests can access these values via the `app` context
- `app/` - where the srouce for our app lives
  - `__init__.py` - configures flask app and loads all routes
  - `auth`
    - `http_access_token.py` - defines a helper to enforce access token authorization
    - `http_api_key.py` - defines a helper to enforce api key authorization
  - `models`
    - `user.py` - defines the user model
  - `routes`
    - `client_initiated.py` - defines routes for client initiated auth
    - `server_initiated.py` - defines routes for server initiated auth
    - `users.py` - defines routes for registering and accessing users
  - `utils`
    - `create_jwt.py` - helper to create jwt tokens
    - `validate_client_credentials.py` - helper to validate client id and get client secret
    - `validate_params.py` - helper to validate and parse request parameters
    - `zenkey_oidc_service.py` - handles all requests made to zenkey and demonstrates the get-user-info flow

## 3.0 Running the Application

After installing the required dependencies and configuring the environment variables, use `application.py` as the entry point to run this application.

```
FLASK_APP=application.py FLASK_ENV=development FLASK_RUN_PORT=5000 flask run
```
or
```
python application.py
```

### 3.1 Linting

To lint all the files in the project run:

```
pylint_runner
```

## 4.0 Deploying the Application

If you have an Amazon Web Services account, you can quickly deploy this demo app to Elastic Beanstalk. Here's how:

1. Install the Elastic Beanstalk CLI and configure it with your AWS credentials
  - [Instructions available on AWS](https://docs.aws.amazon.com/elasticbeanstalk/latest/dg/eb-cli3-install.html)
2. Initialize a new Elastic Beanstalk project
  - `eb init -p python-3.6 xci-example-api-backend --region us-east-1`
  - (if you run `eb init` without arguments, it will walk you through the initalization)
3. Create a new environment in your project and deploy the application to it
  - `eb create demo_environment`
4. Configure environment variables for the new EB environment
  - For each variable in your .env file, run `eb setenv key=value`
  - Or you can use the AWS Management Console
5. Open a browser to view the newly created project:
  - `eb open`

If you make updates to the code, deploy them with
```
eb deploy demo_environment
```
Note that without a `.ebignore` file, the EB CLI will deploy the latest Git commit by default.
You can deploy staged files with `eb deploy demo_environment --staged`

## Support

For technical questions, contact techsupport@myzenkey.com.
