<?php
header('Access-Control-Allow-Origin: *');
include("inc/global-cron.php");
require_once("inc/functions.php");

$db=new connection();
$db->db_conn();

$data = file_get_contents('php://input');
$data_array = json_decode($data, true);
file_put_contents('data.txt', print_r($data_array, true)); 	// for debug


$shop_name = $data_array['line_items'][0]['origin_location']['name'];

// get access_token
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

} //if ($nums > 0 ) 

$shop_data = shopify_call_xheader($shopify_access_token, $shop_name, "/admin/api/2020-01/shop.json", 'GET');
file_put_contents('shop_data.txt', print_r( json_decode($shop_data ,true) , true ) ); 										// for debug
$shop_data = json_decode($shop_data, true);

$sender_fullname = $shop_data['shop']['name'];
$sender_email = $shop_data['shop']['email'];
$sender_phone = $shop_data['shop']['phone'];

$sender_address = '';
$sender_address = $shop_data['shop']['address1'];
$sender_address .=  ( substr($sender_address,-1)==',' ? substr($sender_address,0,-1) :'' );
$sender_address .= ($sender_address? ',':'').$shop_data['shop']['address2'];
$sender_address .=  ( substr($sender_address,-1)==',' ? substr($sender_address,0,-1) :'' );
$sender_address .= ($shop_data['shop']['zip'].$shop_data['shop']['city']? ',':'').$shop_data['shop']['zip'].' '.$shop_data['shop']['city'];
$sender_address .=  ( substr($sender_address,-1)==',' ? substr($sender_address,0,-1) :'' );
$sender_address .= ( $shop_data['shop']['province']? ',':'').$shop_data['shop']['province'];
$sender_address .=  ( substr($sender_address,-1)==',' ? substr($sender_address,0,-1) :'' );
$sender_address .= ( $shop_data['shop']['country']? ',':'').$shop_data['shop']['country'];
$sender_address .=  ( substr($sender_address,-1)==',' ? substr($sender_address,0,-1) :'' );

$sender_latlng = $shop_data['shop']['latitude'].','.$shop_data['shop']['longitude'];


$recipient_fullname = $data_array['shipping_address']['name'];
$recipient_email = $data_array['contact_email'];
$recipient_phone = $data_array['shipping_address']['phone'];

$recipient_address = '';
$recipient_address .= $data_array['shipping_address']['address1'];
$recipient_address .=  ( substr($recipient_address,-1)==',' ? substr($recipient_address,0,-1) :'' );
$recipient_address .= ($data_array['shipping_address']['address2']? ',':'').$data_array['shipping_address']['address2'];
$recipient_address .=  ( substr($recipient_address,-1)==',' ? substr($recipient_address,0,-1) :'');
$recipient_address .= ($data_array['shipping_address']['zip']? ',':'').$data_array['shipping_address']['zip'];
$recipient_address .=  ( substr($recipient_address,-1)==',' ? substr($recipient_address,0,-1) :'');
$recipient_address .= ' '.$data_array['shipping_address']['city']; 
$recipient_address .=  ( substr($recipient_address,-1)==',' ? substr($recipient_address,0,-1) :'');
$recipient_address .= ','.$data_array['shipping_address']['province'];
$recipient_address .=  ( substr($recipient_address,-1)==',' ? substr($recipient_address,0,-1) :'');
$recipient_address .= ','.$data_array['shipping_address']['country'];
$recipient_address .=  ( substr($recipient_address,-1)==',' ? substr($recipient_address,0,-1) :'');

$recipient_latlng = $data_array['shipping_address']['latitude'].','.$data_array['shipping_address']['latitude'];

if($data_array['billing_address']['address2'] !== '') {
    $billing_address_1 = $data_array['billing_address']['address1'] . "," . $data_array['billing_address']['address2']; 
} else {
    $billing_address_1 = $data_array['billing_address']['address1'];
}

$pickup_address = $billing_address_1 . "," . $data_array['billing_address']['city'] . "," . $data_array['billing_address']['province'];

if($data_array['shipping_address']['address2'] != ''){
    $shipping_address_1 = $data_array['shipping_address']['address1'] . "," . $data_array['shipping_address']['address2'];
} else {
    $shipping_address_1 = $data_array['shipping_address']['address1'];
}
                
$delivery_address = $shipping_address_1 . "," . $data_array['shipping_address']['city']. "," . $data_array['shipping_address']['province'];
$shipping_address_1 = $data_array['fulfillments'][''];

// Address Calculate

$api_url = "https://zeptoapi.com/api/rest/calculator/address";

$now = date("Y-m-d H:i:s");

$query = array(
    "token" => ZEPTOAPI_TOKEN,
	"app_id" => ZEPTOAPI_APP_ID,
	"pickup" => $pickup_address,
	"delivery" => $delivery_address,
	"vehicle" => 1,
	"schedule" => $now,
	"country" => $data_array['shipping_address']['country_code']
);

