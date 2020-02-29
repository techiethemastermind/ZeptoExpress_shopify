<?php

// Get config
require_once('inc/config.php');

// Set variables for our request
$shop = $_GET['shop'];
$scopes = "write_shipping, write_checkouts, write_orders";
$redirect_uri = $appConf['app_url'] . '/generate_token.php';

// Build install/approval URL to redirect to
$install_url = "https://" . $shop . ".myshopify.com/admin/oauth/authorize?client_id=" . $appConf['api_key'] . "&scope=" . $scopes . "&redirect_uri=" . urlencode($redirect_uri);

// Redirect
header("Location: " . $install_url);
die();