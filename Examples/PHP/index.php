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
require __DIR__.'/vendor/autoload.php';
require __DIR__.'/SessionService.php';

$sessionService = new SessionService();
$currentUser = $sessionService->getCurrentUser();
$message = isset($_GET['message']) ? $_GET['message'] : null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>ZenKey-DemoApp-PHP</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
          integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <link rel="stylesheet" href="/stylesheets/zk-btn.css">
    <link rel="stylesheet" href="/stylesheets/style.css">
</head>
<body>
<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top"><a class="navbar-brand" href="/">ZenKey-DemoApp-PHP</a>
    <ul class="navbar-nav ml-auto">
        <li class="nav-item">
            <?php if ($currentUser) { ?>
                <a class="nav-link" href="/logout.php">Sign Out</a>
            <?php } else { ?>
                <a href="/auth.php" class="zk-btn zk-btn--mini zk-btn--light">
                    <span class="zk-sr-only">ZenKey</span>
                    <svg class="zk-btn__img" width="60" height="44" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><defs><filter x="-7.1%" y="-5%" width="114.3%" height="120%" filterUnits="objectBoundingBox" id="a2"><feOffset dy="2" in="SourceAlpha" result="shadowOffsetOuter1"/><feGaussianBlur stdDeviation="1" in="shadowOffsetOuter1" result="shadowBlurOuter1"/><feColorMatrix values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.24 0" in="shadowBlurOuter1"/></filter><rect id="b2" x="0" y="0" width="56" height="40" rx="2"/></defs><g fill="none" fill-rule="evenodd"><g transform="translate(2)"><use class="zk-btn__img__shadow" fill="#000" filter="url(#a2)" xlink:href="#b2"/><use class="zk-btn__img__bg" fill="#008522" xlink:href="#b2"/></g><g fill-rule="nonzero" fill="#FFF"><path class="zk-btn__img__logo" d="M13.975 11.75v2.325h13.95l2.325-2.325zM28.825 16.475h-3.3L23.2 18.8h5.625zM24.1 21.2h-3.3l-2.325 2.325H24.1zM30.025 25.925h-13.95L13.75 28.25h16.275z"/></g></g></svg>
                </a>
            <?php } ?>
        </li>
    </ul>
