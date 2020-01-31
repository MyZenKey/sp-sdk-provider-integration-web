package com.xci.javademo;

import java.io.IOException;
import java.io.InputStream;
import java.net.MalformedURLException;
import java.net.URI;
import java.net.URISyntaxException;
import java.net.URL;
import java.util.Date;
import java.util.Map;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpSession;

import org.apache.http.client.utils.URIBuilder;
import org.springframework.security.authentication.AuthenticationServiceException;

import com.nimbusds.jose.JOSEException;
import com.nimbusds.jose.JWSAlgorithm;
import com.nimbusds.jose.proc.BadJOSEException;
import com.nimbusds.jwt.JWT;
import com.nimbusds.oauth2.sdk.AuthorizationCode;
import com.nimbusds.oauth2.sdk.AuthorizationCodeGrant;
import com.nimbusds.oauth2.sdk.ErrorObject;
import com.nimbusds.oauth2.sdk.ParseException;
import com.nimbusds.oauth2.sdk.ResponseType;
import com.nimbusds.oauth2.sdk.Scope;
import com.nimbusds.oauth2.sdk.SerializeException;
import com.nimbusds.oauth2.sdk.TokenErrorResponse;
import com.nimbusds.oauth2.sdk.TokenRequest;
import com.nimbusds.oauth2.sdk.TokenResponse;
import com.nimbusds.oauth2.sdk.auth.ClientSecretBasic;
import com.nimbusds.oauth2.sdk.auth.Secret;
import com.nimbusds.oauth2.sdk.client.ClientInformation;
import com.nimbusds.oauth2.sdk.client.ClientMetadata;
import com.nimbusds.oauth2.sdk.http.HTTPResponse;
import com.nimbusds.oauth2.sdk.id.ClientID;
import com.nimbusds.oauth2.sdk.id.State;
import com.nimbusds.oauth2.sdk.token.AccessToken;
import com.nimbusds.oauth2.sdk.token.BearerAccessToken;
import com.nimbusds.openid.connect.sdk.AuthenticationErrorResponse;
import com.nimbusds.openid.connect.sdk.AuthenticationRequest;
import com.nimbusds.openid.connect.sdk.AuthenticationRequest.Builder;
import com.nimbusds.openid.connect.sdk.claims.UserInfo;
import com.nimbusds.openid.connect.sdk.AuthenticationResponse;
import com.nimbusds.openid.connect.sdk.AuthenticationResponseParser;
import com.nimbusds.openid.connect.sdk.AuthenticationSuccessResponse;
import com.nimbusds.openid.connect.sdk.Nonce;
import com.nimbusds.openid.connect.sdk.OIDCTokenResponse;
import com.nimbusds.openid.connect.sdk.OIDCTokenResponseParser;
import com.nimbusds.openid.connect.sdk.UserInfoErrorResponse;
import com.nimbusds.openid.connect.sdk.UserInfoRequest;
import com.nimbusds.openid.connect.sdk.UserInfoResponse;
import com.nimbusds.openid.connect.sdk.UserInfoSuccessResponse;
import com.nimbusds.openid.connect.sdk.op.OIDCProviderMetadata;
import com.nimbusds.openid.connect.sdk.token.OIDCTokens;
import com.nimbusds.openid.connect.sdk.validators.IDTokenValidator;

/**
 * This class deals with the ZenKey OAuth2/OpenID Connect flow
 * 
 * 
 * the auth flow proceeds in this order:
 * 1. carrierDiscoveryRedirect()
 *      In order to discover the OIDC provider information, we need an MCCMNC.
 *      To get one, we redirect the user to carrier discovery where they select their carrier and authorize
 *      their browser.
 * 2. discoverIssuer()
 *      The carrier discovery screen redirects back to our app with an MCCMNC.
 *      We can use this MCCMNC to make a call to the OIDC discovery endpoint to get OIDC issuer information
 *      for the user's carrier (Verizon, AT&T, etc)
 * 3. requestAuthCodeRedirect()
 *      Now that we have the OIDC issuer endpoint info, we need an auth code. To get one, we redirect
 *      the user to the auth endpoint. They will be prompted to authorize this app.
 * 4. requestToken()
 *      The auth screen redirects back to our app with an auth code.
 *      We can exchange this code for an access token and ID token.
 *      Once we have these tokens, we know the user is authenticated and we can make requests
 *      to the Userinfo endpoint.
 */
