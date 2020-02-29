<?php

	// Get config
	require_once('inc/config.php');

    $token = $appConf['zepto_token'];
    $api_id = $appConf['zepto_app_id'];

    $data = file_get_contents('php://input');
    $data_array = json_decode($data, true);
    
	file_put_contents('data/data.txt', print_r($data_array, true));

	$sender_fullname = $data_array['billing_address']['name'];
	$sender_email = $data_array['email'];
	$sender_phone = $data_array['billing_address']['phone'];
	
	$recipient_fullname = $data_array['shipping_address']['name'];
	$recipient_email = $data_array['contact_email'];
	$recipient_phone = $data_array['shipping_address']['phone'];
	
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

    // Address Calculate
	$api_url = "https://zeptoapi.com/api/rest/calculator/address";
	
	$now = date("Y-m-d H:i:s");
	
	$query = array(
	    "token" => $token,
		"app_id" => $api_id,
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

	// Connect to zeptoexpress api - Post Code Calculator

	$api_url = "https://zeptoapi.com/api/rest/booking/new";
	
	$query = array(
		"token" => $token,
		"app_id" => $api_key,
		"sender_fullname" => $sender_fullname,
		"sender_email" => $sender_email,
		"sender_phone" => $sender_phone,
		"recipient_fullname" => $recipient_fullname,
		"recipient_email" => $recipient_email,
		"recipient_phone" => $recipient_phone,
		"pickup_address" => $pickup_address,
		"delivery_address" => $delivery_address,
		"pickup_latlng" => $pickup_latlng,
		"delivery_latlng" => $delivery_latlng,
		"distance_km" => $distance_km,
		"price_myr" => $price_myr,
		"trip_type" => 1,
		"instruction_notes" => 'Please call me when you have reached.',
		"datetime_pickup" => 'NOW',
		"unit_no_pickup" => '',
		"unit_no_delivery" => '',
		"vehicle" => 1,
		"country" => 'MY'
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
	    $jobid = $result_array['result'][0]['jobid'];
	    $secret_code_pickup = $result_array['result'][0]['secret_code_pickup'];
	    $secret_code_delivery = $result_array['result'][0]['secret_code_delivery'];
	} else {
	    file_put_contents('data/err.txt', $result_array['result'][0]['message'] . $now, true);
	    exit;
	}

?>