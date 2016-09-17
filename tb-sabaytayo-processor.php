<?php

/***************************************************************
Project: Sabay Tayo
File: sabaytayo-process-one-file.php
Created by: Kenneth See
Description:
  Take in a formatted file as input. Data in the file is inserted into the database. Output is a file containing SQL queries to be executed by the next process in the chain.
  
  Format of file: timestamp|||subscriber number|||text message. 
  Assumption: file has already been checked for errors and possible cracking attempts. 
***************************************************************/

/***************************************************************
* CONSTANTS - Begin
***************************************************************/

  define('APP_NAME', 'sabaytayo' );
  define('DEBUG', true );
  define('TOKEN_SEPARATOR', "|||" );
  define('PARAM_SEPARATOR', "/" );
  define('WORKING_DIR', '/kunden/homepages/41/d579830064/htdocs/clickandbuilds/SabayTayo/' );
  define('LOG_DIR', WORKING_DIR.'tb-logs/' );
  define('LOG_FILE', LOG_DIR.APP_NAME.'.log' );
  define('QUERY_FILE', WORKING_DIR.'queries.sql' );
  define('DEFAULT_TIMEZONE', 'Asia/Manila' );
  define('DEFAULT_TIMEZONE_OFFSET', '+08:00' );
  define('TIME_WINDOW', 1209600); // 2 weeks
  define('TRIPS_TABLE', 'st_trips' );

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

  function split_parameters($t) {
    global $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;
    global $handle, $timestamp;
    
    // parse parameters
    $parameters = explode(PARAM_SEPARATOR, $t);

    $port_orig = strtoupper($parameters[0]);
    $port_dest = strtoupper($parameters[1]);
    $dept_date = $parameters[2];
    $dept_time = $parameters[3];
    $pax       = $parameters[4];
    $notes     = $parameters[5];

    if (DEBUG) {
      fwrite($handle, "$timestamp: Port of Origin = $port_orig, Port of Destination = $port_dest, Departure Date = $dept_date, Departure Time = $dept_time, Pax = $pax, Notes = $notes\n");
    }
  }
  
/***************************************************************
* FUNCTIONS - End
***************************************************************/
  
/***************************************************************
* MAIN PROGRAM - Begin
***************************************************************/

  // make sure filename is passed to PHP as parameter
  if (count($argv) < 2) {
    die("Usage: $argv[0] <filename>\n");
  }

  // General prep work
  $_SERVER['HTTP_HOST'] = 'sabaytayo.inourshoes.info';
  define('WP_ADMIN', true);
  define('BASE_PATH', find_wordpress_base_path()."/" );
  require(BASE_PATH . 'wp-load.php');
  global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header, $wpdb;
  global $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes;

  // set up log file
  $handle = fopen(LOG_FILE, 'a') or die('Cannot open file:  '.LOG_FILE);

  date_default_timezone_set(DEFAULT_TIMEZONE);
  if (DEBUG) {
    fwrite($handle, "Timezone = ".DEFAULT_TIMEZONE."\n");
  }

  // ASSUMPTION: file contents follow the pattern: timestamp|||subscriber_number|||text_message  
  // read file and split into various components
  $input_filename = $argv[1]; // add error checking here to make sure filename is legit 
  $contents = file_get_contents($input_filename, true); // assumption: there's only 1 line of SMS in the file
  $inputline = explode(TOKEN_SEPARATOR, $contents);
  $timestamp = $inputline[0];
  $subscriber_number = $inputline[1];
  $text = $inputline[2];

  if (DEBUG) {
    fwrite($handle, "$timestamp: Subscriber Number = $subscriber_number, Text Message = $text\n");
  }

  split_parameters($text);
  if ($dept_date == '')
    $dept_date = '0000-00-00';
  
  // insert entry into sabaytayo table in database
  $wpdb->replace(TRIPS_TABLE, array(
      'subscriber_number' => $subscriber_number
    , 'port_orig' => $port_orig
    , 'port_dest' => $port_dest
    , 'dept_date' => $dept_date
    , 'dept_time' => $dept_time
    , 'pax' => $pax
    , 'notes' => $notes
    , 'timezone' => DEFAULT_TIMEZONE_OFFSET
    , 'dept_timestamp' => strtotime("$dept_date $dept_time")
    , 'timestamp' => $timestamp
    ), array('%s', '%s', '%s', '%s', '%s','%d', '%s', '%s', '%d', '%d' )
  );
  if (DEBUG) {
    fwrite($handle, "$timestamp: Inserting into database: $subscriber_number, $port_orig, $port_dest, $dept_date, $dept_time, $pax, $notes, ". DEFAULT_TIMEZONE_OFFSET . ", " . strtotime("$dept_date $dept_time") . ", $timestamp\n");
  }

  // prepare query
  if ($dept_date == '0000-00-00') {
    $query .= "SELECT * FROM ".TRIPS_TABLE;
    $query .= " WHERE port_orig = '$port_orig' ";
    $query .= "AND   port_dest = '$port_dest' ";
    $query .= "AND   dept_date <> '0000-00-00' ";
    $query .= "AND   dept_timestamp - unix_timestamp() < ".TIME_WINDOW;
    $query .= " UNION ";
    $query .= "SELECT * FROM ".TRIPS_TABLE;
    $query .= " WHERE port_orig = '$port_orig' ";
    $query .= "AND   port_dest = '$port_dest' ";
    $query .= "AND   dept_date = '0000-00-00' ";
    $query .= "AND   timestamp - unix_timestamp() < ".TIME_WINDOW;
  } else {  
    $query  = "SELECT * FROM ".TRIPS_TABLE;
    $query .= " WHERE port_orig = '$port_orig' ";
    $query .= "AND   port_dest = '$port_dest' ";
    $query .= "AND   dept_date = '$dept_date' ";
    $query .= "AND   STR_TO_DATE(CONCAT(dept_date, ' ', dept_time), '%Y-%m-%d %H:%i:%s')  >= convert_tz(NOW(),'-4:00','+8:00') "; // *** this is hardcoded to Eastern DAYLIGHT Time - find a way to remove dependency!!!
    $query .= " UNION ";
    $query .= "SELECT * FROM ".TRIPS_TABLE;
    $query .= " WHERE port_orig = '$port_orig' ";
    $query .= "AND   port_dest = '$port_dest' ";
    $query .= "AND   dept_date = '0000-00-00' ";
    $query .= "AND   unix_timestamp() - timestamp < ".TIME_WINDOW;
  }  
  if (DEBUG) {
    fwrite($handle, "$timestamp: SQL Query: $query\n");
  }

  // add query statement into query file
  file_put_contents(QUERY_FILE, "$query\n", FILE_APPEND | LOCK_EX);

/***************************************************************
* MAIN PROGRAM - End
***************************************************************/

  fclose($handle);
?>