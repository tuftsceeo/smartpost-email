<?php
if( !class_exists('SP_Email_Admin_Page') ){
    class SP_Email_Admin_Page{

        function __construct(){
            add_action( 'admin_menu', array($this, 'sp_add_email_admin_page') );
            add_action( 'admin_enqueue_scripts', array($this, 'admin_enqueue_scripts') );
            add_action( 'admin_init', array( $this, 'page_init' ) );
        }

        /**
         * Adds the e-mail settings page under the SmartPost parent menu
         */
        function sp_add_email_admin_page(){
            if( ( is_plugin_active( "SmartPost2.0/smartpost.php" ) || is_plugin_active( "smartpost-templates/smartpost.php" ) ) && defined( "SP_PLUGIN_NAME" ) ){
                add_submenu_page(
                    'smartpost',
                    'E-mail Settings',
                    'E-mail Settings',
                    'edit_dashboard',
                    'sp_email_settings',
                    array($this, 'sp_render_email_admin_page')
                );
            }
        }

        /**
         * Loads css/js files
         */
        function admin_enqueue_scripts( $hook ){
            if( 'toplevel_page_smartpost' != $hook && 'smartpost_page_sp-cat-page' != $hook && 'smartpost_page_sp_email_settings' != $hook ){
                return;
            }
            wp_enqueue_style( 'sp-email-admin-page-css', plugins_url('css/sp-email-admin-page.css', __FILE__) );
        }

        /**
         * Registers sp-email settings page
         */
        function page_init(){
            register_setting(
                'sp_email_settings', // Option group
                'sp_email_settings', // Option name
                array( $this, 'sanitize_sp_email_settings' ) // Sanitize
            );

            add_settings_section(
                'sp_email_configuration', // ID
                'SmartPost E-mail configuration settings', // Title
                array( $this, 'sp_email_configuration_header' ), // Callback
                'smartpost_page_sp_email_settings' // Page
            );

            add_settings_field(
                'sp_email_configuration_inputs', // ID
                'E-Mail Server Settings:', // Title
                array( $this, 'sp_email_configuration_form' ), // Callback
                'smartpost_page_sp_email_settings', // Page
                'sp_email_configuration',
                array( 'id' => 'sp_email_settings' )
            );
        }

        /**
         * Checks for empty input, if not empty, sanitizes the input
         * @param $input
         * @return mixed
         */
        function sanitize_sp_email_settings( $input ){
            /*
            if( empty( $input[''] ) ){
                add_settings_error( 'gd_step_title', 'gd_step_title_empty', 'Please give this step a name' );
                return;
            }
            */
            return $input;
        }

        /**
         * Callback to sp_email_configuration in page_init()
         * @see page_init()
         */
        function sp_email_configuration_header(){
            echo '<p>Configure e-mail settings below:</p>';
        }

        /**
         * Callback to sp_email_configuration_form in page_init()
         * @see page_init()
         */
        function sp_email_configuration_form( $arg ){
            $sp_email_settings = get_option( 'sp_email_settings' );
            ?>
            <table style="border: 1px solid #cccccc; border-radius: 3px;">
                <tr>
                    <td>IMAP Server Name: </td>
                    <td><input type="text" name="<?php echo $arg['id'] ?>[imap_server_name]" value="<?php echo $sp_email_settings['imap_server_name'] ?>" /></td>
                </tr>
                <tr>
                    <td>Username: </td>
                    <td><input type="text" name="<?php echo $arg['id'] ?>[imap_server_username]" value="<?php echo $sp_email_settings['imap_server_username'] ?>" /> </td>
                </tr>
                <tr>
                    <td>Password: </td>
                    <td><input type="text" name="<?php echo $arg['id'] ?>[imap_server_password]" value="<?php echo $sp_email_settings['imap_server_password'] ?>" /></td>
                </tr>
            </table>
            <?php
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
                <form id="sp_email_configuration_form" method="post" action="options.php">
                <?php
                    // This prints out all hidden setting fields
                    settings_fields( 'sp_email_settings' );
                    do_settings_sections( 'smartpost_page_sp_email_settings' );
                    submit_button( 'Save' );
                ?>
                </form>
            <?php
            // $sp_fetch_mail = new SP_Fetch_Mail();
            // $sp_fetch_mail->sp_fetch_mail();
        }
    }
    $sp_admin_page = new SP_Email_Admin_Page();
}