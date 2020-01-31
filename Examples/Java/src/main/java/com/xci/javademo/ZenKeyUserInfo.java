package com.xci.javademo;

import com.nimbusds.oauth2.sdk.id.Subject;
import com.nimbusds.openid.connect.sdk.claims.UserInfo;

import net.minidev.json.JSONObject;

/**
 * Subclass of Nimbus's UserInfo that handles the ZenKey's standard userinfo
 *
 */
public class ZenKeyUserInfo extends UserInfo {
	
	public static final String POSTAL_CODE_CLAIM_NAME = "postal_code";
	
	public ZenKeyUserInfo(Subject sub) {
		super(sub);
	}
	
	public ZenKeyUserInfo(JSONObject jsonObject) {
		super(jsonObject);
	}
	
	// API v1 does not return "postal_code" in an "address" object
	public String getPostalCode() {
		return getStringClaim(POSTAL_CODE_CLAIM_NAME);
	}
	
	// Verizon uses "phone" instead of "phone_number"
	public String getPhoneNumber() {
		String phoneNumber = super.getPhoneNumber();
		if(phoneNumber == null) {
			return getStringClaim("phone");
		}
		return phoneNumber;
	}
	
	// API v1 uses a string value for booleans
	public Boolean getEmailVerified() {
		String isVerified = getStringClaim(EMAIL_VERIFIED_CLAIM_NAME);
		if(isVerified == null) {
			return null;
		}
		return Boolean.parseBoolean(isVerified);
	}
	
	// API v1 uses a string value for booleans
	public Boolean getPhoneNumberVerified() {
		String isVerified = getStringClaim(PHONE_NUMBER_VERIFIED_CLAIM_NAME);
		if(isVerified == null) {
			return null;
		}
		return Boolean.parseBoolean(isVerified);
	}
	
	/**
	 * Build a ZenKeyUserInfo object from a standard UserInfo object that Nimbus generates
	 */
	public static ZenKeyUserInfo fromUserInfo(UserInfo userInfo) {
		ZenKeyUserInfo zk = new ZenKeyUserInfo(userInfo.toJSONObject());
		return zk;
	}
	
}