public class ZenKeyOIDCAuthService {
	
	private String redirectURI;
	private String clientId;
	private String oidcProviderConfigUrl;
	private String carrierDiscoveryUrl;
	private ClientInformation clientInformation;
	private SessionService sessionService;
	
	public ZenKeyOIDCAuthService(String clientId, String clientSecret, String redirectURI, String oidcProviderConfigUrl, String carrierDiscoveryUrl) {
	    this.redirectURI = redirectURI;
	    this.clientId = clientId;
	    this.oidcProviderConfigUrl = oidcProviderConfigUrl;
	    this.carrierDiscoveryUrl = carrierDiscoveryUrl;
	    
	    // build Nimbus ClientInformation that contains the client ID and secret
	    ClientMetadata clientMetadata = new ClientMetadata();
		this.clientInformation = new ClientInformation(new ClientID(clientId), new Date(), clientMetadata, new Secret(clientSecret));
		
		this.sessionService = new SessionService();
	  }
	
	/**
	 * Carrier Discovery:
	 * To learn the mccmnc, we send the user to the ZenKey discovery endpoint.
	 * This endpoint will redirect the user back to our app, giving us the mccmnc that identifies the user's carrier.
	 */
	public URI carrierDiscoveryRedirect(HttpServletRequest request) throws AuthenticationServiceException {
		HttpSession session = request.getSession(true);
	
		// save a random state value to prevent request forgeries
		State newState = new State();
		this.sessionService.setState(session, newState);

		try {
			return new URIBuilder(this.carrierDiscoveryUrl)
					.addParameter("client_id", this.clientId)
					.addParameter("redirect_uri", this.redirectURI)
					.addParameter("state", newState.toString())
					.build();
		} catch (URISyntaxException e) {
			throw new AuthenticationServiceException("Error redirecting to carrier discovery endpoint");
		}
	}
	
	
	/**
	 * Get the user an auth code
	 * now that we have discovered the OIDC endpoint information, we can redirect
	 * to ask the user to authorize and get an auth code
	 * 
	 * This will build an auth code URL and save the necessary state information
	 * 
	 */
	public URI requestAuthCodeRedirect(HttpServletRequest request, OIDCProviderMetadata providerMetadata, Map<String, Object> urlOptions) {
		String state = request.getParameter("state");
		String loginHintToken = request.getParameter("login_hint_token");
		HttpSession session = request.getSession(true);
		
		// prevent request forgeries by checking that the incoming state matches
		if( state == null || !state.equals(sessionService.getState(session).toString()) ) {
			throw new AuthenticationServiceException("State mismatch after carrier discovery");
		}
		
		// persist a state value and MCCMNC in the session for the auth redirect
		State authRequestState = new State();
		sessionService.setState(session, authRequestState);
		sessionService.setMccmnc(session, request.getParameter("mccmnc"));
		
		URI redirectURI;
		try {
			redirectURI = new URI(this.redirectURI);
		} catch (URISyntaxException e) {
			throw new AuthenticationServiceException("Malformed redirect URI");
		}
		
		// build the auth request
		Builder authRequestBuilder = new AuthenticationRequest.Builder(new ResponseType(ResponseType.Value.CODE),
																						new Scope("openid"), // default to the openid scope only
																						this.clientInformation.getID(), redirectURI)
															.endpointURI(providerMetadata.getAuthorizationEndpointURI())
															.state(authRequestState)
															.customParameter("login_hint_token", loginHintToken);
		
		if(urlOptions.containsKey("scope")) {
			Object scopeObj = urlOptions.get("scope");
			if(scopeObj instanceof Scope) {
				authRequestBuilder.scope((Scope)scopeObj);
			} else if(scopeObj instanceof String) {
				authRequestBuilder.scope(Scope.parse((String)scopeObj));
			} else {
				throw new AuthenticationServiceException("Invalid scope type");
			}
		}
		if(urlOptions.containsKey("context")) {
			authRequestBuilder.customParameter("context", (String)urlOptions.get("context"));
		}
		if(urlOptions.containsKey("acrValues")) {
			authRequestBuilder.customParameter("acr_values", (String)urlOptions.get("acrValues"));
		}
		
		AuthenticationRequest authenticationRequest = authRequestBuilder.build();

		// send user to the ZenKey authorization endpoint to request an authorization code
		return authenticationRequest.toURI();
	}
	
