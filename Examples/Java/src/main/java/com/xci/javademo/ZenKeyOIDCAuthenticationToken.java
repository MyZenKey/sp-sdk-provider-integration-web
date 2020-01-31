package com.xci.javademo;

import java.io.IOException;
import java.io.ObjectInputStream;
import java.io.ObjectOutputStream;
import java.text.ParseException;
import java.util.Collection;

import org.springframework.security.authentication.AbstractAuthenticationToken;
import org.springframework.security.core.GrantedAuthority;

import com.google.common.collect.ImmutableMap;
import com.nimbusds.jwt.JWT;
import com.nimbusds.jwt.JWTParser;
import com.nimbusds.openid.connect.sdk.claims.UserInfo;

/**
 * 
 * This is an implementation of Spring Security's Authentication interface: it represents
 * a user's authentication and contains details about the user.
 * 
 * This class is based on Mitre's MITREid Connect OIDCAuthenticationToken class
 *
 */
public class ZenKeyOIDCAuthenticationToken extends AbstractAuthenticationToken {

	private static final long serialVersionUID = 22100073066377804L;

	private final ImmutableMap<String, String> principal;
	private final String accessTokenValue; // string representation of the access token
	private final String refreshTokenValue; // string representation of the refresh token
	private transient JWT idToken; // this needs a custom serializer
	private final String issuer; // issuer URL (parsed from the id token)
	private final String sub; // user id (parsed from the id token)

	private final ZenKeyUserInfo userInfo; // user info container

	/**
	 * Constructs ZenKeyOIDCAuthenticationToken with the tokens and userinfo
	 *
	 * Set the class as authenticated so the user is logged in.
	 *
	 * Constructs a Principal out of the subject and issuer.
	 * @param subject
	 * @param authorities
	 * @param principal
	 * @param idToken
	 */
	public ZenKeyOIDCAuthenticationToken(String subject, String issuer,
			UserInfo userInfo, Collection<? extends GrantedAuthority> authorities,
			JWT idToken, String accessTokenValue, String refreshTokenValue) {

		super(authorities);

		this.principal = ImmutableMap.of("sub", subject, "iss", issuer);
		this.userInfo = ZenKeyUserInfo.fromUserInfo(userInfo);
		this.sub = subject;
		this.issuer = issuer;
		this.idToken = idToken;
		this.accessTokenValue = accessTokenValue;
		this.refreshTokenValue = refreshTokenValue;

		setAuthenticated(true);
	}


	/*
	 * (non-Javadoc)
	 *
	 * @see org.springframework.security.core.Authentication#getCredentials()
	 */
	@Override
	public Object getCredentials() {
		return accessTokenValue;
	}

	/**
	 * Get the principal of this object, an immutable map of the subject and issuer.
	 */
	@Override
	public Object getPrincipal() {
		return principal;
	}

	public String getSub() {
		return sub;
	}

	/**
	 * @return the idTokenValue
	 */
	public JWT getIdToken() {
		return idToken;
	}

	/**
	 * @return the accessTokenValue
	 */
	public String getAccessTokenValue() {
		return accessTokenValue;
	}

	/**
	 * @return the refreshTokenValue
	 */
	public String getRefreshTokenValue() {
		return refreshTokenValue;
	}

	/**
	 * @return the issuer
	 */
	public String getIssuer() {
		return issuer;
	}

	/**
	 * @return the userInfo
	 */
	public ZenKeyUserInfo getUserInfo() {
		return userInfo;
	}

	/*
	 * Custom serialization to handle the JSON object
	 */
	private void writeObject(ObjectOutputStream out) throws IOException {
		out.defaultWriteObject();
		if (idToken == null) {
			out.writeObject(null);
		} else {
			out.writeObject(idToken.serialize());
		}
	}
	private void readObject(ObjectInputStream in) throws IOException, ClassNotFoundException, ParseException {
		in.defaultReadObject();
		Object o = in.readObject();
		if (o != null) {
			idToken = JWTParser.parse((String)o);
		}
	}

}