</nav>
<main class="container">
    <?php if ($message) { ?>
        <p class="alert alert-primary" role="alert"><?php echo $message; ?></p>
    <?php } ?>
    <h1>Home</h1>
    <?php if ($currentUser) { ?>
        <p>Welcome back, <?php if (isset($currentUser['name'])) { echo $currentUser['name']; } ?> .</p>

        <h4>User Info from ZenKey</h4>
        <pre><code>sub: <?php echo isset($currentUser['sub']) ? $currentUser['sub'] : 'unknown'; ?> 
name: <?php echo isset($currentUser['name']) ? $currentUser['name'] : 'unknown'; ?> 
email: <?php echo isset($currentUser['email']) ? $currentUser['email'] : 'unknown'; ?> 
email_verified: <?php echo isset($currentUser['email_verified']) ? json_encode($currentUser['email_verified']) : 'unknown'; ?> 
phone: <?php echo isset($currentUser['phone_number']) ? $currentUser['phone_number'] : (isset($currentUser['phone']) ? $currentUser['phone'] : 'unknown'); ?> 
phone_number_verified: <?php echo isset($currentUser['phone_number_verified']) ? json_encode($currentUser['phone_number_verified']) : 'unknown'; ?> 
postal_code: <?php echo isset($currentUser['postal_code']) ? $currentUser['postal_code'] : 'unknown'; ?> 
        </code>
        </pre>

        <form method="POST" action="/authorize-transaction.php">
            <h3>Send Money</h3>
            <div class="form-group"><label for="amount">Amount</label>
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text">$</span>
                    </div>
                    <input type="text" class="form-control" name="amount" id="amount" value="20.00">
                </div>
            </div>
            <div class="form-group"><label for="recipient">Recipient</label><input class="form-control" id="recipient" type="text" value="John Doe" readonly="readonly" name="recipient" /></div>
            <div class="form-group">
              <button type="submit" class="zk-btn zk-btn--continue">
                <span class="zk-sr-only">Continue with ZenKey</span>
                <svg class="zk-btn__img" height="44" width="220" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><defs><filter id="zk-btn-color-shadow" width="103.7%" x="-1.9%" y="-5%"><feOffset dy="2" in="SourceAlpha" result="shadowOffsetOuter1"></feOffset><feGaussianBlur in="shadowOffsetOuter1" result="shadowBlurOuter1" stdDeviation="1"></feGaussianBlur><feColorMatrix in="shadowBlurOuter1" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.24 0"></feColorMatrix></filter><rect id="zk-btn-color-bg" height="40" rx="2" width="216"></rect></defs><g fill="none" fill-rule="evenodd" transform="translate(2)"><use class="zk-btn__img__shadow" fill="#000" filter="url(#zk-btn-color-shadow)" xlink:href="#zk-btn-color-bg"></use><use class="zk-btn__img__bg" fill="#008522" xlink:href="#zk-btn-color-bg"></use><g fill="#fff" fill-rule="nonzero"><path class="zk-btn__img__cta" d="M56.586 25.154c2.464 0 4.242-1.274 4.494-3.752h-2.226c-.182 1.288-1.05 1.918-2.296 1.918-1.593 0-2.535-1.12-2.6-3.057l-.004-.331c0-2.072 1.008-3.304 2.59-3.304 1.26 0 1.932.644 2.1 1.848h2.324c-.238-2.464-2.03-3.64-4.438-3.64-2.767 0-4.858 1.964-4.966 4.872l-.004.35c0 3.08 1.75 5.096 5.026 5.096zm9.43 0c2.199 0 3.865-1.47 3.865-3.78v-.112c0-2.282-1.652-3.752-3.85-3.752s-3.864 1.498-3.864 3.794v.112c0 2.282 1.666 3.738 3.85 3.738zm.015-1.54c-1.148 0-1.792-.84-1.792-2.24v-.112c0-1.4.672-2.212 1.792-2.212 1.082 0 1.73.765 1.788 2.053l.004.285c0 1.386-.658 2.226-1.792 2.226zM71.457 25h2.03v-4.228c0-1.078.659-1.61 1.527-1.61.895 0 1.287.476 1.287 1.47V25h2.03v-4.662c0-1.96-1.022-2.828-2.464-2.828-1.218 0-2.016.602-2.38 1.33v-1.162h-2.03zm11.503.14c.546 0 .952-.098 1.232-.196v-1.568a1.89 1.89 0 01-.77.14c-.518 0-.812-.28-.812-.868V19.12h1.54v-1.442h-1.54v-1.582h-2.016v1.582h-.938v1.442h.938v3.71c0 1.526.826 2.31 2.366 2.31zm3.873-8.456c.658 0 1.162-.476 1.162-1.106s-.504-1.106-1.162-1.106c-.644 0-1.148.476-1.148 1.106s.504 1.106 1.148 1.106zM85.839 25h2.016v-7.322h-2.016zm4.097 0h2.03v-4.228c0-1.078.658-1.61 1.526-1.61.896 0 1.288.476 1.288 1.47V25h2.03v-4.662c0-1.96-1.022-2.828-2.464-2.828-1.218 0-2.016.602-2.38 1.33v-1.162h-2.03zm11.264.154c1.205 0 1.947-.56 2.325-1.302V25h2.015v-7.322h-2.015v4.298c0 1.078-.673 1.61-1.527 1.61-.867 0-1.273-.476-1.273-1.47v-4.438h-2.016v4.718c0 1.918 1.063 2.758 2.491 2.758zm9.725 0c2.002 0 3.262-.882 3.5-2.464h-1.918c-.126.616-.588 1.022-1.526 1.022-1.106 0-1.764-.7-1.82-1.918h5.278v-.56c0-2.618-1.68-3.724-3.57-3.724-2.128 0-3.766 1.498-3.766 3.808v.112c0 2.338 1.61 3.724 3.822 3.724zm-1.736-4.606c.154-1.036.77-1.638 1.68-1.638.966 0 1.526.532 1.596 1.638zM120.87 25h2.016l1.344-4.592L125.475 25h1.988l2.31-7.322h-1.946l-1.386 4.928-1.288-4.928h-1.666l-1.414 4.928-1.302-4.928h-2.128zm11.279-8.316c.658 0 1.162-.476 1.162-1.106s-.504-1.106-1.162-1.106c-.644 0-1.148.476-1.148 1.106s.504 1.106 1.148 1.106zM131.154 25h2.016v-7.322h-2.016zm6.77.14c.546 0 .952-.098 1.232-.196v-1.568a1.89 1.89 0 01-.77.14c-.518 0-.812-.28-.812-.868V19.12h1.54v-1.442h-1.54v-1.582h-2.016v1.582h-.938v1.442h.938v3.71c0 1.526.826 2.31 2.366 2.31zm2.823-.14h2.03v-4.228c0-1.078.658-1.61 1.526-1.61.896 0 1.288.476 1.288 1.47V25h2.03v-4.662c0-1.96-1.022-2.828-2.464-2.828-1.218 0-2.016.602-2.38 1.33v-4.48h-2.03zm11.511 0h7.813v-1.778h-4.508l4.676-7.966v-.266h-7.448v1.764h4.242l-4.774 7.966zm12.665.154c2.002 0 3.262-.882 3.5-2.464h-1.918c-.126.616-.588 1.022-1.526 1.022-1.106 0-1.764-.7-1.82-1.918h5.278v-.56c0-2.618-1.68-3.724-3.57-3.724-2.128 0-3.766 1.498-3.766 3.808v.112c0 2.338 1.61 3.724 3.822 3.724zm-1.736-4.606c.154-1.036.77-1.638 1.68-1.638.966 0 1.526.532 1.596 1.638zM169.986 25h2.03v-4.228c0-1.078.658-1.61 1.526-1.61.896 0 1.288.476 1.288 1.47V25h2.03v-4.662c0-1.96-1.022-2.828-2.464-2.828-1.218 0-2.016.602-2.38 1.33v-1.162h-2.03zm9.01 0h2.269v-4.494L184.723 25h2.646l-4.158-5.278 3.962-4.732h-2.408l-3.5 4.312V14.99h-2.268zm12.442.154c2.002 0 3.262-.882 3.5-2.464h-1.918c-.126.616-.588 1.022-1.526 1.022-1.106 0-1.764-.7-1.82-1.918h5.278v-.56c0-2.618-1.68-3.724-3.57-3.724-2.128 0-3.766 1.498-3.766 3.808v.112c0 2.338 1.61 3.724 3.822 3.724zm-1.736-4.606c.154-1.036.77-1.638 1.68-1.638.966 0 1.526.532 1.596 1.638zm7.47 6.944l1.288-3.234-2.912-6.58h2.184l1.778 4.396 1.638-4.396h1.946l-3.962 9.814zM11.975 11.75v2.325h13.95l2.325-2.325zM26.825 16.475h-3.3L21.2 18.8h5.625zM22.1 21.2h-3.3l-2.325 2.325H22.1zM28.025 25.925h-13.95L11.75 28.25h16.275z"></path></g></g></svg>
              </button>
            </div>
        </form>
    <?php } else { ?>
        <p>Welcome. Please sign in to continue.</p>
        <p>
            <a href="/auth.php" class="zk-btn">
                <span class="zk-sr-only">Sign in with ZenKey</span>
                <svg class="zk-btn__img" width="202" height="44" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><defs><filter x="-2%" y="-5%" width="104%" height="120%" filterUnits="objectBoundingBox" id="a"><feOffset dy="2" in="SourceAlpha" result="shadowOffsetOuter1"/><feGaussianBlur stdDeviation="1" in="shadowOffsetOuter1" result="shadowBlurOuter1"/><feColorMatrix values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.24 0" in="shadowBlurOuter1"/></filter><rect id="b" x="0" y="0" width="198" height="40" rx="2"/></defs><g fill="none" fill-rule="evenodd"><g transform="translate(2)"><use class="zk-btn__img__shadow" fill="#000" filter="url(#a)" xlink:href="#b"/><use fill="#008522" xlink:href="#b" class="zk-btn__img__bg"/></g><path class="zk-btn__img__cta" d="M56.578 25.154c2.366 0 3.822-1.414 3.822-3.192 0-2.072-1.246-2.73-3.668-3.038-1.54-.224-1.862-.56-1.862-1.274 0-.672.504-1.134 1.498-1.134s1.526.42 1.666 1.33h2.086c-.182-1.988-1.498-2.996-3.752-2.996-2.212 0-3.64 1.274-3.64 2.982 0 1.932 1.05 2.702 3.612 3.038 1.47.224 1.89.504 1.89 1.302 0 .784-.658 1.33-1.652 1.33-1.484 0-1.862-.742-1.96-1.638h-2.17c.126 2.002 1.386 3.29 4.13 3.29zm6.547-8.47c.658 0 1.162-.476 1.162-1.106 0-.63-.504-1.106-1.162-1.106-.644 0-1.148.476-1.148 1.106 0 .63.504 1.106 1.148 1.106zM62.13 25h2.016v-7.322H62.13V25zm7.344 2.702c2.409 0 3.85-1.106 3.865-3.276v-6.748h-2.017v1.092c-.42-.742-1.133-1.26-2.323-1.26-1.82 0-3.22 1.47-3.22 3.57v.098c0 2.17 1.413 3.5 3.192 3.5a2.747 2.747 0 002.352-1.358v1.106c0 1.148-.617 1.792-1.849 1.792-1.035 0-1.497-.42-1.623-1.064h-2.017c.196 1.512 1.274 2.548 3.64 2.548zm.099-4.55c-1.008 0-1.722-.756-1.722-1.974v-.112c0-1.204.63-2.016 1.763-2.016 1.107 0 1.764.756 1.764 2.002v.098c0 1.246-.742 2.002-1.806 2.002zM75.35 25h2.03v-4.228c0-1.078.658-1.61 1.526-1.61.896 0 1.288.476 1.288 1.47V25h2.03v-4.662c0-1.96-1.022-2.828-2.464-2.828-1.218 0-2.016.602-2.38 1.33v-1.162h-2.03V25zm13.206-8.316c.658 0 1.162-.476 1.162-1.106 0-.63-.504-1.106-1.162-1.106-.644 0-1.148.476-1.148 1.106 0 .63.504 1.106 1.148 1.106zM87.562 25h2.016v-7.322h-2.016V25zm4.097 0h2.03v-4.228c0-1.078.658-1.61 1.526-1.61.895 0 1.287.476 1.287 1.47V25h2.03v-4.662c0-1.96-1.022-2.828-2.463-2.828-1.219 0-2.017.602-2.38 1.33v-1.162h-2.03V25zm13.709 0h2.016l1.344-4.592L109.974 25h1.988l2.31-7.322h-1.946l-1.386 4.928-1.288-4.928h-1.666l-1.414 4.928-1.302-4.928h-2.128L105.368 25zm11.279-8.316c.658 0 1.162-.476 1.162-1.106 0-.63-.504-1.106-1.162-1.106-.644 0-1.148.476-1.148 1.106 0 .63.504 1.106 1.148 1.106zM115.653 25h2.016v-7.322h-2.016V25zm6.77.14c.546 0 .953-.098 1.233-.196v-1.568a1.89 1.89 0 01-.77.14c-.518 0-.813-.28-.813-.868V19.12h1.54v-1.442h-1.54v-1.582h-2.015v1.582h-.939v1.442h.939v3.71c0 1.526.825 2.31 2.365 2.31zm2.823-.14h2.03v-4.228c0-1.078.658-1.61 1.526-1.61.896 0 1.288.476 1.288 1.47V25h2.03v-4.662c0-1.96-1.022-2.828-2.464-2.828-1.218 0-2.016.602-2.38 1.33v-4.48h-2.03V25zm11.512 0h7.812v-1.778h-4.508l4.676-7.966v-.266h-7.448v1.764h4.242l-4.774 7.966V25zm12.665.154c2.001 0 3.262-.882 3.5-2.464h-1.918c-.126.616-.588 1.022-1.526 1.022-1.107 0-1.765-.7-1.82-1.918h5.278v-.56c0-2.618-1.68-3.724-3.57-3.724-2.129 0-3.766 1.498-3.766 3.808v.112c0 2.338 1.61 3.724 3.822 3.724zm-1.737-4.606c.154-1.036.77-1.638 1.68-1.638.966 0 1.526.532 1.596 1.638h-3.275zm6.8 4.452h2.03v-4.228c0-1.078.657-1.61 1.525-1.61.896 0 1.288.476 1.288 1.47V25h2.03v-4.662c0-1.96-1.022-2.828-2.464-2.828-1.218 0-2.016.602-2.38 1.33v-1.162h-2.03V25zm9.01 0h2.268v-4.494L169.222 25h2.646l-4.158-5.278 3.962-4.732h-2.408l-3.5 4.312V14.99h-2.268V25zm12.44.154c2.003 0 3.263-.882 3.5-2.464h-1.917c-.126.616-.588 1.022-1.526 1.022-1.106 0-1.764-.7-1.82-1.918h5.278v-.56c0-2.618-1.68-3.724-3.57-3.724-2.128 0-3.766 1.498-3.766 3.808v.112c0 2.338 1.61 3.724 3.822 3.724zm-1.735-4.606c.154-1.036.77-1.638 1.68-1.638.966 0 1.526.532 1.596 1.638H174.2zm7.47 6.944l1.288-3.234-2.911-6.58h2.184l1.778 4.396 1.638-4.396h1.946l-3.963 9.814h-1.96z" fill="#FFF" fill-rule="nonzero"/><g fill-rule="nonzero" fill="#FFF"><path class="zk-btn__img__logo" d="M13.975 11.75v2.325h13.95l2.325-2.325zM28.825 16.475h-3.3L23.2 18.8h5.625zM24.1 21.2h-3.3l-2.325 2.325H24.1zM30.025 25.925h-13.95L13.75 28.25h16.275z"/></g></g></svg>
            </a>
        </p>
    <?php } ?>
</main>
</body>
</html>
