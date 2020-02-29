<?php
header('Access-Control-Allow-Origin: *');
include("inc/global-cron.php");
require_once("inc/functions.php");

$db=new connection();
$db->db_conn();

$get_shop = $_GET['shop'];
$pieces = explode(".", $get_shop);
$shop_name = $pieces[0];

$s = "select * from members where shopify_shop_name = \"".escape_string($shop_name)."\"  order by id desc limit 1";

$qry= $db->db_query($s);
$nums= $qry->num_rows;
if ($nums > 0 ) { // exist

  $row= $db->db_fetch_array($qry);

  $id                       = $row->id;
  $shopify_allow_shipping   = $row->shopify_allow_shipping;
  $shopify_3h_delivery      = $row->shopify_3h_delivery;
  $shopify_ndd              = $row->shopify_ndd;
  $shopify_allow_inventory  = $row->shopify_allow_inventory;
  $shopify_access_token     = $row->shopify_access_token;

} else { //if ($nums > 0 )
    die("<br>Sorry member's record not found");
}


$post_file = "postcodes.txt";
if(file_exists($post_file)) {
    $contents = file_get_contents($post_file);
    $lines = explode("\n", $contents); // this is your array of words
    $postCodeFrom = $lines[0];
} else {
    $postCodeFrom = '';
}

if(isset($_POST['submit'])) {
    $new_postCodeFrom = $_POST['postCodeFrom'];
    $rlt = file_put_contents($post_file, $new_postCodeFrom);
    if($rlt) {
        $postCodeFrom = $_POST['postCodeFrom'];
    } else {
        echo "<span>Error. Can not save new post code.</span>";
        die();
    }
}

$input = file_get_contents('php://input');
$filename = "log/index_php_input_" . date('YmdHis'); 
file_put_contents($filename, $input);


foreach ($_GET as $key => $value) {
    echo "<br>".__LINE__."/ _GET as $key => $value";
}

// Create Carrier service via api

$params = array(
    "carrier_service" => array(
        "name" => "Shipping Rate Provider",
        "callback_url" => "https://www.zeptoexpress.com/app/shopify/ship_rate_provider.php",
        "service_discovery" => true
    )
);
shopify_call($shopify_access_token, $shop_name, "/admin/api/2020-01/carrier_services.json", $params, 'POST');

 
// Create Webhook for orders/paid
echo "<hr>set webhooks<hr>";
$params = array(
    "webhook" => array(
        "topic"=>"orders/paid",
        "address"=> "https://www.zeptoexpress.com/app/shopify/zepto_order.php",
        "format"=> "json"
    )
);
$hook = shopify_call($shopify_access_token, $shop_name, "/admin/api/2020-01/webhooks.json", $params, 'POST');

echo "<pre>";
print_r($hook);
echo "<hr>";
echo $shop_name;
echo "<hr>";


// mark as shopify activated
$s = "update members set shopify_activated = '1' where shopify_shop_name = \"".escape_string($shop_name)."\" ";
$qry= $db->db_query($s);



echo "<hr>shop detail : shopify_call_xheader<hr>";
$var = shopify_call_xheader($shopify_access_token, $shop_name, "/admin/api/2020-01/shop.json",null, 'GET');
echo "<pre>";
print_r($var);
echo "<hr>";

$var_json = json_decode($var);
$shop_zip = $var_json->shop->zip;
echo "/$shop_zip/";;


echo "<hr> product <hr>";
$var = shopify_call_xheader($shopify_access_token, $shop_name, "/admin/api/2019-10/products.json",null, 'GET');
echo "<pre>";
echo $var;
echo "</pre>";


echo "<hr>inventory<hr>";
$var2 = shopify_call_xheader($shopify_access_token, $shop_name, "/admin/api/2020-01/inventory_levels.json?location_ids=38023397509",null, 'GET');
echo "<pre>";
echo $var2;
echo "</pre>";


echo "<hr>item details<hr>";
$var3 = shopify_call_xheader($shopify_access_token, $shop_name, "/admin/api/2020-01/inventory_items.json?ids=33459836813445",null, 'GET');
echo "<pre>";
echo $var3 ;
echo "</pre>";


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ZeptoApp</title>

    <!-- Link Bootstrap CSS -->
    <link rel="stylesheet" href="assets/lib/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <!-- Script -->
    <script src="assets/lib/jquery/jquery.min.js"></script>
    <script src="assets/lib/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>