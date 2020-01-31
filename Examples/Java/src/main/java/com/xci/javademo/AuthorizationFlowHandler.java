package com.xci.javademo;

import java.io.IOException;
import java.text.ParseException;
import java.util.Map;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;

import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;

import com.nimbusds.jwt.JWT;
import com.nimbusds.openid.connect.sdk.claims.UserInfo;
import com.nimbusds.openid.connect.sdk.token.OIDCTokens;

/**
 * This class helps with the ZenKey non-login authorize flow. It saves
 * authorization details in the session and contains the authorize success
 * callbacks
 */
public class AuthorizationFlowHandler {

	public class SessionAuthorizeDetails {
		private String type;
		private String context;
		private Map<String, Object> options;

		SessionAuthorizeDetails(String type, String context, Map<String, Object> options) {
			this.type = type;
			this.context = context;
			this.options = options;
		}

		public String getType() {
			return type;
		}

		public String getContext() {
			return context;
		}

		public Map<String, Object> getOptions() {
			return options;
		}
	}

	private static final String sessionKey = "authorization";

	/**
	 * Check whether a non-login authorization is in progress: if so there will be
	 * details saved in the session
	 */
	public boolean authorizationInProgress(HttpSession session) {
		return getAuthorizationDetails(session) != null;
	}

	/**
	 * Remove the in-progress authorization details from the session
	 */
	public void deleteAuthorizationDetails(HttpSession session) {
		session.removeAttribute(sessionKey);
	}

	/**
	 * Persist in-progress authorization details in the session After the ZenKey
	 * auth flow redirects, the app will recognize that an authorization is still in
	 * progress by looking for this information in the session
	 */
	public void setAuthorizationDetails(HttpSession session, String type, String context, Map<String, Object> options) {
		SessionAuthorizeDetails authorizeDetails = new SessionAuthorizeDetails(type, context, options);
		session.setAttribute(sessionKey, authorizeDetails);
	}

	/**
	 * Get authorization details from the session
	 */
	public SessionAuthorizeDetails getAuthorizationDetails(HttpSession session) {
		Object sessionAuthorizeDetails = session.getAttribute(sessionKey);
		if (sessionAuthorizeDetails == null || !(sessionAuthorizeDetails instanceof SessionAuthorizeDetails)) {
			return null;
		}
		return (SessionAuthorizeDetails) sessionAuthorizeDetails;
	}

	/**
	 * Call this after the authorization flow is successful It will call a different
	 * success method depending on the type of authorization in progress
	 */
	public void successRouter(HttpSession session, HttpServletRequest request, HttpServletResponse response,
			OIDCTokens tokens) throws IOException {
		if (!authorizationInProgress(session)) {
			// no explicit authorization in progress
			return;
		}

		SessionAuthorizeDetails sessionAuthorizeDetails = getAuthorizationDetails(session);

		switch (sessionAuthorizeDetails.getType()) {
		case "transaction":
			transactionAuthorizeSuccess(session, request, response, tokens);
			break;
		case "adduser":
			addUserAuthorizeSuccess();
		default:
			throw new Error("Unknown authorization type");
		}
	}

	/**
	 * When an transaction authorization is successful, check that the token matches
	 * the current user If then send a success message to the homepage Normally you
	 * would add business logic to this method
	 */
	public void transactionAuthorizeSuccess(HttpSession session, HttpServletRequest request,
			HttpServletResponse response, OIDCTokens tokens) throws IOException {
		// SUCCESS:
		// now we have authorized the user with ZenKey
		// this is where you would add the business logic to complete the transaction

		// first verify that the token is for this user
		// start by getting the logged in user's "sub" value
		Authentication authentication = SecurityContextHolder.getContext().getAuthentication();
		UserInfo userInfo = null;
		if (authentication instanceof ZenKeyOIDCAuthenticationToken) {
			userInfo = ((ZenKeyOIDCAuthenticationToken) authentication).getUserInfo();
		} else {
			throw new IOException("Unable to get authenticated user");
		}
		String loggedInSubject = userInfo.getSubject().toString();
		// get the "sub" value from the token
		JWT idToken = tokens.getIDToken();
		String claimSubject;
		try {
			claimSubject = idToken.getJWTClaimsSet().getSubject();
		} catch (ParseException e) {
			throw new IOException("Error parsing JWT");
		}
		// check that the sub values match
		if (!loggedInSubject.equals(claimSubject)) {
			throw new IOException("Token does not match user sub");
		}

		// pull the authorization details out of the session so we can build a success
		// message
		SessionAuthorizeDetails sessionAuthorizeDetails = getAuthorizationDetails(session);
		if (sessionAuthorizeDetails == null) {
			return;
		}
		Map<String, Object> options = sessionAuthorizeDetails.getOptions();
		if (options == null) {
			return;
		}

		String amount = options.get("amount").toString();
		String recipient = options.get("recipient").toString();

		// If this were a fully functional app, you might call a function to complete
		// transaction here

		// after completion, remove the authorization details from the session
		this.deleteAuthorizationDetails(session);
		SessionService sessionService = new SessionService();
		sessionService.clear(session);

		// return to the homepage with a message
		String message = String.format("Success: $%s was sent to %s", amount, recipient);
		response.sendRedirect(String.format("/?message=%s", message));
	}

	/**
	 * You can use ZenKey authorization for multiple things, like authorizing a
	 * newly added user on the account
	 */
	public void addUserAuthorizeSuccess() {
		throw new Error("Not implemented");
	}
}
