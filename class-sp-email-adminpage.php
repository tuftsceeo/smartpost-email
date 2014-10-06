<?php
if( !class_exists('SP_Email_Admin_Page') ){
    class SP_Email_Admin_Page{

        function __construct(){
            add_action( 'admin_notices', array( $this, 'show_settings_errors') );
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
            //error_log( print_r( $input, true ) );

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
                    <td>IMAP Server Name (i.e. imap.gmail.com): </td>
                    <td><input type="text" name="<?php echo $arg['id'] ?>[imap_server_name]" value="<?php echo $sp_email_settings['imap_server_name'] ?>" /></td>
                </tr>
                <tr>
                    <td>Username: </td>
                    <td><input type="text" name="<?php echo $arg['id'] ?>[imap_server_username]" value="<?php echo $sp_email_settings['imap_server_username'] ?>" /> </td>
                </tr>
                <tr>
                    <td>Password: </td>
                    <td><input type="password" name="<?php echo $arg['id'] ?>[imap_server_password]" value="<?php echo $sp_email_settings['imap_server_password'] ?>" /></td>
                </tr>
                <tr>
                    <td><label for="<?php echo $arg['id'] ?>[sp_email_post_new_emails]">Post only new (unread) e-mails:</label></td>
                    <td><input type="checkbox" id="<?php echo $arg['id'] ?>[sp_email_post_new_emails]" name="<?php echo $arg['id'] ?>[sp_email_post_new_emails]" <?php echo empty( $sp_email_settings['sp_email_post_new_emails'] ) ? '' : 'checked'; ?> value="true" /></td>
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
            //$sp_email_settings = get_option( 'sp_email_settings' );
            //print_r( $sp_email_settings );
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
                    submit_button( 'Save' );
                ?>
                </form>
            <?php

            if( version_compare( SP_VERSION, "2.3.7" ) >= 0 ){
                $sp_fetch_mail = new SP_Fetch_Mail();
                $sp_fetch_mail->sp_fetch_mail();
            }
        }
    }
    $sp_admin_page = new SP_Email_Admin_Page();
}