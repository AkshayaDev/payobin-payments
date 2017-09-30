<?php

// Function to get the client IP address
function get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
       $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function getCardBrand($pan, $include_sub_types = false)
{
    //maximum length is not fixed now, there are growing number of CCs has more numbers in length, limiting can give false negatives atm

    //these regexps accept not whole cc numbers too
    //visa        
    $visa_regex = "/^4[0-9]{0,}$/";
    $vpreca_regex = "/^428485[0-9]{0,}$/";
    $postepay_regex = "/^(402360|402361|403035|417631|529948){0,}$/";
    $cartasi_regex = "/^(432917|432930|453998)[0-9]{0,}$/";
    $entropay_regex = "/^(406742|410162|431380|459061|533844|522093)[0-9]{0,}$/";
    $o2money_regex = "/^(422793|475743)[0-9]{0,}$/";

    // MasterCard
    $mastercard_regex = "/^(5[1-5]|222[1-9]|22[3-9]|2[3-6]|27[01]|2720)[0-9]{0,}$/";
    $maestro_regex = "/^(5[06789]|6)[0-9]{0,}$/"; 
    $kukuruza_regex = "/^525477[0-9]{0,}$/";
    $yunacard_regex = "/^541275[0-9]{0,}$/";

    // American Express
    $amex_regex = "/^3[47][0-9]{0,}$/";

    // Diners Club
    $diners_regex = "/^3(?:0[0-59]{1}|[689])[0-9]{0,}$/";

    //Discover
    $discover_regex = "/^(6011|65|64[4-9]|62212[6-9]|6221[3-9]|622[2-8]|6229[01]|62292[0-5])[0-9]{0,}$/";

    //JCB
    $jcb_regex = "/^(?:2131|1800|35)[0-9]{0,}$/";

    //ordering matter in detection, otherwise can give false results in rare cases
    if (preg_match($jcb_regex, $pan)) {
        return "jcb";
    }

    if (preg_match($amex_regex, $pan)) {
        return "amex";
    }

    if (preg_match($diners_regex, $pan)) {
        return "diners_club";
    }

    //sub visa/mastercard cards
    if ($include_sub_types) {
        if (preg_match($vpreca_regex, $pan)) {
            return "v-preca";
        }
        if (preg_match($postepay_regex, $pan)) {
            return "postepay";
        }
        if (preg_match($cartasi_regex, $pan)) {
            return "cartasi";
        }
        if (preg_match($entropay_regex, $pan)) {
            return "entropay";
        }
        if (preg_match($o2money_regex, $pan)) {
            return "o2money";
        }
        if (preg_match($kukuruza_regex, $pan)) {
            return "kukuruza";
        }
        if (preg_match($yunacard_regex, $pan)) {
            return "yunacard";
        }
    }

    if (preg_match($visa_regex, $pan)) {
        return "visa";
    }

    if (preg_match($mastercard_regex, $pan)) {
        return "mastercard";
    }

    if (preg_match($discover_regex, $pan)) {
        return "discover";
    }

    if (preg_match($maestro_regex, $pan)) {
        if ($pan[0] == '5') {//started 5 must be mastercard
            return "mastercard";
        }
            return "maestro"; //maestro is all 60-69 which is not something else, thats why this condition in the end

    }

    return "unknown"; //unknown for this system
}

// ask your PSP for correct url for processing or test environment (they are different)
define('SERVICE_URL', 'https://gate.payobin.com/forms/initPurchase');
// routing key (UUID of the terminal) you received from support
define('ROUTING_KEY', 'fca606541d56721101472b2962ff929ed698b4c6ca6ae9e8e510b6ac');

if(!empty($_POST['payment-submit'])) {

$card_number = str_replace(' ','',trim($_POST['number']));
$card_brand = strtoupper(getCardBrand($card_number));

$expiry = $_POST['expiry'];
$expiry = explode('/', $expiry);
list($expireMonth,$expireYear) = $expiry;
$transaction = array(
	'merchantTransactionId' => time(),
	'transactionType' => 'DEBIT',
	'recurringType' => 'NONE',
	'payment' => array(
					'amount' => $_POST['amount'],
					'currency' => 'USD',
					'descriptor' => 'Purchase'
		),
	'brands' => array('AMEX','VISA'),
	'customer' => array(
					'firstName' => $_POST['first-name'],
					'lastName' => $_POST['last-name'],
					'email' => $_POST['email'],
					'ipAddress' => get_client_ip(),
					'street' => $_POST['streetaddress'],
					'city' => $_POST['city'],
					'country' => $_POST['country'],
					'zip' => $_POST['zipcode']
		),
	'card' => array(
				'brand' => $card_brand,
				'firstName' => $_POST['first-name'],
				'lastName' => $_POST['last-name'],
				'number' => $card_number,
				'verification' => trim($_POST['cvc']),
				'expireMonth' => trim($expireMonth),
				'expireYear' => trim($expireYear)
		),
	);

$data_string = json_encode($transaction);

$ch = curl_init();
curl_setopt($ch, CURLOPT_POST, 1);
// some PHP versions with openssl bindings have troubles with our RapidSSL certificate
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_URL, SERVICE_URL);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/json',
    'Routing-Key: ' . ROUTING_KEY,
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data_string))
);

curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response    = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, TRUE);
//print_r(json_decode($response, TRUE));
//echo $http_status . PHP_EOL;
}?>
<!DOCTYPE html>
<html>
<head>
	<title>Payment Process</title>
	<meta name="viewport" content="initial-scale=1">
	<link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
</head>
<body>
<div class="container">
	<div class="row text-center">
        <div class="col-sm-6 col-sm-offset-3">
        <br><br>
        <?php 
if(!empty($result) && $result['status'] == 'SUCCESS') { ?>
<h2 style="color:#0fad00">Success</h2>
<img src="http://osmhotels.com//assets/check-true.jpg">
<h3>Dear, <?php echo $_POST['first-name'].' '.$_POST['last-name'] ?></h3>
<p style="font-size:20px;color:#5C5C5C;">Your payment is successfully received.</p>
<a href="javascript:history.back()" class="btn btn-success">Go Back</a>
<?php }else{ ?>
<h2 style="color:#d9534f">Something Went Wrong!</h2>
<a href="javascript:history.back()" class="btn btn-danger">Go Back</a>
<?php }?>
    <br><br>
        </div>
        
	</div>
</div>
</body>
</html>