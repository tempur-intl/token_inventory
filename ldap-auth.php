<?php
/**
 * Local Active Directory (LDAP) Authentication
 * This file handles on-premises AD authentication for the application
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration from environment variables
$ldapConfig = [
    'hosts' => getenv('LDAP_HOST') ? array_map('trim', explode(',', getenv('LDAP_HOST'))) : [],
    'port' => getenv('LDAP_PORT') ?: '389',
    'use_tls' => getenv('LDAP_USE_TLS') === 'true',
    'base_dn' => getenv('LDAP_BASE_DN') ?: '',
    'bind_dn' => getenv('LDAP_BIND_DN') ?: '',
    'bind_password' => getenv('LDAP_BIND_PASSWORD') ?: '',
    'user_filter' => getenv('LDAP_USER_FILTER') ?: '(sAMAccountName={username})',
    'group_filter' => getenv('LDAP_GROUP_FILTER') ?: '(member={dn})',
    'allowed_groups' => getenv('LDAP_ALLOWED_GROUPS') ? explode(',', getenv('LDAP_ALLOWED_GROUPS')) : [],
    'timeout' => intval(getenv('LDAP_TIMEOUT') ?: '10'),
];

// Disable LDAP if not configured
$ldapEnabled = !empty($ldapConfig['hosts']) && !empty($ldapConfig['base_dn']);

/**
 * Check if user is authenticated with LDAP
 */
function isLdapAuthenticated() {
    return isset($_SESSION['ldap_user']) && isset($_SESSION['ldap_authenticated']);
}

/**
 * Connect to LDAP server (tries multiple hosts with failover)
 */
