![Logo](../../image/ZenKey_rgb.png)

# ZenKey Example Application in Java

The prefered implementation is using the Zenkey Framework : https://github.com/MyZenKey/zenkey-java-sdk

This is an example application that demonstrates how to integrate ZenKey into a Java web application. If you have not read the [Web Integration Guide](https://developer.myzenkey.com/docs/web), read it before continuing.

## 1.0 Background

This example application is built on the Java [Spring](https://spring.io/) framework and [Spring Security](https://spring.io/projects/spring-security) powered by [Maven](https://maven.apache.org/). It uses [Nimbus OAuth 2.0 SDK with OpenID Connect extensions](https://connect2id.com/products/nimbus-oauth-openid-connect-sdk) as the OpeID Connect client.

Users can sign in using ZenKey via web browser. When authenticated, they can see their name and user attributes from their carrier.

After signing in, the user can simulate transferring money. This uses the ZenKey auth flow to prompt the user to authorize the transaction.

For simplicity this app does not use a database. It simply stores the user info received from ZenKey in the session.

## 2.0 Getting Started

The ZenKey example application uses various Java libraries. To run the application, add the libraries, then install the dependencies and configure environment variables.

### 2.1 Installation

Requirements can be installed using Maven. There are several commands that will install dependencies for you, such as `mvn install`, `mvn package`, etc.

### 2.2 Environment Configuration

If running locally, create and configure an `application.properties` file based on `application-example.properties`.

```
cp src/main/resources/application.properties/application-example.properties src/main/resources/application.properties/application.properties
```

Otherwise, configure the environment variables in your server environment.

| Parameter        | Description  |
| ------------- | ------------- |  
|`BASE_URL`   |  The base domain of this application. |
|  |  Example: For auth.myapp.com, use `myapp.com` as the domain value |  
|`CLIENT_ID` | Your ZenKey `Client_Id` obtained from the Developer Portal. |  
|`CLIENT_SECRET` | Your ZenKey `Client_Secret` obtained from the Developer Portal.|
|`CARRIER_DISCOVERY_URL` | The URL to ZenKey's carrier discovery UI. |  
|  |  Use the value `https://discoveryui.myzenkey.com/ui/discovery-ui` |  
|`OIDC_PROVIDER_CONFIG_URL` | The URL to ZenKey's OpenID Connect provider configuration. |  
|  |  Use the value `https://discoveryissuer.myzenkey.com/.well-known/openid_configuration` |  

## 3.0 Running the Application

After installing the required dependencies and configuring the environment variables, use `JavademoApplication.java` as the entry point to run this application.

After building a JAR file with Maven you can run
```
java -jar target/javademo-0.0.1-SNAPSHOT.jar
```

## Support

For technical questions, contact [support](mailto:techsupport@myzenkey.com).

## Revision History

| Date      | Version | Description                                   |
| --------- | ------- | --------------------------------------------- |
| 8.30.2019 | 0.9.2  |  Added section numbers; Added revision history; Clarified variables. |

<sub> Last Update:
Document Version 0.9.2 - August 30, 2019</sub>
