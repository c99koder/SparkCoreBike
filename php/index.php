<?php
error_reporting(E_ERROR);
require('lib/sfYamlParser.php');
require('lib/runkeeperAPI.class.php');

function spark_variable($device, $token, $variable) {
	$options = array(
		CURLOPT_URL => "https://api.spark.io/v1/devices/$device/$variable?access_token=$token",
		CURLOPT_RETURNTRANSFER => true
	);
	$curl = curl_init();
	curl_setopt_array($curl, $options);
	$response     = curl_exec($curl);
	curl_close($curl);
	return json_decode($response);
}

function spark_function($device, $token, $function, $args) {
	$options = array(
		CURLOPT_URL => "https://api.spark.io/v1/devices/$device/$function",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => http_build_query(array('access_token'=>$token, 'args'=>$args))
	);
	$curl = curl_init();
	curl_setopt_array($curl, $options);
	$response     = curl_exec($curl);
	curl_close($curl);
	return json_decode($response);
}

/* API initialization */
$rkAPI = new runkeeperAPI(
		'config.yml'	/* api_conf_file */
		);
if ($rkAPI->api_created == false) {
	echo 'error '.$rkAPI->api_last_error; /* api creation problem */
	exit();
}

if ($_GET['code']) {
	$auth_code = $_GET['code'];
	if ($rkAPI->getRunkeeperToken($auth_code) == false) {
		echo $rkAPI->api_last_error;
		exit();
	}
	else {
		echo "Got access token: $rkAPI->access_token";
	}
} else if(isset($rkAPI->api_conf->App->access_token) && strlen($rkAPI->api_conf->App->access_token) > 0) {
	$rkAPI->setRunkeeperToken($rkAPI->api_conf->App->access_token);
	$rkProfile = $rkAPI->doRunkeeperRequest('Profile','Read');
	if($rkProfile) {
		echo "<img src='$rkProfile->small_picture' style='float:left; padding-right: 6px;'/>";
		echo $rkProfile->name . "<br/>";
		echo $rkProfile->location . "<br clear='both'/>";
	} else {
		echo $rkAPI->api_last_error;
	    print_r($rkAPI->request_log);
	}
	$distance = 0;
	$duration = 0;
	$v = spark_variable($rkAPI->api_conf->Spark->device_id, $rkAPI->api_conf->Spark->access_token, "distance");
	if($v && isset($v->result)) {
		$distance = doubleval($v->TEMPORARY_allTypes->string);
		echo "<b>Distance</b>: $distance miles<br/>";
	} else {
		print_r($v);
		exit();
	}
	$v = spark_variable($rkAPI->api_conf->Spark->device_id, $rkAPI->api_conf->Spark->access_token, "duration");
	if($v && isset($v->result)) {
		$duration = $v->TEMPORARY_allTypes->uint32 / 1000;
		$minutes = (int)($duration / 60);
		$seconds = $duration % 60;
		if($seconds < 10)
			$seconds = "0" . $seconds;
		echo "<b>Duration</b>: $minutes:$seconds<br/>";
	} else {
		print_r($v);
		exit();
	}
	$fields = json_decode('{"type": "Cycling", "equipment": "Stationary Bike", "start_time": "'.date(DATE_RFC822).'", "total_distance": '.($distance * 1609.34).', "duration": '.$duration.', "post_to_facebook": false, "post_to_twitter": false}');
	$rkCreateActivity = $rkAPI->doRunkeeperRequest('NewFitnessActivity','Create',$fields);
	if ($rkCreateActivity) {
		echo "<b>Calories burned</b>: $rkCreateActivity->total_calories<br/>";
		echo "<a href='$rkCreateActivity->activity'>View on RunKeeper</a>";
		spark_function($rkAPI->api_conf->Spark->device_id, $rkAPI->api_conf->Spark->access_token, "clear", "");
	} else {
		echo $rkAPI->api_last_error;
		print_r($rkAPI->request_log);
	}
} else {
	echo "<a href='" . $rkAPI->connectRunkeeperButtonUrl() . "'>Login to RunKeeper</a>";
}
?>
