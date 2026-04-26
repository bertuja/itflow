<?php

defined('FROM_POST_HANDLER') || die("Direct file access is not allowed");

if (isset($_POST['edit_zoho_settings'])) {

    validateCSRFToken($_POST['csrf_token']);

    $config_zoho_client_id = sanitizeInput($_POST['config_zoho_client_id']);
    $config_zoho_org_id    = sanitizeInput($_POST['config_zoho_org_id']);

    // Only update secret/refresh token if a new value was provided
    $secret_update = '';
    if (!empty($_POST['config_zoho_client_secret'])) {
        $secret = sanitizeInput($_POST['config_zoho_client_secret']);
        $secret_update = ", config_zoho_client_secret = '$secret'";
    }

    $refresh_update = '';
    if (!empty($_POST['config_zoho_refresh_token'])) {
        $refresh = sanitizeInput($_POST['config_zoho_refresh_token']);
        $refresh_update = ", config_zoho_refresh_token = '$refresh'";
        // Invalidate cached token when refresh token changes
        $refresh_update .= ", config_zoho_access_token = NULL, config_zoho_access_token_expires_at = NULL";
    }

    mysqli_query($mysqli, "UPDATE settings SET
        config_zoho_client_id = '$config_zoho_client_id',
        config_zoho_org_id = '$config_zoho_org_id'
        $secret_update
        $refresh_update
        WHERE company_id = 1");

    logAction("Settings", "Edit", "$session_name edited Zoho Desk settings");

    flash_alert("Zoho Desk settings updated");

    redirect();
}
