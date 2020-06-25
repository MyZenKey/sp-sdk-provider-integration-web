<?php
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

function random($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Base64 URL encode a value
 * @param string $str
 * @return string
 */
function base64UrlEncode($str) {
    $enc = base64_encode($str);
    $enc = rtrim($enc, '=');
    $enc = strtr($enc, '+/', '-_');
    return $enc;
}

function generateCodeVerifierHash($codeVerifier) {
    $challengeBytes = hash("sha256", $codeVerifier, true);
    return base64UrlEncode($challengeBytes);
}

function curl_get_contents($url) {
    if (!function_exists('curl_init')) {
        throw new Exception('The cURL library is not installed.');
    }
    $ch = curl_init();	
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

// FastCGI does not support filter_input on ENV and SERVER variables
// this function is a polyfill/workaround
// source: https://stackoverflow.com/questions/25232975/php-filter-inputinput-server-request-method-returns-null
function filter_input_fix($type, $variable_name, $filter = FILTER_DEFAULT, $options = NULL ) {
    // FastCGI still support filter_input with these types
    $checkTypes =[
        INPUT_GET,
        INPUT_POST,
        INPUT_COOKIE
    ];

    if (in_array($type, $checkTypes) || filter_has_var($type, $variable_name)) {
        return filter_input($type, $variable_name, $filter, $options);
    } else if ($type == INPUT_SERVER && isset($_SERVER[$variable_name])) {
        return filter_var($_SERVER[$variable_name], $filter, $options);
    } else if ($type == INPUT_ENV && isset($_ENV[$variable_name])) {
        return filter_var($_ENV[$variable_name], $filter, $options);
    } else {
        return NULL;
    }
}
