<?php
/**
 * Configuration OAuth Google Simple
 */

// Configuration Google OAuth
define('GOOGLE_CLIENT_ID', '1023247001986-eo27ecl5t85db6d0o04crgdnie35oc8e.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'https://smartcoreexpress.com/auth/google-callback.php');

// URLs de base
define('BASE_URL', 'https://smartcoreexpress.com');

/**
 * Générer l'URL d'autorisation Google
 */
function getGoogleAuthUrl() {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'scope' => 'openid email profile',
        'response_type' => 'code',
        'state' => $state,
        'access_type' => 'offline',
        'prompt' => 'consent'
    ];
    
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Vérifier si Google OAuth est configuré
 */
function isGoogleConfigured() {
    return defined('GOOGLE_CLIENT_ID') && 
           !empty(GOOGLE_CLIENT_ID) &&
           defined('GOOGLE_CLIENT_SECRET') && 
           !empty(GOOGLE_CLIENT_SECRET);
}
?>