package com.xci.javademo;

import java.io.IOException;
import java.net.URI;
import java.net.URISyntaxException;
import java.util.Arrays;
import java.util.Collection;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;

import org.springframework.security.authentication.AuthenticationServiceException;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.AuthenticationException;
import org.springframework.security.core.GrantedAuthority;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.web.authentication.AbstractAuthenticationProcessingFilter;
import org.springframework.security.web.util.matcher.AntPathRequestMatcher;
import org.springframework.security.web.util.matcher.OrRequestMatcher;

import com.google.common.base.Strings;
import com.nimbusds.jwt.JWT;
import com.nimbusds.oauth2.sdk.Scope;
import com.nimbusds.oauth2.sdk.token.AccessToken;
import com.nimbusds.oauth2.sdk.token.RefreshToken;
import com.nimbusds.openid.connect.sdk.claims.UserInfo;
import com.nimbusds.openid.connect.sdk.op.OIDCProviderMetadata;
import com.nimbusds.openid.connect.sdk.token.OIDCTokens;
import com.xci.javademo.AuthorizationFlowHandler.SessionAuthorizeDetails;

/**
 * This Spring Security filter authenticates the user with ZenKey
 *
 */
public class ZenKeyAuthFilter extends AbstractAuthenticationProcessingFilter {
	
	private String redirectPath = "/auth/cb";
	
	private String baseUrl;
	private Collection<String> scope;
	
	private String redirectURI;

	private ZenKeyOIDCAuthService authService;

	private AuthorizationFlowHandler authFlowhandler;

	private SessionService sessionService;
	

	public ZenKeyAuthFilter(String baseUrl, String clientId, String clientSecret, String carrierDiscoveryUrl, String oidcProviderConfigUrl, List<String> scope) {
		super(new OrRequestMatcher(Arrays.asList(new AntPathRequestMatcher("/auth*", "GET"), new AntPathRequestMatcher("/auth/cb*", "GET"))));
		
		this.baseUrl = baseUrl;
		this.redirectURI = String.format("%s%s", this.baseUrl, this.redirectPath);
		this.scope = scope;
		
		this.authService = new ZenKeyOIDCAuthService(clientId, clientSecret, this.redirectURI, oidcProviderConfigUrl, carrierDiscoveryUrl);
		this.sessionService = new SessionService();
	}
	
	@Override
	public Authentication attemptAuthentication(HttpServletRequest request, HttpServletResponse response) throws AuthenticationException, IOException {
		HttpSession session = request.getSession(true);

		try {
			if (!Strings.isNullOrEmpty(request.getParameter("error"))) {
	
				// there's an error coming back from the server, need to handle this
				handleError(request, response);
				return null; // no auth, response is sent to display page or something
	
			} else if (!Strings.isNullOrEmpty(request.getParameter("code"))) {
	
				// we got back the code, need to process this to get our tokens
				Authentication auth = handleAuthorizationCodeResponse(request, response);
				return auth;
	
			} else if(!Strings.isNullOrEmpty(request.getParameter("mccmnc"))) {
	
				// not an error, not a code, must be an initial login of some type
				handleAuthorizationRequest(request, response);
	
				return null; // no auth, response redirected to the server's Auth Endpoint (or possibly to the account chooser)
			} else {
				doCarrierDiscovery(request, response);
				return null;
			}
		} catch (AuthenticationException e) {
			this.sessionService.clear(session);
			// avoid throwing an AuthenticationException during a postlogin auth flow because that would log us out
			if(this.authFlowhandler.authorizationInProgress(session)) {
				this.authFlowhandler.deleteAuthorizationDetails(session);
				throw new IOException(e);
			}
			throw e;
		} catch (Exception e) {
			// if we get any error during auth, clear the authentication details so the user can try again
			this.sessionService.clear(session);
			this.authFlowhandler.deleteAuthorizationDetails(session);
			throw e;
		}
	}
	
	protected void doCarrierDiscovery(HttpServletRequest request, HttpServletResponse response) throws IOException {
		URI carrierDiscoveryUrl = this.authService.carrierDiscoveryRedirect(request);
		response.sendRedirect(carrierDiscoveryUrl.toString());
	}

