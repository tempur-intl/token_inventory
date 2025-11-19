<?php
/**
 * Authentication Router
 * Selects between Azure AD (Entra) or LDAP (Local AD) authentication
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine authentication method from environment variable
// Options: 'azure' (Entra ID), 'ldap' (Local AD), or 'none' (no authentication)
$authMethod = strtolower(getenv('AUTH_METHOD') ?: 'none');

// Load the appropriate authentication module
switch ($authMethod) {
    case 'azure':
    case 'entra':
        // Azure AD / Entra ID authentication
        if (!file_exists(__DIR__ . '/azure-auth.php')) {
            die('Error: azure-auth.php not found');
        }
        require_once __DIR__ . '/azure-auth.php';
        break;

    case 'ldap':
    case 'ad':
        // Local Active Directory (LDAP) authentication
        if (!file_exists(__DIR__ . '/ldap-auth.php')) {
            die('Error: ldap-auth.php not found');
        }
        require_once __DIR__ . '/ldap-auth.php';
        break;

    case 'none':
    case 'disabled':
        // No authentication (not recommended for production)
        error_log('WARNING: Authentication is disabled! This should only be used for development.');
        break;

    default:
        die('Error: Invalid AUTH_METHOD. Must be "azure", "ldap", or "none".');
}

/**
 * Handle logout for any authentication method
 */
function handleAuthLogout() {
    global $authMethod;

    switch ($authMethod) {
        case 'azure':
        case 'entra':
            if (function_exists('handleAzureLogout')) {
                handleAzureLogout();
            }
            break;

        case 'ldap':
        case 'ad':
            if (function_exists('handleLdapLogout')) {
                handleLdapLogout();
            }
            break;
    }
}

/**
 * Check if user is authenticated (any method)
 */
function isAuthenticated() {
    global $authMethod;

    switch ($authMethod) {
        case 'azure':
        case 'entra':
            return function_exists('isAzureAuthenticated') && isAzureAuthenticated();

        case 'ldap':
        case 'ad':
            return function_exists('isLdapAuthenticated') && isLdapAuthenticated();

        case 'none':
        case 'disabled':
            return true; // Always authenticated when auth is disabled

        default:
            return false;
    }
}

/**
 * Get authenticated user information
 */
function getAuthenticatedUser() {
    global $authMethod;

    switch ($authMethod) {
        case 'azure':
        case 'entra':
            return $_SESSION['azure_user'] ?? null;

        case 'ldap':
        case 'ad':
            return $_SESSION['ldap_user'] ?? null;

        case 'none':
        case 'disabled':
            return ['name' => 'Guest', 'email' => '', 'username' => 'guest'];

        default:
            return null;
    }
}

/**
 * Get authentication method display name
 */
function getAuthMethodName() {
    global $authMethod;

    switch ($authMethod) {
        case 'azure':
        case 'entra':
            return 'Azure AD';

        case 'ldap':
        case 'ad':
            return 'Active Directory';

        case 'none':
        case 'disabled':
            return 'None (Development)';

        default:
            return 'Unknown';
    }
}
