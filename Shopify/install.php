<?php
header('Access-Control-Allow-Origin: *');
include("inc/global-cron.php");
require_once("inc/functions.php");

$db=new connection();
$db->db_conn();

// Set variables for our request
$shop = trim($_GET['shop']);

$s = "select * from members where shopify_shop_name = \"".escape_string($shop)."\"  order by id desc limit 1";
$qry= $db->db_query($s);
$nums= $qry->num_rows;
if ($nums > 0 ) { // exist

  $row= $db->db_fetch_array($qry);

  $id       = $row->id;
  $shopify_allow_shipping = $row->shopify_allow_shipping;
  $shopify_3h_delivery = $row->shopify_3h_delivery;
  $shopify_ndd       			= $row->shopify_ndd;
  $shopify_allow_inventory = $row->shopify_allow_inventory;
  $shopify_access_token     = $row->shopify_access_token;

} else { //if ($nums > 0 ) 
	die("<br>Sorry member's record not found");
}

$scopes = "read_orders, write_orders";
if($shopify_allow_shipping) {
	$scopes .= ", read_shipping, write_shipping, read_checkouts, write_checkouts";
}	
if($shopify_allow_inventory) {
	$scopes .= ", read_inventory, write_inventory, read_products, write_products";
}	

$redirect_uri = "https://www.zeptoexpress.com/app/shopify/generate_token.php";

// Build install/approval URL to redirect to
$install_url = "https://" . $shop . ".myshopify.com/admin/oauth/authorize?client_id=" . SHOPIFY_API_KEY . "&scope=" . $scopes . "&redirect_uri=" . urlencode($redirect_uri);

// Redirect
header("Location: " . $install_url);
die();