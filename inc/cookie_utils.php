<?php
if (!function_exists('setSecureCookie')) {
    function setSecureCookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = true, $httponly = true, $samesite = 'Lax') {
        if (PHP_VERSION_ID < 70300) {
            setcookie($name, $value, $expire, $path . '; HttpOnly; Secure; SameSite=' . $samesite, $domain, $secure, $httponly);
        } else {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]);
        }
    }
}