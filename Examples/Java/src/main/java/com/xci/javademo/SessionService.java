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

import javax.servlet.http.HttpSession;
import com.nimbusds.oauth2.sdk.id.State;
import com.nimbusds.openid.connect.sdk.Nonce;

public class SessionService {
	
	private String stateSessionKey = "zenkey_state";
	private String nonceSessionKey = "zenkey_nonce";
	private String mccmncSessionKey = "zenkey_mccmnc";
	
	public SessionService() {
		
	}
	
	public void clear(HttpSession session) {
		session.removeAttribute(stateSessionKey);
		session.removeAttribute(mccmncSessionKey);
	}
	
	public void setState(HttpSession session, State state) {
		session.setAttribute(stateSessionKey, state);
	}
	
	public State getState(HttpSession session) {
		return (State)session.getAttribute(stateSessionKey);
	}
	
	public void setNonce(HttpSession session, Nonce nonce) {
		session.setAttribute(nonceSessionKey, nonce);
	}
	
	public Nonce getNonce(HttpSession session) {
		return (Nonce)session.getAttribute(nonceSessionKey);
	}
	
	public void setMccmnc(HttpSession session, String mccmnc) {
		session.setAttribute(mccmncSessionKey, mccmnc);
	}
	
	public String getMccmnc(HttpSession session) {
		return (String)session.getAttribute(mccmncSessionKey);
	}
}
