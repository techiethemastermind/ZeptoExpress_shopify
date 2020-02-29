<?php

// Get config
require_once('inc/config.php');

// log the raw request -- this makes debugging much easier
$filename = "log/" . time();
$input = file_get_contents('php://input');
file_put_contents($filename.'-input', $input);

// parse the request
$rates = json_decode($input, true);

// log the array format for easier interpreting
file_put_contents($filename.'-debug', print_r($rates, true));

// regular shipping is 3 to 7 days after today
$reg_min_date = date('Y-m-d H:i:s O', strtotime('+3 days'));
$reg_max_date = date('Y-m-d H:i:s O', strtotime('+7 days'));

// Destination Post Code
$postCodeTo = $rates['rate']['destination']['postal_code'];
$countryTo = $rates['rate']['destination']['country'];
$currency = $rates['rate']['currency'];

// Pickup Post code
$post_file = $appConf['postcode_file'];

if(file_exists($post_file)) {
    $contents = file_get_contents($post_file);
    $lines = explode("\n", $contents); // this is your array of words
    $postCodeFrom = $lines[0];
} else {
    $postCodeFrom = '';
}

// Connect to zeptoexpress api - Post Code Calculator

$api_url = "https://zeptoapi.com/api/rest/calculator/postcode/";

$query = array(
	"token" => $appConf['zepto_token'],
	"app_id" => $appConf['zepto_app_id'],
	"pickup" => $postCodeFrom,
	"delivery" => $postCodeTo,
	"country" => $countryTo
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
    $zepto_cost = $result_array['result'][0]['price_myr'];
} else {
    file_put_contents('log/err.txt', $result_array['result'][0]['message'] . $now, true);
}

// build the array of line items using the prior values
$output = array('rates' => array(
    array(
        'service_name' => 'ZepToExpress Delivery',
        'service_code' => 'ZTS',
        'total_price' => $zepto_cost,
        'currency' => $currency,
        'min_delivery_date' => $reg_min_date,
        'max_delivery_date' => $reg_max_date
    )
));

// encode into a json response
$json_output = json_encode($output);

// log it so we can debug the response
file_put_contents($filename.'-output', $json_output);

// send it back to shopify
print $json_output;