<?php
/**
 * Class for handling Ajax requests
 *
 * @author      feeling4design
 * @category    Admin
 * @package     SUPER_Media_Cleaner/Classes
 * @class       SUPER_MC_Ajax
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if( !class_exists( 'SUPER_MC_Ajax' ) ) :

/**
 * SUPER_MC_Ajax Class
 */
class SUPER_MC_Ajax {
  

    /** 
     *  Define ajax callback functions
     *
     *  @since      1.0.0
     */
    public static function init() {

        $ajax_events = array(
            // Ajax action => nopriv
            'mc_scan' => false, // @since 1.2.6
        );

        foreach ( $ajax_events as $ajax_event => $nopriv ) {
            add_action( 'wp_ajax_super_' . $ajax_event, array( __CLASS__, $ajax_event ) );
            if ( $nopriv ) {
                add_action( 'wp_ajax_nopriv_super_' . $ajax_event, array( __CLASS__, $ajax_event ) );
            }
        }
    }

    public static function getFileCount($path) {
        $size = 0;
        $ignore = array('.','..','cgi-bin','.DS_Store');
        $files = scandir($path);
        foreach($files as $t) {
            if(in_array($t, $ignore)) continue;
            if (is_dir(rtrim($path, '/') . '/' . $t)) {
                $size += self::getFileCount(rtrim($path, '/') . '/' . $t);
            } else {
                $size++;
            }   
        }
        return $size;
    }

    /*
    Step 1 - scan / list all directories
    Step 2 - loop through all the directories one by one, and list all files
    Step 3 - let user choose file extensions they wish to clean up
    Step 4 - scan all unchecked files to see if they are used or not
    Step 5 - remove files to bin
    Step 6 - recover files if something went wrong
    */

