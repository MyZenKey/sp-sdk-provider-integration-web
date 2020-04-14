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