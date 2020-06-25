/*
 * Copyright 2020 ZenKey, LLC.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
package com.xci.javademo;

import com.nimbusds.oauth2.sdk.ParseException;
import com.nimbusds.oauth2.sdk.id.Subject;
import com.nimbusds.oauth2.sdk.util.JSONObjectUtils;
import com.nimbusds.openid.connect.sdk.claims.UserInfo;

import net.minidev.json.JSONObject;

/**
 * Subclass of Nimbus's UserInfo that handles the ZenKey userinfo format
 * 
 * 
 * ZenKey's userInfo API response is JSON in the following format (attributes depend on the scopes requested)
 * { "sub":"<mccmnc-(salted for this SP)>", "name":{ "value":"Jane Doe", "given_name":"Jane", "family_name":"Doe" }, "email":{ "value":"janedoe@example.com(opens in new tab)" }, "postal_code":{ "value":"90210-3456" }, "phone":{ "value":"+13101234567" } }
 *
 */
public class ZenKeyUserInfo extends UserInfo {
	
	public static final String POSTAL_CODE_CLAIM_NAME = "postal_code";
	public static final String GENERIC_VALUE_CLAIM_NAME = "value";
	public static final String PHONE_NUMBER_CLAIM_NAME = "phone";
	
	public ZenKeyUserInfo(Subject sub) {
		super(sub);
	}
	
	public ZenKeyUserInfo(JSONObject jsonObject) {
		super(jsonObject);
	}
	
	/**
	 * Zenkey groups certain attributes like name. Use this method to get the group of attributes:
	 * "name": {
	 *   "value": "Jane Doe",
	 *   "given_name": "Jane",
	 *   "family_name": "Doe"
	 * }
	 * 
	 * @param key - the JSON key for the object containing claims
	 * @return the nested JSON group
	 */
	private JSONObject getClaimGroup(String key) {
		try {
			return JSONObjectUtils.getJSONObject(claims, key);
		} catch (ParseException e) {
			return new JSONObject();
		}
	}
	
	public String getName() {
		try {
			return JSONObjectUtils.getString(getClaimGroup(NAME_CLAIM_NAME), GENERIC_VALUE_CLAIM_NAME);
		} catch (ParseException e) {
			return null;
		}
	}
	
	public String getGivenName() {
		try {
			return JSONObjectUtils.getString(getClaimGroup(NAME_CLAIM_NAME), GIVEN_NAME_CLAIM_NAME);
		} catch (ParseException e) {
			return null;
		}
	}
	
	public String getFamilyName() {
		try {
			return JSONObjectUtils.getString(getClaimGroup(NAME_CLAIM_NAME), FAMILY_NAME_CLAIM_NAME);
		} catch (ParseException e) {
			return null;
		}
	}
	
	public String getEmailAddress() {
		try {
			return JSONObjectUtils.getString(getClaimGroup(EMAIL_CLAIM_NAME), GENERIC_VALUE_CLAIM_NAME);
		} catch (ParseException e) {
			return null;
		}
	}
	
	public String getPhoneNumber() {
		try {
			return JSONObjectUtils.getString(getClaimGroup(PHONE_NUMBER_CLAIM_NAME), GENERIC_VALUE_CLAIM_NAME);
		} catch (ParseException e) {
			return null;
		}
	}
	
	public String getPhone() {
		return getPhoneNumber();
	}
	
	public String getPostalCode() {
		try {
			return JSONObjectUtils.getString(getClaimGroup(POSTAL_CODE_CLAIM_NAME), GENERIC_VALUE_CLAIM_NAME);
		} catch (ParseException e) {
			return null;
		}
	}
	
	/**
	 * Build a ZenKeyUserInfo object from a standard UserInfo object that Nimbus generates
	 */
	public static ZenKeyUserInfo fromUserInfo(UserInfo userInfo) {
		ZenKeyUserInfo zk = new ZenKeyUserInfo(userInfo.toJSONObject());
		return zk;
	}
	
}
