<?php
/**
 * Azure AD OAuth2 Authentication
 * This file handles Azure AD authentication for the application
 */

session_start();

// Configuration from environment variables
$azureConfig = [
    'tenant_id' => getenv('AZURE_AD_TENANT_ID') ?: '',
    'client_id' => getenv('AZURE_AD_CLIENT_ID') ?: '',
    'client_secret' => getenv('AZURE_AD_CLIENT_SECRET') ?: '',
    'redirect_uri' => getenv('AZURE_AD_REDIRECT_URI') ?: '',
    'allowed_groups' => getenv('AZURE_AD_ALLOWED_GROUPS') ? explode(',', getenv('AZURE_AD_ALLOWED_GROUPS')) : [],
];

// Disable Azure AD if not configured
$azureAdEnabled = !empty($azureConfig['tenant_id']) && !empty($azureConfig['client_id']) && !empty($azureConfig['client_secret']);

/**
 * Check if user is authenticated with Azure AD
 */
function isAzureAuthenticated() {
    return isset($_SESSION['azure_user']) && isset($_SESSION['azure_token']);
}

/**
 * Get Azure AD authorization URL
 */
function getAzureAuthUrl($config) {
    $params = [
        'client_id' => $config['client_id'],
        'response_type' => 'code',
        'redirect_uri' => $config['redirect_uri'],
        'response_mode' => 'query',
        'scope' => 'openid profile email User.Read',
        'state' => bin2hex(random_bytes(16))
    ];

    $_SESSION['oauth_state'] = $params['state'];

    return "https://login.microsoftonline.com/{$config['tenant_id']}/oauth2/v2.0/authorize?" . http_build_query($params);
}

/**
 * Exchange authorization code for access token
 */
function getAzureAccessToken($config, $code) {
    $url = "https://login.microsoftonline.com/{$config['tenant_id']}/oauth2/v2.0/token";

    $data = [
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'code' => $code,
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Azure AD token error: " . $response);
        return null;
    }

    return json_decode($response, true);
}

/**
 * Get user info from Microsoft Graph
 */
function getAzureUserInfo($accessToken) {
    $url = "https://graph.microsoft.com/v1.0/me";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Azure AD user info error: " . $response);
        return null;
    }

    return json_decode($response, true);
}

/**
 * Get user's group memberships
 */
function getAzureUserGroups($accessToken) {
    $url = "https://graph.microsoft.com/v1.0/me/memberOf";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Azure AD groups error: " . $response);
        return [];
    }

    $data = json_decode($response, true);
    $groups = [];

    foreach ($data['value'] ?? [] as $group) {
        if (isset($group['id'])) {
            $groups[] = $group['id'];
        }
    }

    return $groups;
}

/**
 * Check if user is in allowed groups
 */
function isUserInAllowedGroups($userGroups, $allowedGroups) {
    if (empty($allowedGroups)) {
        return true; // No group restrictions
    }

    return !empty(array_intersect($userGroups, $allowedGroups));
}

/**
 * Handle Azure AD OAuth callback
 */
function handleAzureCallback($config) {
    if (!isset($_GET['code']) || !isset($_GET['state'])) {
        return ['error' => 'Invalid callback parameters'];
    }

    // Verify state to prevent CSRF
    if ($_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
        return ['error' => 'Invalid state parameter'];
    }

    // Exchange code for token
    $tokenData = getAzureAccessToken($config, $_GET['code']);
    if (!$tokenData || !isset($tokenData['access_token'])) {
        return ['error' => 'Failed to get access token'];
    }

    // Get user info
    $userInfo = getAzureUserInfo($tokenData['access_token']);
    if (!$userInfo) {
        return ['error' => 'Failed to get user info'];
    }

    // Check group membership if configured
    if (!empty($config['allowed_groups'])) {
        $userGroups = getAzureUserGroups($tokenData['access_token']);
        if (!isUserInAllowedGroups($userGroups, $config['allowed_groups'])) {
            return ['error' => 'Access denied: User not in allowed groups'];
        }
    }

    // Store user info in session
    $_SESSION['azure_user'] = [
        'id' => $userInfo['id'] ?? '',
        'name' => $userInfo['displayName'] ?? '',
        'email' => $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? '',
        'upn' => $userInfo['userPrincipalName'] ?? ''
    ];
    $_SESSION['azure_token'] = $tokenData['access_token'];
    $_SESSION['azure_token_expires'] = time() + ($tokenData['expires_in'] ?? 3600);

    return ['success' => true];
}

/**
 * Require Azure AD authentication
 */
function requireAzureAuth($config) {
    global $azureAdEnabled;

    // Skip if Azure AD is not configured
    if (!$azureAdEnabled) {
        return;
    }

    // Handle OAuth callback
    if (isset($_GET['code']) && isset($_GET['state'])) {
        $result = handleAzureCallback($config);
        if (isset($result['error'])) {
            die("Authentication Error: " . htmlspecialchars($result['error']));
        }
        // Redirect to remove code from URL
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    // Check if user is authenticated
    if (!isAzureAuthenticated()) {
        // Redirect to Azure AD login
        header('Location: ' . getAzureAuthUrl($config));
        exit;
    }

    // Check if token is expired
    if (isset($_SESSION['azure_token_expires']) && $_SESSION['azure_token_expires'] < time()) {
        // Token expired, require re-authentication
        unset($_SESSION['azure_user']);
        unset($_SESSION['azure_token']);
        header('Location: ' . getAzureAuthUrl($config));
        exit;
    }
}

/**
 * Handle Azure AD logout
 */
function handleAzureLogout() {
    unset($_SESSION['azure_user']);
    unset($_SESSION['azure_token']);
    unset($_SESSION['azure_token_expires']);
}

// Require authentication for this application
requireAzureAuth($azureConfig);
