<?php
if( !class_exists('SP_Email_Admin_Page') ){
    class SP_Email_Admin_Page{

        function __construct(){
            add_action( 'admin_menu', array($this, 'sp_add_email_admin_page') );
            add_action( 'admin_enqueue_scripts', array($this, 'admin_enqueue_scripts') );
        }

        /**
         * Adds the e-mail settings page under the SmartPost parent menu
         */
        function sp_add_email_admin_page(){
            if( ( is_plugin_active( "SmartPost2.0/smartpost.php" ) || is_plugin_active( "smartpost-templates/smartpost.php" ) ) && defined( "SP_PLUGIN_NAME" ) ){
                add_submenu_page( 'smartpost', 'E-mail Settings', 'E-mail Settings', 'edit_dashboard', 'sp-email-settings', array($this, 'sp_render_email_admin_page') );
            }
        }

        /**
         * Loads css/js files
         */
        function admin_enqueue_scripts( $hook ){

            if( 'toplevel_page_smartpost' != $hook && 'smartpost_page_sp-cat-page' != $hook && 'smartpost_page_sp-email-settings' != $hook ){
                return;
            }

            wp_enqueue_style( 'sp-email-admin-page-css', plugins_url('css/sp-email-admin-page.css', __FILE__) );

        }

        /**
         * Renders the E-mail Settings Page
         */
        function sp_render_email_admin_page(){
            ?>
            <div class="wrap">
                <h2 id="sp-email-admin-page-header"><?php echo SP_EMAIL_PLUGIN_NAME ?></h2>
                <small>(Version <?php echo SP_EMAIL_VERSION ?>)</small>
                <p>This plugin connects to any IMAP E-mail server and converts e-mails in an inbox into SmartPost "style" posts.</p>
                <p>Below you can configure the inbox you'd like to connect to:</p>
            <?php
        }
    }
    $sp_admin_page = new SP_Email_Admin_Page();
}