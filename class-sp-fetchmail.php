<?php
if ( !class_exists("SP_Fetch_Mail") ){

    class SP_Fetch_Mail {

        function __construct(){
            add_filter( 'cron_schedules', array( $this, 'cron_add_every_min' ) );
        }

        /**
         * Add a "Every Minute" option to the cron scheduler
         * @param $schedules
         * @return mixed
         */
        function cron_add_every_min( $schedules ) {
            $schedules['every_minute'] = array(
                'interval' => 60,
                'display' => __( 'Every 1 minute' )
            );
            return $schedules;
        }

        /**
         * Connects to the inbox and fetches new mail.
         *
         * @see sp_create_post_from_email();
         */
        public static function sp_fetch_mail(){

            $sp_email_settings = get_option( 'sp_email_settings' );

            $imap_server_name = $sp_email_settings['imap_server_name'];
            $imap_server_username = $sp_email_settings['imap_server_username'];
            $imap_server_password = $sp_email_settings['imap_server_password'];

            if( empty( $imap_server_name ) ){
                error_log( 'IMAP server name not provided!');
                return;
            }

            if( empty( $imap_server_username ) ){
                error_log( 'IMAP server user name not provided!');
                return;
            }

            if( empty( $imap_server_password ) ){
                error_log( 'IMAP server password not provided!');
                return;
            }

            $hostname = '{' . $imap_server_name . ':993/imap/ssl/novalidate-cert}INBOX';

            // try to connect
            $inbox = imap_open( $hostname, $imap_server_username, $imap_server_password, OP_DEBUG ) or die( print_r(imap_errors(), true) );

            // grab emails
            $search_criteria = isset( $sp_email_settings['sp_email_post_new_emails'] ) ? 'UNSEEN' : 'ALL';

            $emails = imap_search($inbox, $search_criteria );

            // if emails are returned, cycle through each...
            if( $emails ) {
                rsort( $emails );
                foreach( $emails as $email_number ) {

                    // Get e-mail properties
                    $overview = imap_fetch_overview( $inbox, $email_number, 0 );

                    // For now grab the text parts of the message - @see http://stackoverflow.com/questions/5177772/how-to-use-imap-in-php-to-fetch-mail-body-content
                    $message = imap_fetchbody( $inbox, $email_number, 1 );

                    /*
                    $message  = quoted_printable_decode( imap_fetchbody( $inbox, $email_number, 1.1 ) );
                    if( empty( $message ) ){
                        $message = imap_fetchbody( $inbox, $email_number, 2 );
                    }
                    */

                    $header    = imap_headerinfo( $inbox, $email_number );
                    $structure = imap_fetchstructure($inbox, $email_number);

                    // Check that the email received exists in a user
                    $from_address = $header->from[0]->mailbox . "@" . $header->from[0]->host;
                    $email_author = get_user_by( 'email', $from_address );
                    $email_author_id = $email_author->ID;

                    echo 'E-mail recieved from: ' . $from_address . '<br />';

                    if( $email_author !== false ){

                        $subject = $overview[0]->subject;

                        if( $sp_email_settings['sp_email_cat_tag'] ){
                            // Try and get a category name via subject line (format should be "<category-name>:Title of the post")
                            $maybe_get_cat = substr( $subject, 0, strpos( $subject, ':' ) );
                        }

                        if( isset( $maybe_get_cat ) && !empty( $maybe_get_cat ) ){

                            // 1. Try and get cat ID via un-sanitized title
                            $default_sp_cat = get_cat_ID( $maybe_get_cat );

                            // 2. If we didn't get anything, try to get category via slug/sanitized title
                            if( $default_sp_cat === 0 ){

                                $default_sp_cat = get_term_by('slug', sanitize_title( $maybe_get_cat ), 'category' );

                                if( $default_sp_cat === false ){
                                    // 3. Otherwise fallback to default category
                                    $default_sp_cat = $sp_email_settings['sp_email_default_template'];
                                }else{
                                    $default_sp_cat = $default_sp_cat->term_id;
                                }
                            }

                            // Cut the post title after ':'
                            $post_title = substr( $subject, strpos( $subject, ':' ) + 1 );

                        }else{
                            // If we can find a category, fallback to the default cat
                            $default_sp_cat = $sp_email_settings['sp_email_default_template'];
                            $post_title = $overview[0]->subject;
                        }

                        // If there is no subject, default to "User's Category Name Post"
                        if( empty( $post_title ) ){
                            $post_title = $email_author->display_name . '\'s ' . get_cat_name( $default_sp_cat ) . ' post';
                        }

                        $post_id = wp_insert_post(
                            array(
                                'post_title' => trim( $post_title ),
                                'post_author' => $email_author_id,
                                'post_status' => 'publish',
                                'post_category' => array( $default_sp_cat )
                            )
                        );
                        $sp_post = new sp_post( $post_id );

                        $sp_post_comps = $sp_post->getComponents();

                        $sp_gallery_comp     = null;
                        $sp_video_comp       = null;
                        $sp_attachments_comp = null;

                        if( !empty( $sp_post_comps ) ){

                            // Flag to test if content was created or not
                            $content_created = false;
                            foreach( $sp_post_comps as $post_comp ){
                                // If a content component exists, update it
                                if( is_a( $post_comp, 'sp_postContent' ) ){
                                    $post_comp->update( $message );
                                    $content_created = true;
                                }

                                if( is_a( $post_comp, 'sp_postGallery') ){
                                    $sp_gallery_comp = $post_comp;
                                }
                                if( is_a( $post_comp, 'sp_postVideo') ){
                                    $sp_video_comp = $post_comp;
                                }
                                if( is_a( $post_comp, 'sp_postAttachments') ){
                                    $sp_attachments_comp = $post_comp;
                                }
                            }

                            // If there is no content component, then we've got bad news!
                            if( $content_created === false ){
                                // Capture this and report it back to the user
                            }
                        }

                        self::sp_load_attachments( $post_id, $sp_gallery_comp, $sp_video_comp, $sp_attachments_comp, $structure, $inbox, $email_number, $email_author_id );

                        $wp_post = $sp_post->getwpPost();
                        if( is_admin() ){
                            echo 'Post titled: "<a href="' . get_permalink( $wp_post->ID ) . '"" />' . $wp_post->post_title . '</a>" created via e-mail! <br />';
                            echo '<br /><br />';
                        }
                    }
                } // end foreach
            }
            imap_close($inbox);
        }

        /**
         * Handles e-mail attachments and adds them to the appropriate components
         * If attachment is an image (.jpg, .jpeg, .png, .gif), then it will be added
         * to a "Picture component". If more than one image exists, it will be
         * created in a gallery.
         *
         * If attachment is a video (.mov, .avi, .mp4, .m4v), then it will be added
         * to the video component and converted if need be.
         *
         * All other file types will be added to the attachment component
         * @link http://www.codediesel.com/php/downloading-gmail-attachments-using-php/
         *
         * @param $post_id
         * @param $gallery_comp
         * @param $video_comp
         * @param $attach_comp
         * @param $email_structure
         * @param $inbox
         * @param $email_number
         * @param $email_author_id
         */
        function sp_load_attachments( $post_id, sp_postGallery &$gallery_comp = null, sp_postVideo &$video_comp = null,
                                      sp_postAttachments &$attach_comp = null, $email_structure, $inbox, $email_number, $email_author_id ){

            $attachments = array();

            // if any attachments found ...
            if( isset( $email_structure->parts ) && count( $email_structure->parts ) )
            {
                for( $i = 0; $i < count( $email_structure->parts ); $i++ )
                {
                    $attachments[$i] = array(
                        'is_attachment' => false,
                        'filename' => '',
                        'name' => '',
                        'attachment' => ''
                    );

                    // Gather attachment filename ...
                    if( $email_structure->parts[$i]->ifdparameters )
                    {
                        foreach( $email_structure->parts[$i]->dparameters as $object )
                        {
                            if( strtolower($object->attribute) == 'filename' )
                            {
                                $attachments[$i]['is_attachment'] = true;
                                $attachments[$i]['filename'] = $object->value;
                            }
                        }
                    }

                    // Gather attachment name ...
                    if( $email_structure->parts[$i]->ifparameters )
                    {
                        foreach( $email_structure->parts[$i]->parameters as $object )
                        {
                            if( strtolower($object->attribute) == 'name' )
                            {
                                $attachments[$i]['is_attachment'] = true;
                                $attachments[$i]['name'] = $object->value;
                            }
                        }
                    }

                    // Gather attachment binaries
                    if( $attachments[$i]['is_attachment'] )
                    {
                        $attachments[$i][ 'attachment' ] = imap_fetchbody( $inbox, $email_number, $i+1 );

                        // 4 = QUOTED-PRINTABLE encoding
                        if($email_structure->parts[$i]->encoding == 3)
                        {
                            $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                        }
                        // 3 = BASE64 encoding
                        elseif($email_structure->parts[$i]->encoding == 4)
                        {
                            $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                        }
                    }
                }
            }

            // iterate through each attachment and save it
            foreach( $attachments as $attachment )
            {
                if( $attachment['is_attachment'] == 1 )
                {
                    $filename = $attachment['name'];
                    if( empty( $filename ) ){
                        $filename = $attachment['filename'];
                    }

                    if( empty( $filename ) ){
                        $filename = time() . ".dat";
                    }

                    // place the attachments in the WP upload directory
                    $wp_upload_dir = wp_upload_dir();

                    /* Prefix the email number to the filename in case two emails
                     * have the attachment with the same file name.
                     */
                    $full_filename = $wp_upload_dir['path'] . DIRECTORY_SEPARATOR . $email_number . "-" . $filename;

                    $fp = fopen( $full_filename, "w+");
                    fwrite( $fp, $attachment['attachment'] );
                    fclose( $fp );

                    // correspond attachments with components
                    $file_ext = strtolower( pathinfo( $full_filename, PATHINFO_EXTENSION ) );

                    //error_log( 'attachment extension: ' . $file_ext );

                    switch( $file_ext ){
                        case 'jpg' :
                        case 'jpeg':
                        case 'png' :
                        case 'gif' :
                        case 'tiff':
                        case 'tif' :
                            // add to gallery component
                            if( !is_null( $gallery_comp ) ){
                                $attach_id = sp_core::create_attachment( $full_filename, $post_id, $filename, $email_author_id );
                                $allowed_exts = array( 'jpeg', 'jpg', 'tiff', 'tif' );
                                if( in_array( $file_ext, $allowed_exts) ){
                                    sp_core::fixImageOrientation( get_attached_file( $attach_id ) );
                                }
                                array_push( $gallery_comp->attachmentIDs, $attach_id );
                                $gallery_comp->update();
                            }
                            break;
                        case 'mov':
                        case 'mp4':
                        case 'avi':
                        case 'm4v':
                            if( !is_null( $video_comp ) ){
                                // add to video component & convert
                                $video_comp::encode_via_ffmpeg( $video_comp, $full_filename );
                            }
                            break;
                        default:
                            // add to attachments component
                            if( !is_null( $attach_comp ) ){
                                $attach_id = sp_core::create_attachment( $full_filename, $post_id, $filename, $email_author_id );
                                array_push( $attach_comp->attachmentIDs, $attach_id );
                                $attach_comp->update();
                            }
                            break;
                    }
                }
            }
        } //sp_load_attachments()
    } // end class

    $sp_fetch_mail = new SP_Fetch_Mail();
}