/*
 * Copyright 2019 ZenKey, LLC.
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

import java.io.IOException;
import java.util.Arrays;
import java.util.List;

import javax.servlet.Filter;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;

import com.google.common.collect.ImmutableMap;
import com.nimbusds.openid.connect.sdk.claims.UserInfo;

import org.apache.commons.logging.Log;
import org.apache.commons.logging.LogFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.Configuration;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.annotation.web.configuration.WebSecurityConfigurerAdapter;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.web.authentication.LoginUrlAuthenticationEntryPoint;
import org.springframework.security.web.authentication.www.BasicAuthenticationFilter;
import org.springframework.security.web.csrf.CookieCsrfTokenRepository;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;

@SpringBootApplication
@Controller
@Configuration
public class JavademoApplication extends WebSecurityConfigurerAdapter {

	@Value("${BASE_URL}")
	private String baseUrl;

	@Value("${CLIENT_ID}")
	private String clientId;

	@Value("${CLIENT_SECRET}")
	private String clientSecret;

	@Value("${CARRIER_DISCOVERY_URL}")
	private String carrierDiscoveryUrl;
	
	@Value("${OIDC_PROVIDER_CONFIG_URL}")
	private String oidcProviderConfigUrl;
	
	private final List<String> scope = Arrays.asList("openid", "name", "email", "postal_code", "phone");
	
	protected final Log logger = LogFactory.getLog(getClass());
	
	@GetMapping("/")
	public String main(Model model, HttpServletRequest request) {
		Authentication authentication = SecurityContextHolder.getContext().getAuthentication();
		UserInfo userInfo = null;
		if (authentication instanceof ZenKeyOIDCAuthenticationToken) {
		  userInfo = ((ZenKeyOIDCAuthenticationToken)authentication).getUserInfo();
		}
		String message = request.getParameter("message");
		if(message != null) {
			model.addAttribute("message", message);
		}
		model.addAttribute("userInfo", userInfo);
		return "index";
	}
	
	@PostMapping("/authorize-transaction")
	public void authorizeTransaction(HttpServletRequest request, HttpServletResponse response) throws IOException {
		// save the authorization information in the session so we can use it after the user is authenticated
		HttpSession session = request.getSession(true);
		
		String amount = request.getParameter("amount");
		if(amount == null) {
			amount = "0";
		}
		String recipient = "John Doe";
		String context = String.format("Send $%s to %s", amount, recipient);
		
		AuthorizationFlowHandler handler = new AuthorizationFlowHandler();
		handler.setAuthorizationDetails(session, "transaction", context, ImmutableMap.of(
						"amount", amount,
						"recipient", recipient
				));

	    // begin the auth flow
	    response.sendRedirect("/auth/cb");
	}

	@Override
	protected void configure(HttpSecurity http) throws Exception {
		http
			.antMatcher("/**").authorizeRequests() // enable authorization
			.antMatchers("/", "/user", "/auth**", "/stylesheets/**", "/error", "/error**", "/favicon.ico").permitAll() // allow public access
			.anyRequest().authenticated() // allow authenticated users everywhere else
			.and()
			.exceptionHandling().authenticationEntryPoint(new LoginUrlAuthenticationEntryPoint("/"))
			.and()
			.logout().invalidateHttpSession(true).deleteCookies("JSESSIONID", "XSRF-TOKEN")
				.logoutUrl("/logout").logoutSuccessUrl("/").permitAll() // enable logout and clear the session when logging out
			.and()
			.csrf().csrfTokenRepository(CookieCsrfTokenRepository.withHttpOnlyFalse()) // enable CSRF
			.and()
			.addFilterBefore(zenKeyAuthFilter(), BasicAuthenticationFilter.class); // add our auth filter
	}

	public static void main(String[] args) {
		SpringApplication.run(JavademoApplication.class, args);
	}

	/**
	 * Build the ZenKey OIDC authentication filter
	 */
	private Filter zenKeyAuthFilter() {
		ZenKeyAuthFilter filter = new ZenKeyAuthFilter(this.baseUrl,
								this.clientId,
								this.clientSecret,
								this.carrierDiscoveryUrl,
								this.oidcProviderConfigUrl,
								this.scope);
		filter.setAuthFlowHandler(new AuthorizationFlowHandler());
		return filter;
	}
}