// Configure curl client and execute request
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, count($query));
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query));
$result = curl_exec($ch);
curl_close($ch);
$result_array = json_decode($result, true);
$status = $result_array['result'][0]['status'];
if($status) {
    $delivery_price = $result_array['result'][0]['price_myr'];
    $pickup_latlng = $result_array['result'][0]['pickup_latlng'];
    $delivery_latlng = $result_array['result'][0]['delivery_latlng'];
    $distance_km = $result_array['result'][0]['distance_km'];
    $price_myr = $result_array['result'][0]['price_myr'];
} else {
    file_put_contents('err.txt', $result_array['result'][0]['message'] . $now, true);
    exit;
}


/*
$product_list = '';
product_quantity
product_price
product_currency
product_order_id
weight_kg
*/

$order_number = $data_array['order_number'];
$total_weight = $data_array['total_weight'];
$weight_kg = $total_weight/1000;
$product_currency = $data_array['currency'];
$shipping_method = strtolower(trim($data_array['shipping_lines']['0']['code']));

$ar_items = $data_array['line_items'];
foreach ($ar_items as $key => $item) {
	$item_sku = $item['sku'];
	$item_name = $item['name'];
	$item_quantity = $item['quantity'];
	$item_price = $item['price'];

	$product_list .= $item_name."|";
	$product_quantity .= $item_quantity."|";
	$product_price .= $item_price."|"; 
}
$product_list = substr($product_list,0,-1);
$product_quantity = substr($product_quantity,0,-1);
$product_price = substr($product_price,0,-1);

$deliveryday_type = 'sd';
if( strtolower(trim($shipping_method))==strtolower(trim('zts_ndd')) ) $deliveryday_type = 'nd';
if( strtolower(trim($shipping_method))==strtolower(trim('zts_3h')) ) $deliveryday_type = 'sd';

$vehicle = 1;
if($deliveryday_type == 'sd' && $weight_kg>15 ) {
	$vehicle = 2;
}
if($deliveryday_type == 'nd') {
	$vehicle = 13;
}

// Connect to zeptoexpress api - Post Code Calculator
$api_url = "https://zeptoapi.com/api/rest/booking/new";
$query = array(
	"token" => ZEPTOAPI_TOKEN,
	"app_id" => ZEPTOAPI_APP_ID,
	"sender_fullname" => $sender_fullname,
	"sender_email" => $sender_email,
	"sender_phone" => $sender_phone,
	"recipient_fullname" => $recipient_fullname,
	"recipient_email" => $recipient_email,
	"recipient_phone" => $recipient_phone,
	"pickup_address" => $sender_address,
	"pickup_latlng" => $sender_latlng,
	"delivery_address" => $recipient_address,
	"delivery_latlng" => $recipient_latlng,
	"distance_km" => $distance_km,
	"price_myr" => $price_myr,
	"trip_type" => 1,
	"instruction_notes" => 'Please call me when you have reached.',
	"datetime_pickup" => 'NOW',
	"unit_no_pickup" => '',
	"unit_no_delivery" => ''
	,"deliveryday_type"=>$deliveryday_type
	,"vehicle" => $vehicle
	,"country" => 'MY'
	,"riderSelected"=>'714'
	,"shop_name"=>$shop_name
	,"product_list"=>$product_list
	,"product_quantity"=>$product_quantity
	,"product_price"=>$product_price
	,"product_currency"=>$product_currency
	,"product_order_id"=>$order_number
	,"weight_kg"=>$weight_kg
);

$send_params = print_r( json_decode( json_encode($query) ,true ) ,true );
file_put_contents('err.txt', $send_params , true);

// Configure curl client and execute request
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, count($query));
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query));
$result = curl_exec($ch);
curl_close($ch);
$result_array = json_decode($result, true);
$status = $result_array['result'][0]['status'];

$result_en = json_encode($result, JSON_UNESCAPED_SLASHES);
$result_en = str_ireplace('\n        ','',$result_en);
$result_en = str_ireplace('"',"'",$result_en);

// save result into db
$s = "insert into shopify_job_create_result set 
			created = now()
			,shopify_shop_name = \"".$shop_name."\"
			,shopify_order_id = \"".$order_number."\"
			,result = \"".( $result_en )."\"
			";
$qry= $db->db_query($s);

$result_pretty = print_r( $result_array ,true);
file_put_contents('job_create_new.txt', $order_number.'\n'.$result_en.'\n'.$result_pretty.'\n'.$s, true);


if($status) {
    $jobid = $result_array['result'][0]['jobid'];
    $secret_code_pickup = $result_array['result'][0]['secret_code_pickup'];
    $secret_code_delivery = $result_array['result'][0]['secret_code_delivery'];
} else {
	
    exit;
}

?>