	/**
	 * We have an auth code, we can now exchange it for a token
	 * First parse the request information to make sure we got a code successfully
	 */
	public OIDCTokens requestToken(HttpServletRequest request, OIDCProviderMetadata providerMetadata, URI currentRequestURI) {
		HttpSession session = request.getSession(true);

		// parse the request URL to look for an auth code or error
		AuthenticationResponse authenticationResponse;
		try {
			authenticationResponse = AuthenticationResponseParser.parse(currentRequestURI);
		} catch(ParseException e) {
			throw new AuthenticationServiceException("Error parsing auth response: " + e.getMessage());
		}
		if (authenticationResponse instanceof AuthenticationErrorResponse) {
		  ErrorObject error = ((AuthenticationErrorResponse) authenticationResponse).getErrorObject();
		  throw new AuthenticationServiceException("Error received in auth response: " + error.getDescription());
		}
		AuthenticationSuccessResponse successResponse = (AuthenticationSuccessResponse) authenticationResponse;

		// prevent request forgeries by checking that the incoming state matches
		if( successResponse.getState() == null || !successResponse.getState().equals(this.sessionService.getState(session)) ) {
			throw new AuthenticationServiceException("State mismatch after carrier discovery");
		}

		AuthorizationCode authCode = successResponse.getAuthorizationCode();
		
		// clear the session cache when we no longer need the MCCMNC and state
		this.sessionService.clear(session);

		// Fetch the access token and ID tokens and check that they were returned successfully
		URI redirectURI;
		try {
			redirectURI = new URI(this.redirectURI);
		} catch (URISyntaxException e) {
			throw new AuthenticationServiceException("Malformed redirect URI");
		}
		
		TokenRequest accessTokenRequest = new TokenRequest(providerMetadata.getTokenEndpointURI(),
				  								 new ClientSecretBasic(this.clientInformation.getID(),
				  										 			   this.clientInformation.getSecret()),
				  								 new AuthorizationCodeGrant(authCode, redirectURI));

		HTTPResponse accessTokenHTTPResponse = null;
		try {
			accessTokenHTTPResponse = accessTokenRequest.toHTTPRequest().send();
		} catch (SerializeException | IOException e) {
			throw new AuthenticationServiceException("Failed to request access token: " + e.getMessage()); 
		}

		// Parse the access token response and look for an error
		TokenResponse accessTokenResponse = null;
		try {
			accessTokenResponse = OIDCTokenResponseParser.parse(accessTokenHTTPResponse);
		} catch (ParseException e) {
			throw new AuthenticationServiceException("Failed to parse access token response: " + e.getMessage()); 
		}
		if (accessTokenResponse instanceof TokenErrorResponse) {
		  ErrorObject error = ((TokenErrorResponse) accessTokenResponse).getErrorObject();
		  throw new AuthenticationServiceException("Access token endpoint returned an error: " + error.getDescription());
		}

		return ((OIDCTokenResponse) accessTokenResponse).getOIDCTokens();
	}
	
