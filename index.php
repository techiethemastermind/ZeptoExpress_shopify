<?php

// Get config
require_once('inc/config.php');

// Get our helper functions
require_once("inc/functions.php");

// Set variables for our request
$shop = $appConf['shop_name'];
$token_file = $appConf['token_file'];

if(!file_exists($token_file)) {
    echo "<span>Install Error. Please install App again!</span>";
    die();
}

$contents = file_get_contents($token_file);
$lines = explode("\n", $contents); // this is your array of words
$token = $lines[0];

$post_file = $appConf['postcode_file'];

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

// Create Carrier service via api

$params = array(
    "carrier_service" => array(
        "name" => "Shipping Rate Provider",
        "callback_url" => $appConf['app_url'] . "/ship_rate_provider.php",
        "service_discovery" => true
    )
);

shopify_call($token, $shop, "/admin/api/2020-01/carrier_services.json", $params, 'POST');

// Create Webhook for orders/paid
$params = array(
    "webhook" => array(
        "topic"=>"orders/paid",
        "address"=> $appConf['app_url'] . "/zepto_order.php",
        "format"=> "json"
    )
);

shopify_call($token, $shop, "/admin/api/2020-01/webhooks.json", $params, 'POST');

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
    
    <section id="" class="">
        <div class="container-fluid">
            <div class="row p-5">
                <div class="col-12">
                    <form method="post" action="">
                        <div class="form-group row">
                            <label for="postCodeFrom" class="col-form-label">Postal Code: </label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" id="postCodeFrom" name="postCodeFrom" value="<?php echo $postCodeFrom; ?>" placeholder="Post Code here...">
                            </div>
                            <div class="col-sm-2">
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                            <input type='hidden' name='submit' />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Script -->
    <script src="assets/lib/jquery/jquery.min.js"></script>
    <script src="assets/lib/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>