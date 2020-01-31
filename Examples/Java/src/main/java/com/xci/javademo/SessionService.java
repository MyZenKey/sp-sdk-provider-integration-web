package com.xci.javademo;

import javax.servlet.http.HttpSession;
import com.nimbusds.oauth2.sdk.id.State;

public class SessionService {
	
	private String stateSessionKey = "zenkey_state";
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
	
	public void setMccmnc(HttpSession session, String mccmnc) {
		session.setAttribute(mccmncSessionKey, mccmnc);
	}
	
	public String getMccmnc(HttpSession session) {
		return (String)session.getAttribute(mccmncSessionKey);
	}
}