	/**
	 * make a userinfo request to get user information
	 */
	public UserInfo requestUserinfo(HttpServletRequest request, OIDCProviderMetadata providerMetadata, AccessToken accessToken) {
		// make the request
		UserInfoRequest userinfoRequest = new UserInfoRequest(providerMetadata.getUserInfoEndpointURI(),
				  											  (BearerAccessToken) accessToken);
		HTTPResponse userinfoHTTPResponse = null;
		try {
			userinfoHTTPResponse = userinfoRequest.toHTTPRequest().send();
		} catch (SerializeException | IOException e) {
			throw new AuthenticationServiceException("Failed to request userinfo: " + e.getMessage()); 
		}
		// parse the userinfo response and look for an error
		UserInfoResponse userinfoResponse = null;
		try {
			userinfoResponse = UserInfoResponse.parse(userinfoHTTPResponse);
		} catch (ParseException e) {
			throw new AuthenticationServiceException("Failed to parse userinfo response: " + e.getMessage()); 
		}
		if (userinfoResponse instanceof UserInfoErrorResponse) {
		  ErrorObject error = ((UserInfoErrorResponse) userinfoResponse).getErrorObject();
		  throw new AuthenticationServiceException("Userinfo endpoint returned an error: " + error.getDescription());
		}

		return ((UserInfoSuccessResponse) userinfoResponse).getUserInfo();
	}
	
	/**
	 * Make an HTTP request to the ZenKey discovery issuer endpoint to access the carrier’s OIDC configuration
	 */
	protected OIDCProviderMetadata discoverIssuer(String mccmnc) throws AuthenticationServiceException {
		// build the request URL
		URL providerConfigurationURL;
		try {
			providerConfigurationURL =  new URIBuilder(this.oidcProviderConfigUrl)
					.addParameter("client_id", this.clientId)
					.addParameter("mccmnc", mccmnc)
					.build().toURL();
		} catch (MalformedURLException | URISyntaxException e) {
			throw new AuthenticationServiceException("Malformed OIDC provider config URI");
		}
		
		// Read all data from URL
		InputStream stream;
		try {
			stream = providerConfigurationURL.openStream();
		} catch (IOException e) {
			throw new AuthenticationServiceException("Unable to connect to provider discovery URI");
		}
		String providerInfo = null;
		try (java.util.Scanner s = new java.util.Scanner(stream)) {
		  providerInfo = s.useDelimiter("\\A").hasNext() ? s.next() : "";
		}

		// parse the issuer metadata JSON
		OIDCProviderMetadata providerMetadata;
		try {
			providerMetadata = OIDCProviderMetadata.parse(providerInfo);
		} catch (ParseException e) {
			throw new AuthenticationServiceException("Unable to parse issuer discovery response: " + e.getMessage());
		}
		return providerMetadata;
	}
	
	/**
	 * check that we received a valid ID token to avoid spoofing attacks
	 */
	public void validateIDToken(JWT idToken, OIDCProviderMetadata providerMetadata) {
		URL jwksURL;
		try {
			jwksURL = providerMetadata.getJWKSetURI().toURL();
		} catch (MalformedURLException e1) {
			throw new AuthenticationServiceException("Malformed JWKS URL");
		}
		// Create validator for signed ID tokens
		IDTokenValidator validator = new IDTokenValidator(
				providerMetadata.getIssuer(),
				this.clientInformation.getID(),
				JWSAlgorithm.RS256,
				jwksURL);
		
		// TODO Nonce expectedNonce = new Nonce("xyz..."); 
		Nonce expectedNonce = null;

		try {
//			The validator performs the following operations internally:
//				Checks if the ID token JWS algorithm matches the expected one.
//				Checks the ID token signature (or HMAC) using the provided key material (from the JWK set URL or the client secret).
//				Checks if the ID token issuer (iss) and audience (aud) match the expected IdP and client_id.
//				Checks if the ID token is within the specified validity window (between the given issue time and expiration time, given a 1 minute leeway to accommodate clock skew).
//				Check the nonce value if one is expected.
		    validator.validate(idToken, expectedNonce);
		} catch (BadJOSEException e) {
		    // Invalid signature or claims (iss, aud, exp...)
			throw new AuthenticationServiceException("Invalid ID token: " + e.getMessage());
		} catch (JOSEException e) {
		    // Internal processing exception
			throw new AuthenticationServiceException("Error processing ID token: " + e.getMessage());
		}
	}
}
