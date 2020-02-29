<?php
header('Access-Control-Allow-Origin: *');
include("inc/global-cron.php");

$db=new connection();
$db->db_conn();

// log the raw request -- this makes debugging much easier
$filename = "log/" . date('YmdHis'); // time();
$input = file_get_contents('php://input');
file_put_contents($filename.'-input', $input);

// parse the request
$input_json = json_decode($input, true);

// log the array format for easier interpreting
file_put_contents($filename.'-debug', print_r($input_json, true));

// total up the cart quantities for simple rate calculations
$quantity = 0;
foreach($input_json['rate']['items'] as $item) {
    $quantity =+ $item['quantity'];
}

// get total grams
$total_grams = 0;
foreach($input_json['rate']['items'] as $item) {
    $total_grams += ( $item['grams'] * $item['quantity'] );
}

$input_company_name = $input_json['rate']['origin']['company_name'];
$input_postcode_from = $input_json['rate']['origin']['postal_code'];
$input_postcode_to = $input_json['rate']['destination']['postal_code'];
$countryTo = $input_json['rate']['destination']['country'];
$currency = $input_json['rate']['currency'];


// get merchant setting on db
$s = "select * from members where shopify_shop_name = \"".escape_string($input_company_name)."\"  order by id desc limit 1";
$qry= $db->db_query($s);
$nums= $qry->num_rows;
if ($nums > 0 ) { // exist in activated
    $row= $db->db_fetch_array($qry);

    $id       = $row->id;
    $shopify_allow_shipping = $row->shopify_allow_shipping;
    $shopify_3h_delivery = $row->shopify_3h_delivery;
    $shopify_ndd                = $row->shopify_ndd;
    $shopify_allow_inventory = $row->shopify_allow_inventory;

} else { //if ($nums > 0 ) 
    die("<br>Sorry member's record not found");
}


// use number_format because shopify api expects the price to be "25.00" instead of just "25"

// overnight shipping is 5.50 per item
$overnight_cost = number_format($quantity * 5.50, 2, '', '');
// regular shipping is 2.75 per item
$regular_cost = number_format($quantity * 2.75, 2, '', '');

// overnight shipping is 1 to 2 days after today
$on_min_date = date('Y-m-d H:i:s O', strtotime('+0 day'));
$on_max_date = date('Y-m-d H:i:s O', strtotime('+3 hours'));

// regular shipping is 3 to 7 days after today
$reg_min_date = date('Y-m-d H:i:s O', strtotime('+3 days'));
$reg_max_date = date('Y-m-d H:i:s O', strtotime('+7 days'));


// find vehicle
$vehicle = 1;                           // zeptobike
if($total_grams > 15000) $vehicle = 2;  // zeptocar

// Connect to zeptoexpress api - Post Code Calculator

$api_url = "https://zeptoapi.com/api/rest/calculator/postcode/";

$query = array(
	"token" => ZEPTOAPI_TOKEN,
	"app_id" => ZEPTOAPI_APP_ID,
	"pickup" => $input_postcode_from,
	"delivery" => $input_postcode_to,
	"country" => $countryTo,
    "vehicle" => $vehicle,
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
    $zepto_cost = number_format($quantity * $result_array['result'][0]['price_myr'], 2, '', '');
} else {
    file_put_contents('log/err.txt', $result_array['result'][0]['message'] . $now, true);
}

$query2 = array(
    "token" => ZEPTOAPI_TOKEN,
    "app_id" => ZEPTOAPI_APP_ID,
    "pickup" => $input_postcode_from,
    "delivery" => $input_postcode_to,
    "delivery_type" => "ndd",
    "schedule" => date('Y-m-d 15:00:00', strtotime($stop_date . ' +2 day')),
    "weight_kg" => "".($total_grams/1000)."",
    "country" => "MY"
);

// Configure curl client and execute request
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch2, CURLOPT_URL, $api_url);
curl_setopt($ch2, CURLOPT_POST, count($query2));
curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query($query2));
$result2 = curl_exec($ch2);
curl_close($ch2);
$result_array2 = json_decode($result2, true);
$status2 = $result_array2['result'][0]['status'];
if($status2) {
    $zepto_cost2 = number_format($quantity * $result_array2['result'][0]['price_myr'], 2, '', '');
} else {
    file_put_contents('log/err.txt', $result_array2['result'][0]['message'] . $now, true);
}

// build the array of line items using the prior values
$arTemp = array();
if($shopify_3h_delivery) {
    $arTemp[] = array(
        'service_name' => 'ZeptoExpress (3 Hours Express)',
        'service_code' => 'ZTS_3H',
        'total_price' => $zepto_cost,
        'currency' => $currency,
        'min_delivery_date' => $on_min_date,
        'max_delivery_date' => $on_max_date
      );
}
if($shopify_ndd) {
    $arTemp[] = array(
        'service_name' => 'ZeptoExpress Next Day',
        'service_code' => 'ZTS_NDD',
        'total_price' => $zepto_cost2,
        'currency' => $currency,
        'min_delivery_date' => $reg_min_date,
        'max_delivery_date' => $reg_max_date
      );
}

$output = array('rates' => $arTemp
);

// encode into a json response
$json_output = json_encode($output);

// log it so we can debug the response
file_put_contents($filename.'-output', $json_output);

// send it back to shopify
print $json_output;