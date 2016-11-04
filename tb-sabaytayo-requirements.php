<?php

/***************************************************************
*
* Sabay Tayo (c)
* Created by: Kenneth See
*
* Application-specific constants, functions, etc.
*
***************************************************************/

/***************************************************************
* CONSTANTS - Begin
***************************************************************/

  define('APP_NAME', 'sabaytayo' );
  define('DEBUG', true );
  define('WORKING_DIR', '/kunden/homepages/41/d579830064/htdocs/clickandbuilds/SabayTayo/' );
  define('WP_LOAD_FILE', WORKING_DIR.'wp-load.php' );
  define('LOG_DIR', WORKING_DIR.'tb-logs/' );
  define('LOG_FILE', LOG_DIR.APP_NAME.'-subscriber-consent.log' );
  define('SUBSCRIBER_TABLE', 'st_member_mobiles' );

/***************************************************************
* CONSTANTS - End
***************************************************************/

/***************************************************************
* FUNCTIONS - Begin
***************************************************************/

  // load Wordpress environment
  function find_wordpress_base_path() {
    $dir = dirname(__FILE__);
    do {
      //it is possible to check for other files here
      if( file_exists($dir."/wp-config.php") ) {
        return $dir;
      }
    } while( $dir = realpath("$dir/..") );
      return null;
  }

/***************************************************************
* FUNCTIONS - End
***************************************************************/




  // define('DEFAULT_TIMEZONE', 'Asia/Manila' );
  // define('DEFAULT_TIMEZONE_OFFSET', '+08:00' );
  // define('WORKING_DIR', '/kunden/homepages/41/d579830064/htdocs/clickandbuilds/SabayTayo/' );
  // define('LOG_DIR', WORKING_DIR.'tb-logs/' );
  // define('LOG_FILE', LOG_DIR.APP_NAME.'.log' );
  // define('INCOMING_TEXTS_DIR', WORKING_DIR.'tb-in/' );
  // define('GLOBE_APP_NUMBER', '3363');
  // define('TOKEN_SEPARATOR', "|||" );
  // define('PHP_FULL_PATH',  '/usr/bin/php5.5-cli' );
  // define('ST_POLLER', WORKING_DIR.'tb-sabaytayo-poller.php' );
  // define('SUBSCRIBER_TABLE', 'st_member_mobiles' );
  
  
?>
