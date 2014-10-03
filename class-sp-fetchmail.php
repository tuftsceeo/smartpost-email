<?php
if ( !class_exists("SP_Fetch_Mail") ){

    class SP_Fetch_Mail {

        function __construct(){

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
        function sp_load_attachments( $post_id, sp_postGallery &$gallery_comp, sp_postVideo &$video_comp,
                                      sp_postAttachments &$attach_comp, $email_structure, $inbox, $email_number, $email_author_id ){

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

                    error_log( 'attachment extension: ' . $file_ext );

                    switch( $file_ext ){
                        case 'jpg' :
                        case 'jpeg':
                        case 'png' :
                        case 'gif' :
                        case 'tiff':
                        case 'tif' :
                            // add to gallery component
                            $attach_id = sp_core::create_attachment( $full_filename, $post_id, $filename, $email_author_id );
                            $allowed_exts = array( 'jpeg', 'jpg', 'tiff', 'tif' );
                            if( in_array( $file_ext, $allowed_exts) ){
                                sp_core::fixImageOrientation( get_attached_file( $attach_id ) );
                            }
                            array_push( $gallery_comp->attachmentIDs, $attach_id );
                            $gallery_comp->update();
                            break;
                        case 'mov':
                        case 'mp4':
                        case 'avi':
                        case 'm4v':
                            // add to video component & convert
                            $video_comp::encode_via_ffmpeg( $video_comp, $full_filename );
                            break;
                        default:
                            // add to attachments component
                            $attach_id = sp_core::create_attachment( $full_filename, $post_id, $filename, $email_author_id );
                            array_push( $attach_comp->attachmentIDs, $attach_id );
                            $attach_comp->update();
                            break;
                    }
                }
            }
        }

        /**
         * Connects to the inbox and fetches new mail.
         *
         * @see sp_create_post_from_email();
         */
        function sp_fetch_mail(){

            // @todo: grab these parameters from an option
            $hostname = '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX';
            $username = 'k12engineering@gmail.com';
            $password = 'cee0prek!474';

            /* try to connect */
            $inbox = imap_open($hostname,$username,$password, OP_DEBUG) or die( print_r(imap_errors(), true) );

            // grab emails, @todo: create a flag for ALL or NEW
            $emails = imap_search($inbox, 'ALL' );

            // if emails are returned, cycle through each...
            if( $emails ) {
                rsort( $emails );
                foreach( $emails as $email_number ) {

                    // Get e-mail properties
                    $overview  = imap_fetch_overview( $inbox, $email_number, 0 );
                    //$message   = imap_fetchbody( $inbox, $email_number, 2 );
                    $header    = imap_headerinfo( $inbox, $email_number );
                    $structure = imap_fetchstructure($inbox, $email_number);

                    // Check that the email received exists in a user
                    $from_address = $header->from[0]->mailbox . "@" . $header->from[0]->host;
                    $email_author = get_user_by( 'email', $from_address );
                    $email_author_id = $email_author->ID;

                    echo 'E-mail recieved from: ' . $from_address . '<br />';

                    if( $email_author !== false ){

                        $post_id = wp_insert_post(
                            array(
                                'post_title' => $overview[0]->subject,
                                'post_author' => $email_author_id,
                                'post_status' => 'publish',
                                'post_category' => array( 2 )
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
                                    error_log( 'Content component exists! Attempting to update it ... ' );
                                    //$post_comp->update( $message );
                                    $content_created = true;
                                }

                                if( is_a( $post_comp, 'sp_postGallery') ){
                                    $sp_gallery_comp = $post_comp;
                                    error_log( 'Gallery component exists! ... ' );
                                }
                                if( is_a( $post_comp, 'sp_postVideo') ){
                                    $sp_video_comp = $post_comp;
                                    error_log( 'Video component exists! ... ' );
                                }
                                if( is_a( $post_comp, 'sp_postAttachments') ){
                                    $sp_attachments_comp = $post_comp;
                                    error_log( 'Attachments component exists! ... ' );
                                }
                            }

                            // If there is no content component, then we've got bad news!
                            if( $content_created === false ){
                                // Capture this and report it back to the user
                            }
                        }

                        self::sp_load_attachments( $post_id, $sp_gallery_comp, $sp_video_comp, $sp_attachments_comp, $structure, $inbox, $email_number, $email_author_id );

                        $wp_post = $sp_post->getwpPost();
                        echo 'Post titled: "<a href="' . get_permalink( $wp_post->ID ) . '"" />' . $wp_post->post_title . '</a>" created via e-mail! <br />';
                        echo '<br /><br />';
                    }
                } // end foreach
            }
            imap_close($inbox);
        }
    }
}