function connectLdap($config) {
    $protocol = $config['use_tls'] ? 'ldaps' : 'ldap';
    $lastError = '';

    // For LDAPS, disable certificate verification globally (helps with self-signed certs)
    // Must be set before ldap_connect for some PHP versions
    if ($protocol === 'ldaps') {
        ldap_set_option(NULL, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
    }

    // Try each host in order until one succeeds
    foreach ($config['hosts'] as $host) {
        $ldapUri = "{$protocol}://{$host}:{$config['port']}";

        $conn = @ldap_connect($ldapUri);

        if (!$conn) {
            $lastError = "Failed to connect to {$ldapUri}";
            error_log("LDAP: {$lastError}");
            continue;
        }

        // Set LDAP options
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, $config['timeout']);

        // Also set on the connection handle for redundancy
        if ($protocol === 'ldaps') {
            ldap_set_option($conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        }

        if ($config['use_tls'] && $protocol === 'ldap') {
            if (!@ldap_start_tls($conn)) {
                $lastError = "Failed to start TLS on {$ldapUri}";
                error_log("LDAP: {$lastError}");
                ldap_close($conn);
                continue;
            }
        }

        // Successfully connected
        error_log("LDAP: Successfully connected to {$ldapUri}");
        return $conn;
    }

    // All hosts failed
    error_log("LDAP: All hosts failed. Last error: {$lastError}");
    return false;
}

/**
 * Search for user in LDAP
 */
function searchLdapUser($conn, $config, $username) {
    // Bind with service account
    if (!empty($config['bind_dn'])) {
        $bind = @ldap_bind($conn, $config['bind_dn'], $config['bind_password']);
        if (!$bind) {
            error_log("LDAP: Failed to bind with service account: " . ldap_error($conn));
            return false;
        }
    }

    // Build search filter
    $filter = str_replace('{username}', ldap_escape($username, '', LDAP_ESCAPE_FILTER), $config['user_filter']);

    // Search for user
    $search = @ldap_search($conn, $config['base_dn'], $filter, ['dn', 'cn', 'displayName', 'mail', 'sAMAccountName', 'memberOf', 'userPrincipalName']);

    if (!$search) {
        error_log("LDAP: Search failed: " . ldap_error($conn));
        return false;
    }

    $entries = ldap_get_entries($conn, $search);

    if ($entries['count'] === 0) {
        error_log("LDAP: User not found: {$username}");
        return false;
    }

    if ($entries['count'] > 1) {
        error_log("LDAP: Multiple users found for: {$username}");
        return false;
    }

    return $entries[0];
}

/**
 * Authenticate user credentials
 */
function authenticateLdapUser($config, $username, $password) {
    if (empty($username) || empty($password)) {
        return ['success' => false, 'error' => 'Username and password are required'];
    }

    $conn = connectLdap($config);
    if (!$conn) {
        return ['success' => false, 'error' => 'Failed to connect to LDAP server'];
    }

    // Search for user
    $userEntry = searchLdapUser($conn, $config, $username);
    if (!$userEntry) {
        ldap_close($conn);
        return ['success' => false, 'error' => 'Invalid username or password'];
    }

    $userDn = $userEntry['dn'];

    // Attempt to bind as the user (this authenticates them)
    $bind = @ldap_bind($conn, $userDn, $password);

    if (!$bind) {
        error_log("LDAP: Failed to authenticate user: " . ldap_error($conn));
        ldap_close($conn);
        return ['success' => false, 'error' => 'Invalid username or password'];
    }

    // Check group membership if required
    if (!empty($config['allowed_groups'])) {
        $groups = getLdapUserGroups($conn, $config, $userDn);

        if (!isUserInAllowedGroups($groups, $config['allowed_groups'])) {
            ldap_close($conn);
            return ['success' => false, 'error' => 'Access denied: User not in allowed groups'];
        }
    }

    // Extract user information
    $userInfo = [
        'dn' => $userDn,
        'username' => $userEntry['samaccountname'][0] ?? $username,
        'name' => $userEntry['displayname'][0] ?? $userEntry['cn'][0] ?? $username,
        'email' => $userEntry['mail'][0] ?? $userEntry['userprincipalname'][0] ?? '',
        'upn' => $userEntry['userprincipalname'][0] ?? ''
    ];

    ldap_close($conn);

    return ['success' => true, 'user' => $userInfo];
}

/**
 * Get user's group memberships
 */
function getLdapUserGroups($conn, $config, $userDn) {
    $groups = [];

    // Get groups from memberOf attribute
    $search = @ldap_read($conn, $userDn, '(objectClass=*)', ['memberOf']);

    if ($search) {
        $entry = ldap_get_entries($conn, $search);

        if (isset($entry[0]['memberof'])) {
            for ($i = 0; $i < $entry[0]['memberof']['count']; $i++) {
                $groupDn = $entry[0]['memberof'][$i];

                // Extract CN from DN
                if (preg_match('/^CN=([^,]+)/', $groupDn, $matches)) {
                    $groups[] = $matches[1];
                }

                // Also store full DN
                $groups[] = $groupDn;
            }
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

    // Check both CN and full DN matches
    foreach ($allowedGroups as $allowedGroup) {
        $allowedGroup = trim($allowedGroup);

        foreach ($userGroups as $userGroup) {
            if (stripos($userGroup, $allowedGroup) !== false) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Show LDAP login form
 */
function showLdapLoginForm($error = '') {
    $errorHtml = $error ? '<div class="alert alert-danger">' . htmlspecialchars($error) . '</div>' : '';

    echo '<!DOCTYPE html>
<html>
<head>
    <title>Login - Token Inventory</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="shortcut icon" href="favico.png">
    <style>
        body {
            background-image: url("assets/bg.png");
            background-attachment: fixed;
            background-repeat: no-repeat;
            background-size: cover;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header img {
            width: 48px;
            height: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="favico.png" alt="Logo">
            <h4>TOTP Tokens Inventory</h4>
            <p class="text-muted">Sign in with your Active Directory credentials</p>
        </div>
        ' . $errorHtml . '
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                       placeholder="Enter your username" required autofocus>
                <small class="form-text text-muted">Use your domain username (e.g., jdoe)</small>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                       placeholder="Enter your password" required>
            </div>
            <button type="submit" name="ldap_login" class="btn btn-primary btn-block">Sign In</button>
        </form>
    </div>
    <div style="position: fixed; bottom: 10px; right: 10px; font-size: 14px; color: #555;">
        &copy; Token2
    </div>
</body>
</html>';
    exit;
}

/**
 * Handle LDAP login
 */
function handleLdapLogin($config) {
    if (isset($_POST['ldap_login'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = authenticateLdapUser($config, $username, $password);

        if ($result['success']) {
            // Store user info in session
            $_SESSION['ldap_user'] = $result['user'];
            $_SESSION['ldap_authenticated'] = true;
            $_SESSION['ldap_login_time'] = time();

            // Redirect to remove POST data
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        } else {
            showLdapLoginForm($result['error']);
        }
    }
}

/**
 * Require LDAP authentication
 */
function requireLdapAuth($config) {
    global $ldapEnabled;

    // Skip if LDAP is not configured
    if (!$ldapEnabled) {
        return;
    }

    // Check for PHP LDAP extension
    if (!function_exists('ldap_connect')) {
        die('Error: PHP LDAP extension is not installed. Please install php-ldap.');
    }

    // Handle login form submission
    handleLdapLogin($config);

    // Check if user is authenticated
    if (!isLdapAuthenticated()) {
        showLdapLoginForm();
    }

    // Check session timeout (default: 8 hours)
    $timeout = 28800; // 8 hours in seconds
    if (isset($_SESSION['ldap_login_time']) && (time() - $_SESSION['ldap_login_time']) > $timeout) {
        unset($_SESSION['ldap_user']);
        unset($_SESSION['ldap_authenticated']);
        showLdapLoginForm('Session expired. Please log in again.');
    }
}

/**
 * Handle LDAP logout
 */
function handleLdapLogout() {
    unset($_SESSION['ldap_user']);
    unset($_SESSION['ldap_authenticated']);
    unset($_SESSION['ldap_login_time']);
}

// Require authentication for this application
requireLdapAuth($ldapConfig);
