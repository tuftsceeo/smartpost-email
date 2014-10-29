<?php
/*
Plugin Name: SmartPost E-mail
Plugin URI: http://sptemplates.org
Description: This plugin connects to any IMAP E-mail server and converts e-mails in an inbox into SmartPost "style" posts
Version: 0.0.1
Author: RafiLabs
Author URI: http://www.rafilabs.com/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
define( "SP_EMAIL_VERSION", "0.0.1" );
define( "SP_EMAIL_PLUGIN_NAME", "SmartPost E-Mail" );
define( "SP_EMAIL_DEBUG", false );

if( !class_exists( "SP_Email" ) ){

    class SP_Email{
        function __construct(){
            require_once( "class-sp-email-adminpage.php" );
            require_once( "class-sp-fetchmail.php" );
        }
    }

    $sp_email = new SP_Email();
}