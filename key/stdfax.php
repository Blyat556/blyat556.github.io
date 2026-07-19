<?php
// ============================================
// quenhook.cfd - API Configuration
// ============================================
// 1. Set a STRONG $AdminKey (used for admin API calls)
// 2. Set $WebhookUrl to a Discord webhook for activity logs
// 3. Add your server IP to $whitelisted_ips for admin endpoints
// ============================================

$whitelisted_ips = ['127.0.0.1'];
$AdminKey = "CHANGE_ME_TO_A_RANDOM_STRING";
$WebhookUrl = "";

// Rate limiting for user-facing HWID reset (seconds between resets)
$HWID_RESET_COOLDOWN = 604800; // 7 days
?>
