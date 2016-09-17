<?php

/***************************************************************
*
* Sabay Tayo (c)
* Created by: Kenneth See
*
* Receives SMS from the users, processes the contents, then
* replies back to the users also via SMS.
*
***************************************************************/

/***************************************************************
* CONSTANTS - Begin
***************************************************************/

  define('APP_NAME', 'sabaytayo' );
  define('DEBUG', true );
  define('DEFAULT_TIMEZONE', 'Asia/Manila' );
  define('DEFAULT_TIMEZONE_OFFSET', '+08:00' );
  define('WORKING_DIR', '/kunden/homepages/41/d579830064/htdocs/clickandbuilds/SabayTayo/' );
  define('LOG_DIR', WORKING_DIR.'tb-logs/' );
  define('LOG_FILE', LOG_DIR.APP_NAME.'.log' );
  define('INCOMING_TEXTS_DIR', WORKING_DIR.'tb-in/' );
  define('GLOBE_APP_NUMBER', 0465);
  define('TOKEN_SEPARATOR', "|||" );
  define('PHP_FULL_PATH',  '/usr/bin/php5.5-cli' );
  define('ST_PROCESSOR', WORKING_DIR.'tb-sabaytayo-poller.php' );

/***************************************************************
* CONSTANTS - End
***************************************************************/


/***************************************************************
* FUNCTIONS - Begin
***************************************************************/

  // load Wordpress environment to access mySQL underneath
  function find_wordpress_base_path() {
    $dir = dirname(__FILE__);
    do {
      // it is possible to check for other files here
      if( file_exists($dir."/wp-config.php") ) {
        return $dir;
      }
    } while( $dir = realpath("$dir/..") );
      return null;
  }

  // parameter checking of text message
  function isvalid($item, $type) {
    $isvalid = 0;

    // $type can be port, date, time, pax, and notes
    switch ($type) {
      case 'port':
        $isvalid = preg_match("#^[0-9a-zA-Z]+$#", $item);
        break;
      case 'date':
        if ($item == '') {
          $isvalid = 1;
        } else {
          $isvalid = preg_match("#^[0-9]{4}\-((0?[1-9])|(10)|(11)|(12))\-([0-3]?[0-9])$#", $item);
        }
        break;
      case 'time':
        if ($item == '') {
          $isvalid = 1;
        } else {
          $isvalid = preg_match("#^(([0-1]?[0-9])|(20)|(21)|(22)|(23)):([0-5][0-9])$#", $item);
        }
        break;
      case 'pax':
        $isvalid = preg_match("#^[0-9]+$#", $item);
//        if (! preg_match("#^[0-9]+$#", $item))
//          $isvalid = 0;
        break;
    };

//    echo $isvalid;
    return($isvalid);
  }

  function terminate_with_message($errormessage) {
    global $handle;

    fwrite($handle, $errormessage);
    fclose($handle);
    die($errormessage);
  }

  // get access token of the subscriber number
  function get_access_token($phone_number) {
    global $handle,$wpdb;

    $query = "SELECT access_token FROM subscr_acctoken WHERE subscriber_number = '".$phone_number."'" ;
    if (DEBUG) {
      fwrite($handle, "SQL QUERY: $query\n");
    }
    $results = $wpdb->get_results($query);
    $tok = $results[0]->access_token;
    if (DEBUG) {
      fwrite($handle, "ACCESS TOKEN: $tok\n");
    }
    return $tok;
  }

  function send_sms($phone_number, $message) {
    global $handle, $globe, $timestamp;

    $sms = $globe->sms(GLOBE_APP_NUMBER);
    $acctok = get_access_token($phone_number);
    $response = $sms->sendMessage($acctok, $phone_number, $message);
    if (DEBUG) {
      fwrite($handle, "$timestamp: SMS Response to $phone_number = $message\n");
    }
    $logfilename = LOG_DIR."$timestamp.$phone_number.response";
    file_put_contents($logfilename, $message);
  }

  function syntax_error($t) {
    global $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;
    global $handle;
    $em = '';

    // parse parameters
    $parameters = explode("/", $t);

    // filter 1: num of parameters must be 5-6. The 'notes' field is optional.
    if (count($parameters) < 5) {
      $em = "Message must follow the following pattern: origin/destination/departure date in YYYY-MM-DD format/latest departure time in HH:mm format, military time/number of passengers/notes. Ex 1: PHPIN/PHSBL/2017-01-31/16:00/3/can leave as early as 14:00";
      return $em;
    }

  // filter 2: parameters must be in the right format
    $port_orig = strtoupper($parameters[0]);
    $port_dest = strtoupper($parameters[1]);
    $dept_date = $parameters[2];
    $dept_time = $parameters[3];
    $pax       = $parameters[4];
    $notes     = $parameters[5];

    if (! isvalid($port_orig, 'port') ) {
      $em .= "Origin not in list. Pls refer to list of valid ports. ";
    }

    if (! isvalid($port_dest, 'port') ) {
      $em .= "Destination not in list. Pls refer to list of valid ports. ";
    }

    if (! isvalid($dept_date, 'date') ) {
      $em .= "Date format must be YYYY-MM-DD, ex. 2016-01-13 for 13 January 2016. ";
    }

    if (! isvalid($dept_time, 'time') ) {
      $em .= "Time format must be HH:mm, military time, ex. 13:45 for 1:45 PM. ";
    }

    if (! isvalid($pax, 'pax') ) {
      $em .= "Number of passengers must be a whole number. ";
    }

    // do something for the optional notes field to prevent SQL injection!!!

    return $em;
  }


