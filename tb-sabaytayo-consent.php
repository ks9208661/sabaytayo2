<?php

/***************************************************************
*
* Sabay Tayo (c)
* Created by: Kenneth See
*
***************************************************************/

/***************************************************************
* CONSTANTS - Begin
***************************************************************/

  // define('DEBUG', true );
  // define('WORKING_DIR', '/kunden/homepages/41/d579830064/htdocs/clickandbuilds/SabayTayo/' );
  // define('LOG_DIR', WORKING_DIR.'tb-logs/' );
  // define('LOG_FILE', LOG_DIR.'sabaytayo-subscriber-consent.log' );
  // define('SUBSCRIBER_TABLE', 'st_member_mobiles' );
  
  
/***************************************************************
* CONSTANTS - End
***************************************************************/

/***************************************************************
* FUNCTIONS - Begin
***************************************************************/

  // //load Wordpress environment
  // function find_wordpress_base_path() {
    // $dir = dirname(__FILE__);
    // do {
      // //it is possible to check for other files here
      // if( file_exists($dir."/wp-config.php") ) {
        // return $dir;
      // }
    // } while( $dir = realpath("$dir/..") );
      // return null;
  // }

/***************************************************************
* FUNCTIONS - End
***************************************************************/

/***************************************************************
* MAIN PROGRAM - Begin
***************************************************************/
  
  require_once('./tb-sabaytayo-requirements.php');
  require_once(WP_LOAD_FILE);
  // define('BASE_PATH', find_wordpress_base_path()."/");
  // require(BASE_PATH . 'wp-load.php');
  global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header, $wpdb;

  // set up log file
  $handle = fopen(LOG_FILE, 'a') or die('Cannot open file:  '.LOG_FILE);

  $access_token      = $_GET["access_token"];
  $subscriber_number = "+63".$_GET["subscriber_number"];

  // save access token and subscriber number in database

  
  $query = " SELECT * FROM ",SUBSCRIBER_TABLE." WHERE subscriber_number = '$subscriber_number' " ;
  echo "Query = $query\n";

  $row = $wpdb->get_row($query);  
  if ($row == null) {

  } else {
    $wpdb->replace(SUBSCRIBER_TABLE, array(
        'subscriber_number' => $subscriber_number
      , 'access_token' => $access_token
      , 'activation_timestamp' => time()
      , 'current' => 1
      ), array('%s', '%s', '%d', '%d')
    );
  }  
  
  if (DEBUG) {
    fwrite($handle, "Member added/replaced. Subscriber Number = $subscriber_number , Access Token = $access_token\n");
  }

  fclose($handle);
  
/***************************************************************
* MAIN PROGRAM - End
***************************************************************/

?>