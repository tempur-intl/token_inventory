<?php
/**
 * Main entry point with Azure AD authentication
 * This file wraps the original application with Azure AD OAuth
 */

// Start session first
session_start();

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);

// Load Azure AD authentication
require_once __DIR__ . '/azure-auth.php';

// Handle Azure AD logout
if (isset($_GET['logout'])) {
    handleAzureLogout();
    session_destroy();
    // Clear credential cookies
    setcookie('tenantId', '', time() - 3600, '/', '', true, true);
    setcookie('clientId', '', time() - 3600, '/', '', true, true);
    header('Location: index.php');
    exit;
}

// Define as web application (not desktop)
define('LOCAL_APP', 0);

// Load the main application
// We'll modify index.php to be included rather than run directly
$_SERVER['SCRIPT_NAME'] = '/index.php'; // Ensure proper routing

// Include the main application logic
// Note: The original index.php will need to be refactored to be include-friendly
require_once __DIR__ . '/app.php';
