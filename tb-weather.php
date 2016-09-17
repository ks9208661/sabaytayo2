<?php
	
DEFINE('HOST','localhost');
DEFINE('USER','ht_htc1');
DEFINE('PASS','1234');
DEFINE('DB','ht_htc1');
DEFINE('DEBUG',true);

function mysqconnect() {
	
	$link = mysql_connect(HOST, USER, PASS);
	
	if (!$link) {
	    die('Could not connect: ' . mysql_error());
	}
	if (!mysql_select_db ( DB ) ) {
	    die('Could not select database: ' . mysql_error());
	}
	return;
}

function get_current_weather($port) {
	mysqconnect();
	$link = mysql_query( sprintf(" 
			SELECT last_update, temp_current, windspeed_current, direction_current, chance_rain_current
			FROM st_weather 
			WHERE port='%s' ",
			mysql_real_escape_string($port) ) );
	if (!$link) {
	    die('Could not query:' . mysql_error());
	}
	$return = mysql_fetch_object($link); 
	$date1 = date('Y-m-d', $return->last_update);
	
	$return_text = 'Weather for ' . $date1 . ' Temp:' . $return->temp_current . ' Wind:' . $return->windspeed_current . 'km/s-' . $return->direction_current . ' with chance of rain:' .$return->chance_rain_current . '%'; 
	
	return $return_text;
	
}	

function check_params($text) {
	$check = explode('/', $text);
	
	switch ( $check[0] ) {
		case 'WEATHER':
			echo $check[1];
			break;
		
		case 'BOATMAN':
			echo $check[1];
			break;
		
		case 'TRIPS':
			echo $check[1];
			break;	
	}


}


$text = 'WEATHER/test';

echo check_params($text);


$place = 'PHMDRPIN';
echo get_current_weather($place);


?>