/***************************************************************
* FUNCTIONS - End
***************************************************************/

/***************************************************************
* MAIN PROGRAM - Begin
***************************************************************/

  // General prep work
  global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header, $wpdb;
  global $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;

  define('BASE_PATH', find_wordpress_base_path()."/" );
  require(BASE_PATH . 'wp-load.php');

  // set up log file
  $handle = fopen(LOG_FILE, 'a') or die('Cannot open file:  '.LOG_FILE);

  date_default_timezone_set(DEFAULT_TIMEZONE);
  if (DEBUG) {
    fwrite($handle, "Timezone = ".DEFAULT_TIMEZONE."\n");
  }

  $timestamp = time();
  if (DEBUG) {
    fwrite($handle, "Timestamp = $timestamp\n");
  }

  // Prep for sending SMS via Globe API
  session_start();
  require ('api/PHP/src/GlobeApi.php');
  $globe = new GlobeApi('v1');

  // get json object which contains the text message and metadata; 1 object per SMS batch (can be 1-4 individual SMS depending on length of message
  $json = file_get_contents('php://input');
  $json = stripslashes($json);
  $jsonvalues = json_decode($json, true);

  // get mobile number. NOTE: senderAddr ADDS A "TEL:" STRING TO THE PHONE NUMBER
  $subscriber_number = $jsonvalues[inboundSMSMessageList][inboundSMSMessage][0][senderAddress];
  $subscriber_number = substr($subscriber_number, 4);    // remove the "tel:" prefix from the string
  if (DEBUG) {
    fwrite($handle, "Subscriber Number = $subscriber_number\n");
  }
  
  // get text message. Rebuild if entire message is broken into 2-4 SMSes
  $c = $jsonvalues[inboundSMSMessageList][numberOfMessagesInThisBatch];
  //echo "Number of messages in batch = $c\n";
  if (DEBUG) {
    fwrite($handle, "$timestamp: Number of messages in batch = $c\n");
  }
  $text = '';
  for ($i=0; $i < $c ; $i++) {
    // get text message
    $text .= $jsonvalues[inboundSMSMessageList][inboundSMSMessage][$i][message];
  }
  if (DEBUG) {
    fwrite($handle, "$timestamp: Text message = $text\n");
  }

  // validate text message
  // if is wrongly formatted, inform user by SMS then quit program
  $se = syntax_error($text);
  if (!($se === '')) {
    send_sms($subscriber_number, $se);
  } else {
    // create file with text message + other data
    $textfilename = INCOMING_TEXTS_DIR.APP_NAME.".$timestamp";
    file_put_contents($textfilename, $timestamp.TOKEN_SEPARATOR.$subscriber_number.TOKEN_SEPARATOR.$text);
  }
    
  // exec(PHP_FULL_PATH.' '.ST_PROCESSOR);
  // if (DEBUG) {
    // fwrite($handle, "$timestamp: ".ST_PROCESSOR." executed.\n");
  // }
  
  // properly close log file
  fclose($handle);  
?>