    public static function scan_directory( $dir, $structure=array(), $stats=array() ) {
        $delete_transient = false;
        if( ($stats['directories_scanned']==0) && ($stats['files_scanned']==0) ) {
            $delete_transient = true;
        }

        $folders_scanned = 0;
        $files_scanned = 0;
        $total_size = 0;
        $files = scandir($dir);
        $upload_folder = wp_upload_dir();
        foreach( $files as $key => $value ) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if( !is_dir($path) ) {
                $info = pathinfo($path);
                $size = filesize($path);

                $known = false;
                if( array_key_exists( $info['extension'], SUPER_Media_Cleaner()->extensions ) ) {
                    $known = true;
                }else{
                    if (!in_array($info['extension'], $stats['unknown_file_extensions'])){
                        $stats['unknown_file_extensions'][] = $info['extension'];
                        $stats['unknown_files'] = $stats['unknown_files']+1;
                    }
                }

                $found = false;
                $found_in = array();
                // Check if file was found in a post gallery
                $post_id = self::search_in_galleries( $path, $delete_transient );
                if($post_id!=0){
                    $found = true;
                    $found_in[] = 'galleries';
                }

                // Check in theme mods
                $found_in_theme_mods = self::search_in_theme_mods( $path, $delete_transient );
                if($found_in_theme_mods){
                    $found = true;
                    $found_in[] = 'theme_mods';
                }

                // Check if URL was found in post content


                // Check if file is attached to a post
                //self::search_in_attachments();


                /*
                // Check if file was found in post content
                
                Search in post types:
                - nav_menu_item
                - attachment


                $post_id = self::search_in_post($path);
                if($post_id!=0){
                    $found = true;
                }


                $posts = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM $wpdb->posts p
                    WHERE p.post_status != 'inherit'
                    AND p.post_status != 'trash'
                    AND p.post_type != 'attachment'
                    AND p.post_type != 'shop_order'
                    AND p.post_type != 'shop_order_refund'
                    AND p.post_type != 'nav_menu_item'
                    AND p.post_type != 'revision'
                    AND p.post_type != 'auto-draft'
                    AND p.post_type != 'wphb_minify_group'
                    AND p.post_type != 'customize_changeset'
                    AND p.post_type != 'oembed_cache'
                    AND p.post_type NOT LIKE '%acf-%'
                    AND p.post_type NOT LIKE '%edd_%'
                    LIMIT %d, %d", $limit, $limitsize
                    )
                );
                */




                $structure['files'][] = array(
                    'file' => substr($value, 0, 35),
                    'found' => $found,
                    'found_in' => $found_in,
                    'post_id' => $post_id,
                    'post_edit_url' => get_edit_post_link($post_id),
                    'path' => $path,
                    'url' => self::file_url($path, $upload_folder),
                    'extension' => $info['extension'],
                    'known' => $known,
                    'size' => $size 
                );
                $total_size = $total_size + $size;
                if($found){
                    $stats['total_size_saved'] = $stats['total_size_saved'] + $size;
                }
                $stats['total_size'] = $stats['total_size'] + $size;
                $stats['files_scanned'] = $stats['files_scanned'] + 1;
                $files_scanned++;
            }else if($value != '.' && $value != '..') {
                self::scan_directory($path, $structure, $stats);
                $structure['directories'][] = array(
                    'file' => $value,
                    'path' => $path
                );
                $stats['directories_scanned'] = $stats['directories_scanned'] + 1;
                $folders_scanned++;
            }
        }

        
        $response = array(
            'status' => 'scanning', // analysing, scanning, cleaning
            'type' => 'notice', 
            'log' => 'Scanned ' . ($dir==$upload_folder['basedir'] ? '/' : str_replace("\\", "/", str_replace(realpath($upload_folder['basedir']), "", realpath($dir))) ),
            'structure' => $structure,
            'stats' => $stats,
            'folders_scanned' => $folders_scanned,
            'files_scanned' => $files_scanned,
            'total_size_saved' => $total_size_saved,
            'total_size' => $total_size,
            'dir' => realpath($upload_folder['basedir'])
        );
        set_transient( 'super_mc_stats', $stats, 60 * 60 * 2 );
        return $response;
    }


    /** 
     *  Return file URL based on file path
     *  e.g htdocs\dev\wp-content\uploads\2018\03\Example-1-100x100.jpg
     *  to: http://localhost/dev/wp-content/uploads/2018/07/Example-1-100x100.jpg
     *
     *  @since      1.0.0
    */
    public static function file_url( $file, $upload_folder=false ) {
        $file = realpath($file);
        if(!$upload_folder) {
            $upload_folder = wp_upload_dir();
        }
        $basedir = realpath($upload_folder['basedir']);
        $url = str_replace($basedir, $upload_folder['baseurl'], $file);
        $url = str_replace('\\', '/', $url);
        return $url;
    }


    /** 
     *  Find all galleries
     *
     *  @since      1.0.0
    */
    public static function get_post_galleries( $post, $html = true ) {
        if ( ! $post = get_post( $post ) ) {
            return array();
        }
        if ( ! has_shortcode( $post->post_content, 'gallery' ) ) {
            return array();
        }
        $playlists = array();
        if ( preg_match_all( '/' . get_shortcode_regex() . '/s', $post->post_content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $shortcode ) {
                if ( 'gallery' === $shortcode[2] ) {
                    $ids = shortcode_parse_atts( $shortcode[3] );
                    $playlists = array_merge($playlists, explode(",", $ids['ids']));
                }
            }
        }
        return $playlists;
    }

    /** 
     *  Find all playlists
     *
     *  @since      1.0.0
    */
    public static function get_post_playlists( $post, $html = true ) {
        if ( ! $post = get_post( $post ) ) {
            return array();
        }
        if ( ! has_shortcode( $post->post_content, 'playlist' ) ) {
            return array();
        }
        $playlists = array();
        if ( preg_match_all( '/' . get_shortcode_regex() . '/s', $post->post_content, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $shortcode ) {
                if ( 'playlist' === $shortcode[2] ) {
                    $ids = shortcode_parse_atts( $shortcode[3] );
                    $playlists = array_merge($playlists, explode(",", $ids['ids']));
                }
            }
        }
        return $playlists;
    }


    /** 
     *  Find all galleries
     *
     *  @since      1.0.0
    */
    public static function find_galleries( $delete_transient=false ) {
        if( $delete_transient ) {
            delete_transient('super_mc_galleries');
            $gallery_images = null;
        }else{
            $gallery_images = get_transient('super_mc_galleries');
        }
        if($gallery_images) return $gallery_images;

        global $wpdb;
        $gallery_images = array();
        $posts = $wpdb->get_col( "SELECT id FROM $wpdb->posts WHERE post_type != 'attachment' AND post_status != 'inherit'" );
        foreach( $posts as $post ) {
            $galleries = self::get_post_galleries( $post, false );
            foreach( $galleries as $image_id ) {
                array_push( $gallery_images, $image_id );
            }
            $playlists = self::get_post_playlists( $post, false );
            foreach( $playlists as $video_id ) {
                array_push( $gallery_images, $video_id );
            }
        }
        $post_galleries = get_posts( 
            array(
                'tax_query' => array(
                    array(
                      'taxonomy' => 'post_format',
                      'field'    => 'slug',
                      'terms'    => array( 'post-format-gallery' ),
                      'operator' => 'IN'
                    )
                )
            )
        );
        foreach( (array) $post_galleries as $gallery_post ) {
            $images = get_children( 'post_type=attachment&post_mime_type=image&post_parent=' . $gallery_post->ID );
            if ( $images ) {
                foreach( (array) $images as $image_post ) {
                    array_push( $gallery_images, $image_post->guid );
                }
            }
        }
        wp_reset_postdata();
        set_transient( 'super_mc_galleries', $gallery_images, 60 * 60 * 2 );
        return $gallery_images;
    }


    /** 
     *  Search file in galleries
     *
     *  @since      1.0.0
    */
    public static function search_in_galleries( $file, $delete_transient ) {
        global $wpdb;
        $uploads = wp_upload_dir();
        //$guid = 'http://localhost/dev/wp-content/uploads/2018/07/Example-Copy-1.jpg';
        $guid = self::file_url($file);
        $attachment = $wpdb->get_row("SELECT ID, post_parent FROM $wpdb->posts WHERE post_type = 'attachment' AND guid = '$guid'");
        if( $attachment ) {
            $images = self::find_galleries( $delete_transient );
            if( in_array($attachment->ID, $images) ) {
                return absint($attachment->post_parent);
                //$meta = wp_get_attachment_metadata( $file_id );
                //var_dump($meta);
                //return true;
            }
        }
        return 0;

    }


    /** 
     *  Search in theme mods
     *
     *  @since      1.0.0
    */
    public static function search_theme_mods_array( $guid, $theme_mods ) {
        $found_in_array = array_search($guid, $theme_mods);
        if( $found_in_array ) {
            return true;
        }else{
            // Loop through array and search for other values
            foreach( $theme_mods as $k => $v ) {
                if( is_array($v) ) {
                    self::search_theme_mods_array( $guid, $v );
                }
            }
        }
        return false;
    }


    /** 
     *  Search in theme mods
     *
     *  @since      1.0.0
    */
    public static function search_in_theme_mods( $file, $delete_transient=false ) {
        if( $delete_transient ) {
            delete_transient('super_mc_theme_mods');
            $theme_mods = get_theme_mods();
            set_transient( 'super_mc_theme_mods', $theme_mods, 60 * 60 * 2 );
        }else{
            $theme_mods = get_transient('super_mc_theme_mods');
        }
        $guid = self::file_url($file);
        //$guid = 'http://localhost/dev/wp-content/uploads/2018/07/cropped-VOORBEELD.jpg';
        return self::search_theme_mods_array( $guid, $theme_mods );
    }


    /** 
     *  Find all attachments
     *
     *  @since      1.0.0
    */
    public static function find_attachments( $delete=false ) {
        if( $delete ) {
            delete_transient('super_mc_attachments');
            $attachments = null;
        }else{
            $attachments = get_transient('super_mc_attachments');
        }
        if($attachments) return $attachments;
        global $wpdb;
        $attachments = array();
        $posts = $wpdb->get_col( "SELECT id FROM $wpdb->posts WHERE post_type = 'attachment'" );
        foreach( $posts as $post_id ) {
            $source_path = str_replace( '\\', '/', get_post_meta( $post_id, '_wp_attached_file', true ) );
            $prepend_path = '';
            $source_parts = explode( '/', $source_path );
            if ( is_array( $source_parts ) && !empty( $source_parts ) ) {
                array_pop( $source_parts ); $prepend_path = implode( '/', $source_parts ) . '/';
            }
            $backup_sizes = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
            if ( is_array( $backup_sizes ) && !empty( $backup_sizes ) ) {
                foreach( (array) $backup_sizes as $key => $data) {
                    if ( !empty($data['file'] ) ) {
                        $attachments[] = $prepend_path.$data['file'];
                    }
                }
            }
        }
        wp_reset_postdata();
        set_transient( 'super_mc_attachments', $attachments, 60 * 60 * 2 );
    }


    /** 
     *  Search file in attachments
     *
     *  @since      1.0.0
    */
    public static function search_in_attachments( $file ) {
        $attachments = find_attachments();
        if( in_array( $file, $attachments) ) {
            return true;
        }
        return false;
    }  


    /** 
     *  Do not show intro tutorial
     *
     *  @since      1.0.0
    */
    public static function mc_scan() {
        
        $start = $_POST['start'];
        if( $start=='true' ) {
            $upload_folder = wp_upload_dir();
            $dir = $upload_folder['basedir'];
            $stats = array(
                'status' => 'analysing',  // analysing, scanning, cleaning
                'log' => '',
                'type' => 'notice',
                'query_offset' => 0,
                'urls_found' => 0,
                'files_scanned' => 0,
                'directories_scanned' => 0,
                'unknown_files' => 0,
                'unknown_file_extensions' => array(),
                'total_size' => 0,
                'total_size_saved' => 0,
                'total_files' => self::getFileCount($dir),
            );
            set_transient( 'super_mc_stats', $stats, 60 * 60 * 2 );
        }else{
            $dir = sanitize_text_field($_POST['dir']);
            if( $dir=='' ) {
                $upload_folder = wp_upload_dir();
                $dir = $upload_folder['basedir'];
                $stats['total_files'] = self::getFileCount($dir);
            }
            $stats = get_transient('super_mc_stats');
        }

        //For debugging only!
        error_reporting( 1 );
        @ini_set( 'display_errors', 1 );

        if( $stats['status']=='analysing' ) {
            $limit = 50; // Process a total of 10 posts at a time to not stress the database to much
            $query_offset = absint($stats['query_offset']);
            $offset = $limit * $query_offset;

            global $wpdb;
            $posts = $wpdb->get_results("
                SELECT ID, post_content, post_excerpt 
                FROM $wpdb->posts 
                WHERE (post_content != '' OR post_excerpt != '') AND post_status NOT IN ('inherit', 'trash') 
                LIMIT $limit 
                OFFSET $offset"
            );
            if( !$posts ) {

                // Seems we are done here, let's continue and scan directories and files
                $stats['status'] = 'scanning';

            }else{
                // Get URLs from the posts
                $all_urls_found = array();
                foreach( $posts as $k => $v ) {

                    // First parse shortcodes
                    $post_content = do_shortcode($v->post_content);
                    $post_content = wp_make_content_images_responsive($v->post_content);
                    //self::return_urls( $post_content );
                    if( !empty($post_content) ) {

                        // Get all URLs from the post content
                        // [\w,@?^=%&:\/~+#-]{1,}[.][\w.,@?^=%&:\/~+#-]{2,}
                        $regex = '/[\w,@?^=%&:\/~+#-]{1,}[.][\w.,@?^=%&:\/~+#-]{2,}/';
                        $match = preg_match_all($regex, $post_content, $matches, PREG_PATTERN_ORDER, 0);
                        if($match){
                            foreach( $matches as $urls ) {
                                foreach( $urls as $v ) {
                                    // Only add if not exists
                                    if( !in_array( $v, $all_urls_found, true) ) {
                                        array_push( $all_urls_found, $v );
                                        // array_push( $results, $this->wpmc_clean_url( $url ) );
                                    }
                                }
                            }
                        }
                       
                    }
                }

                // Update stats to display how many URLs we have found so far
                $stats['urls_found'] = count($all_urls_found);
                $stats['log'] = 'Analysing posts, found a total of ' . count($all_urls_found) . ' possible media URL\'s';

                    /*
                    // Of course we also need to scan the database for URLs

                    // And of course we also need to scan widgets including the function do_shortcode() and wp_make_content_images_responsive() for the Text Widget which can contain HTML
                    */


                // Save all URLs for later use when scanning
                set_transient( 'super_mc_urls_found', $all_urls_found, 60 * 60 * 2 );

                // Increase offset by 1
                $stats['query_offset'] = $query_offset + 1;
            }

            set_transient( 'super_mc_stats', $stats, 60 * 60 * 2 );

            // Return stats to callback and continue the loop
            echo json_encode($stats);

        }else{

            // We are all set it seems, so let's scan files
            $result = self::scan_directory($dir, array(), $stats);
            echo json_encode($result);

        }
        //sleep(1);
        die();
    }

}
endif;
SUPER_MC_Ajax::init();