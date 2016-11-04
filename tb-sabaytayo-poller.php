<?php

/***************************************************************
Project: Sabay Tayo
File: sabaytayo-process.php
Created by: Kenneth See
Description:
  When executed, checks a directory for files to process. When files are found, files are handled one by one by passing them to another php script that directly processes them and outputs a file in the end containing SQL queries. Processed files are moved to another directory afterwards. When all the files are processed and the SQL queries file finished, it starts sending the queries to the database. SMSes are sent to users based on query results. When all queries have been executed, the query file is removed.

  During execution, a temporary lock file is created in the default directory to prevent other instances of this program from running and causing collision. The temporary lock file is removed at the end of the execution.
  
  Format of file: timestamp|||subscriber number|||text message. 
  Assumption: file has already been checked for errors and possible cracking attempts. 
***************************************************************/

/***************************************************************
* CONSTANTS - Begin
***************************************************************/

  define('APP_NAME', 'sabaytayo' );
  define('DEBUG', true );
  define('WORKING_DIR', '/kunden/homepages/41/d579830064/htdocs/clickandbuilds/SabayTayo/' );
  define('INCOMING_TEXTS_DIR', WORKING_DIR.'tb-in/' );
  define('PROCESSED_TEXTS_DIR', WORKING_DIR.'tb-proc/' );
  define('LOG_DIR', WORKING_DIR.'tb-logs/' );
  define('LOG_FILE', LOG_DIR.APP_NAME.'.log' );
  define('GLOBE_APP_NUMBER', '3363');
  define('LOCK_FILE', WORKING_DIR.APP_NAME.'.lock');
  define('QUERY_FILE', WORKING_DIR.'queries.sql' );
  define('RESPONSE_SMS_PRE', 'TY from SABAYTAYO! ' );
  define('RESPONSE_SMS_POST',  '' );
  define('PHP_FULL_PATH',  '/usr/bin/php5.5-cli' );
  define('ST_PROCESSOR_FILE', WORKING_DIR.'tb-sabaytayo-processor.php' );
  define('SUBSCRIBER_TABLE', 'st_member_mobiles' );

//  define('TIME_WINDOW', 1209600); // 2 weeks
//  define('DEFAULT_TIMEZONE', 'Asia/Manila' );
//  define('DEFAULT_TIMEZONE_OFFSET', '+08:00' );
//  define('WP_USE_THEMES', false );
//  define('TOKEN_SEPARATOR', "|||" );


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

  // get access token of the subscriber number
  function get_access_token($phone_number) {
    global $handle,$wpdb;

    $query = "SELECT access_token FROM ".SUBSCRIBER_TABLE." WHERE subscriber_number = $phone_number" ;
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
    $logfilename = LOG_DIR."/$timestamp.$phone_number.response";
    file_put_contents($logfilename, $message);
  }


/***************************************************************
* FUNCTIONS - End
***************************************************************/

/***************************************************************
* MAIN PROGRAM - Begin
***************************************************************/
  
  // General prep work
  $_SERVER['HTTP_HOST'] = 'sabaytayo.inourshoes.info';
  global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header, $wpdb;
  global $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;

  define('BASE_PATH', find_wordpress_base_path()."/" );
  require(BASE_PATH . 'wp-load.php');

  // Prep for sending SMS via Globe API
  session_start();
  require ('api/PHP/src/GlobeApi.php');
  $globe = new GlobeApi('v1');

  // set up log file
  $handle = fopen(LOG_FILE, 'a') or die('Cannot open file:  '.LOG_FILE);
  $timestamp = time();
  
  // check for presence of lock file; quit program if lock file exists
  $running = file_exists(LOCK_FILE);
  if ($running) {
    if (DEBUG) {
      fwrite($handle, "$timestamp: Process already running.\n");
    }
    exit;
  }

  // initialise lock file
  file_put_contents(LOCK_FILE, $timestamp);
  
  // initialise query file
  file_put_contents(QUERY_FILE, '');
  
  // the MEAT
  $files_to_process = glob(INCOMING_TEXTS_DIR.APP_NAME.".*");
  while (count($files_to_process)>0) {    
    exec(PHP_FULL_PATH.' '.ST_PROCESSOR_FILE.' '.$files_to_process[0]);
    exec("mv $files_to_process[0] ".PROCESSED_TEXTS_DIR);
    $files_to_process = glob(INCOMING_TEXTS_DIR.APP_NAME.".*");
  }

  // read queries file into array
  $queries = file(QUERY_FILE);
  $queries = array_unique($queries);
  
  // query database and notify passengers with matching itineraries
  $c = count($queries);
  for ($i=0; $i < $c ; $i++) {
    $q = array_shift($queries);
    // run query
    $results = $wpdb->get_results($q);
    if (DEBUG) {
      fwrite($handle, "$timestamp: Query Results: ".print_r($results)."\n");
    }
    // build response SMS
    $response_sms = RESPONSE_SMS_PRE;
    $response_sms .= "The ff people are travelling from {$results[0]->port_orig} to {$results[0]->port_dest}: ";   
    $subscribers = array();
    //reset($subscribers);
    foreach ( $results as $r ) {
      array_push($subscribers, $r->subscriber_number);
      // $response_sms .= "$r->subscriber_number (".date('G:i',strtotime($r->dept_time)).", $r->pax pax, $r->notes) ";
      $response_sms .= "$r->subscriber_number ($r->dept_date ".substr($r->dept_time, 0, 5).", $r->pax pax, $r->notes) ";
    }
    // send response SMS to subscribers
    for ($i = 0; $i < sizeof($subscribers); $i++) {
      send_sms($subscribers[$i], $response_sms);
    }
  }
  
  // delete query file
  unlink(QUERY_FILE);

  // delete lock file
  unlink(LOCK_FILE);
  
  // properly close log file
  fclose($handle);
?>
