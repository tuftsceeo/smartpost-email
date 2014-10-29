<?php
if( !class_exists('SP_Email_Admin_Page') ){
    class SP_Email_Admin_Page{

        function __construct(){
            add_action( 'admin_notices', array( $this, 'show_settings_errors') );
            add_action( 'sp_add_submenus', array($this, 'sp_add_email_admin_page') );
            add_action( 'admin_enqueue_scripts', array($this, 'admin_enqueue_scripts') );
            add_action( 'admin_init', array( $this, 'page_init' ) );
            add_action( 'admin_init', array( 'SP_Email_Admin_Page', 'sp_email_setup_schedule' ) );
            add_action( 'sp_email_check_emails', array( $this, 'check_emails' ) );
        }

        /**
         * Adds the e-mail settings page under the SmartPost parent menu
         */
        function sp_add_email_admin_page(){
            if( defined( "SP_PLUGIN_NAME" ) ){
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
            wp_enqueue_script( 'sp-email-admin-js', plugins_url( 'js/sp-email-admin.js', __FILE__ ) );
            wp_enqueue_style( 'sp-email-admin-page-css', plugins_url('css/sp-email-admin-page.css', __FILE__) );
        }

        /**
         * Provides user with error notices
         */
        function show_settings_errors() {
            $errors = get_settings_errors();
            if( !empty( $errors ) ){
                foreach( $errors as $error ){
                    ?>
                    <div class="<?php echo $error['type'] ?>">
                        <p><?php echo $error['message']; ?></p>
                    </div>
                <?php
                }
            }
        }

        /**
         * Sets up the frequency e-mails are checked
         */
        function sp_email_setup_schedule(){
            if ( ! wp_next_scheduled( 'sp_email_check_emails' ) ) {
                $sp_email_settings = get_option( 'sp_email_settings' );
                $frequency = $sp_email_settings['sp_email_fetch_frequency'];

                if( isset( $frequency ) && $frequency !== 0 ){
                    wp_schedule_event( time(), $frequency, 'sp_email_check_emails');
                }
            }
        }

        /**
         * Checks e-mails based on what is setup in sp_email_setup_schedule()
         */
        function check_emails(){
            if( version_compare( SP_VERSION, "2.3.7" ) >= 0 ){
                error_log( 'SP Emails: Checking e-mail now!' );
                $sp_fetch_mail = new SP_Fetch_Mail();
                $sp_fetch_mail->sp_fetch_mail();
            }
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
                'sp_email_configuration_form', // ID
                'E-Mail Server Settings:', // Title
                array( $this, 'sp_email_configuration_form' ), // Callback
                'smartpost_page_sp_email_settings', // Page
                'sp_email_configuration',
                array( 'id' => 'sp_email_settings' )
            );

            add_settings_field(
                'sp_email_sp_post_behavior', // ID
                'New Post Behavior:', // Title
                array( $this, 'sp_email_sp_post_behavior' ), // Callback
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

            if( empty( $input['imap_server_name'] ) ){
                add_settings_error( 'sp_email_configuration_form', 'sp_imap_server_name_empty', 'Error: Please provide an IMAP server name!' );
            }

            if( empty( $input['imap_server_username'] ) ){
                add_settings_error( 'sp_email_configuration_form', 'sp_imap_user_name_empty', 'Error: Please provide a username!' );
            }

            if( empty( $input['imap_server_password'] ) ){
                add_settings_error( 'sp_email_configuration_form', 'sp_imap_password_empty', 'Error: Please provide a password!' );
            }

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
                    <td><i>(i.e. imap.gmail.com)</i></td>
                </tr>
                <tr>
                    <td>Username: </td>
                    <td><input type="text" name="<?php echo $arg['id'] ?>[imap_server_username]" value="<?php echo $sp_email_settings['imap_server_username'] ?>" /> </td>
                    <td><i>The username used for logging into the e-mail account (i.e. username@gmail.com)</i></td>
                </tr>
                <tr>
                    <td>Password: </td>
                    <td><input type="password" name="<?php echo $arg['id'] ?>[imap_server_password]" value="<?php echo $sp_email_settings['imap_server_password'] ?>" /></td>
                    <td><i>The password used for logging into the e-mail account</i></td>
                </tr>
                <tr>
                    <td><label for="<?php echo $arg['id'] ?>[sp_email_post_new_emails]">Post only new (unread) e-mails:</label></td>
                    <td><input type="checkbox" id="<?php echo $arg['id'] ?>[sp_email_post_new_emails]" name="<?php echo $arg['id'] ?>[sp_email_post_new_emails]" <?php echo empty( $sp_email_settings['sp_email_post_new_emails'] ) ? '' : 'checked'; ?> value="true" /></td>
                    <td><i>Only unread e-mails will be posted</i></td>
                </tr>
                <tr>
                    <td>Schedule for checking e-mails:</td>
                    <td>
                        <select name="<?php echo $arg['id'] ?>[sp_email_fetch_frequency]">
                            <option value="0">Select a schedule ...</option>
                            <?php
                                $sp_email_frequency = $sp_email_settings['sp_email_fetch_frequency'];
                                $freq_options = array('hourly' => 'Hourly', 'daily' => 'Daily', 'twicedaily' => 'Twice Daily');
                                foreach( $freq_options as $freq_id => $freq_lbl ){
                                    $selected = $sp_email_frequency == $freq_id ? 'selected' : '';
                                    echo '<option value="' . $freq_id . '" ' . $selected . '>' . $freq_lbl . '</option>';
                                }
                            ?>
                        </select>
                    </td>
                    <td><i>How often to check for new e-mails</i></td>
                </tr>
            </table>
            <?php
        }

        /**
         * Callback to sp_email_sp_post_behavior in page_init()
         * @see page_init();
         * @param $arg
         */
        function sp_email_sp_post_behavior( $arg ){
            $sp_categories = get_option( 'sp_categories' );
            $sp_email_settings = get_option( 'sp_email_settings' );
            ?>
            <table style="border: 1px solid #cccccc; border-radius: 3px;">
                <tr>
                    <td>
                        <label for="<?php echo $arg['id'] ?>[sp_email_cat_tag]">Enable category tagging:</label>
                    </td>
                    <td>
                        <input type="checkbox" id="<?php echo $arg['id'] ?>[sp_email_cat_tag]" name="<?php echo $arg['id'] ?>[sp_email_cat_tag]" <?php echo empty( $sp_email_settings['sp_email_cat_tag'] ) ? '' : 'checked'; ?> value="true" />
                    </td>
                    <td>
                        <i>
                           Allows users to tag their e-mails in a category in the subject line.
                           It should follow the form [category name]: [post title] - i.e. "Assignment 1: My assignment 1 post" will be tagged in
                           the category "Assignment 1" if it exists, otherwise it will fall back to the default category picked in the "Default SmartPost template"
                           setting.
                        </i>
                    </td>
                </tr>

                <tr>
                    <td>Default SmartPost template:</td>
                    <td>
                        <?php if( !empty( $sp_categories ) ): ?>
                        <select name="<?php echo $arg['id'] ?>[sp_email_default_template]">
                            <option value="0">Select a template ... </option>
                            <?php
                                foreach( $sp_categories as $sp_cat ){
                                    if( term_exists( $sp_cat, 'category' ) ){
                                        $selected = $sp_email_settings['sp_email_default_template'] == $sp_cat ? 'selected' : '';
                                        echo '<option value="' . $sp_cat . '" ' . $selected . '>' . get_cat_name( $sp_cat ) . '</option>';
                                    }
                                }
                            ?>
                        </select>
                        <?php endif; ?>
                    </td>
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
                <p><b><a href="#" onclick="jQuery('#sp-email-notes').fadeToggle();">PLEASE CLICK ME BEFORE CONTINUING - IMPORTANT INFO !</a></b></p>
                <ul id="sp-email-notes" style="list-style: circle; margin-left: 50px; display: none;">
                    <li>This plugin only works with SmartPost version 2.3.7 and up.</li>
                    <li>Incoming e-mails are matched with user e-mails on the WordPress database, if no match is made, the e-mail will not be posted.</li>
                    <li>Incoming e-mails are limited to a <b>maximum of one</b> video attachment.</li>
                    <li>Your e-mail password is stored in <b>PLAINTEXT</b>, please be aware of this. Future versions will hash it.</li>
                    <li>Only IMAP servers are supported at this  time.</li>
                    <li>Only SSL (port 993) connections are supported at this time - this means your IMAP E-mail server has to support SSL connection on port 993.</li>
                </ul>


                <form id="sp_email_configuration_form" method="post" action="options.php">
                <?php
                    // This prints out all hidden setting fields
                    settings_fields( 'sp_email_settings' );
                    do_settings_sections( 'smartpost_page_sp_email_settings' );
                    submit_button( 'Save Settings', 'primary', 'submit', false );
                ?>
                <!-- <button id="sp-email-check-now" type="button" class="button button-primary">Check For E-mails Now</button> -->
                </form>
            <?php
        }
    }
    $sp_admin_page = new SP_Email_Admin_Page();
}