	/**
	 * Request an auth code
     * The carrier discovery endpoint has redirected back to our app with the mccmnc.
     * Now we can start the authorize flow by requesting an auth code.
     * Send the user to the ZenKey authorization endpoint. After authorization, this endpoint will redirect back to our app with an auth code.
	 */
	protected void handleAuthorizationRequest(HttpServletRequest request, HttpServletResponse response) throws IOException {
		String mccmnc = request.getParameter("mccmnc");
		if(mccmnc == null) {
			throw new AuthenticationServiceException("No MCCMNC available");
		}
		
		OIDCProviderMetadata providerMetadata = authService.discoverIssuer(mccmnc);
		
		Scope scope = Scope.parse(this.scope);
		HashMap<String, Object> urlOptions = new HashMap<String, Object>();
		
		// if there is an authorization flow in progress (as a opposed to a standard "log in with ZenKey flow",
		// pull the details and provide a context for the authorization
		HttpSession session = request.getSession(true);
		SessionAuthorizeDetails sessionAuthorizeDetails = this.authFlowhandler.getAuthorizationDetails(session);
		if(this.authFlowhandler.authorizationInProgress(session)) {
			// authorization is in progress
			// only openid scope is needed for this auth request
			urlOptions.put("scope",  Scope.parse("openid"));
			// add the context and acr value to the auth request
			urlOptions.put("context", sessionAuthorizeDetails.getContext());
			urlOptions.put("acrValues", "a3");
		} else {
			// no authorization in progress: do a standard login authorization
			urlOptions.put("scope", scope);
			
		}
		
		URI authorizationURI = this.authService.requestAuthCodeRedirect(request, providerMetadata, urlOptions);

		// send user to the ZenKey authorization endpoint to request an authorization code
		response.sendRedirect(authorizationURI.toString());
	}

	/**
	 * Request a token
     * The authentication endpoint has redirected back to our app with an auth code. Now we can exchange the auth code for a token.
     * Once we have a token, we can make a userinfo request to get user profile information.
	 * @throws IOException 
	 */
	protected Authentication handleAuthorizationCodeResponse(HttpServletRequest request, HttpServletResponse response) throws AuthenticationServiceException, IOException {
		HttpSession session = request.getSession(true);
		SessionService sessionService = new SessionService();

		// use a cached MCCMNC if needed
		String mccmnc = request.getParameter("mccmnc");
		if(mccmnc == null) {
			mccmnc = sessionService.getMccmnc(session);
		}
		if(mccmnc == null) {
			throw new AuthenticationServiceException("No MCCMNC available");
		}
		OIDCProviderMetadata providerMetadata = authService.discoverIssuer(mccmnc);
		
		// get the current request URL
		URI currentRequestURI;
		try {
			currentRequestURI = getCurrentRequestURI(request);
		} catch(URISyntaxException e) {
			throw new AuthenticationServiceException("Error parsing authentication request URI");
		}
		OIDCTokens tokenResponse = this.authService.requestToken(request, providerMetadata, currentRequestURI);
		
		
		AccessToken accessToken = tokenResponse.getAccessToken();
		JWT idToken = tokenResponse.getIDToken();
		RefreshToken refreshToken = tokenResponse.getRefreshToken();
		
		
		// validate the ID token JWT
		this.authService.validateIDToken(idToken, providerMetadata);
		
		// if auth in progress, do the auth thing
		// otherwise do the userinfo call and login
		if(this.authFlowhandler.authorizationInProgress(session)) {
			this.authFlowhandler.successRouter(session, request, response, tokenResponse);
			return null;
		}
		
		
		UserInfo userInfo = authService.requestUserinfo(request, providerMetadata, accessToken);
		
        // this is where a real app might look up the user in the database using the "sub" value
        // we could also create a new user or show a registration form
        // the userInfo object contains values like sub, name, and email (depending on which scopes were requested)
        // these values can be saved for the user or used to auto-populate a registration form
		
		// return an authorized Authentication with the "ROLE_USER" authority
		Set<GrantedAuthority> authorities = new HashSet<>();
		authorities.add(new SimpleGrantedAuthority("ROLE_USER"));

		return new ZenKeyOIDCAuthenticationToken(
				userInfo.getSubject().toString(),
				providerMetadata.getIssuer().toString(),
				userInfo, authorities,
				idToken,
				(accessToken != null ? accessToken.getValue() : null),
				(refreshToken != null ? refreshToken.getValue() : null));
	}

	/**
	 * Get the URI of the current request by parsing the request strings
	 */
	private URI getCurrentRequestURI(HttpServletRequest request) throws URISyntaxException {
		StringBuffer requestURL = request.getRequestURL();
	    String queryString = request.getQueryString();
	    String requestString;

	    if (queryString == null)
	    	requestString = requestURL.toString();

	    requestString = requestURL.append('?').append(queryString).toString();
		
		return new URI(requestString);
	}
	
	/**
	 * Get the OIDC error details from the URL and throw an error message
	 */
	protected void handleError(HttpServletRequest request, HttpServletResponse response) throws AuthenticationServiceException {
		String error = request.getParameter("error");
		String errorDescription = request.getParameter("error_description");
		String errorMessage = String.format("%s: %s", error, errorDescription);
		throw new AuthenticationServiceException(errorMessage);
	}

	public void setAuthFlowHandler(AuthorizationFlowHandler authorizationFlowHandler) {
		this.authFlowhandler = authorizationFlowHandler;
		
	}

}
