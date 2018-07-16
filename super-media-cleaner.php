<?php
/**
 * Super - Media Cleaner 23
 *
 * @package   Super - Media Cleaner
 * @author    feeling4design
 * @link      http://codecanyon.net/user/feeling4design
 * @copyright 2018 by feeling4design
 *
 * @wordpress-plugin
 * Plugin Name: Super - Media Cleaner
 * Plugin URI:  http://codecanyon.net/user/feeling4design
 * Description: Clean unused media files and save up space on your server and in your database
 * Version:     1.0.0
 * Author:      feeling4design
 * Author URI:  http://codecanyon.net/user/feeling4design
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if(!class_exists('SUPER_Media_Cleaner')) :


    /**
     * Main SUPER_Media_Cleaner Class
     *
     * @class SUPER_Media_Cleaner
     * @version	1.0.0
     */
    final class SUPER_Media_Cleaner {
    
        
        /**
         * @var string
         *
         *	@since		1.0.0
        */
        public static $version = '1.0.0';
        

        /**
         * @var SUPER_Media_Cleaner The single instance of the class
         *
         *	@since		1.0.0
        */
        protected static $_instance = null;

        
        /**
         * Contains an array of registered script handles
         *
         * @var array
         *
         *	@since		1.0.0
        */
        private static $scripts = array();
        
        
        /**
         * Contains an array of localized script handles
         *
         * @var array
         *
         *	@since		1.0.0
        */
        private static $wp_localize_scripts = array();
        
        
        /**
         * Main SUPER_Media_Cleaner Instance
         *
         * Ensures only one instance of SUPER_Media_Cleaner is loaded or can be loaded.
         *
         * @static
         * @see SUPER_Media_Cleaner()
         * @return SUPER_Media_Cleaner - Main instance
         *
         *	@since		1.0.0
        */
        public static function instance() {
            if(is_null( self::$_instance)){
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        

        /**
         * SUPER_Media_Cleaner Constructor.
         *
         *  @since      1.0.0
        */
        public function __construct(){
            $this->includes();
            $this->init_hooks();
            do_action('super_media_cleaner_loaded');
        }


        /**
         * Define constant if not already set
         *
         * @param  string $name
         * @param  string|bool $value
         *
         *	@since		1.0.0
        */
        private function define($name, $value){
            if(!defined($name)){
                define($name, $value);
            }
        }

        
        /**
         * What type of request is this?
         *
         * string $type ajax, frontend or admin
         * @return bool
         *
         *	@since		1.0.0
        */
        private function is_request($type){
            switch ($type){
                case 'admin' :
                    return is_admin();
                case 'ajax' :
                    return defined( 'DOING_AJAX' );
                case 'cron' :
                    return defined( 'DOING_CRON' );
                case 'frontend' :
                    return (!is_admin() || defined('DOING_AJAX')) && ! defined('DOING_CRON');
            }
        }

        
        /**
         * Include required core files used in admin and on the frontend.
         *
         *  @since      1.0.0
        */
        public function includes(){

            if ( $this->is_request( 'admin' ) ) {
                include_once( 'includes/class-menu.php' );
                include_once( 'includes/class-pages.php' );
            }

            if ( $this->is_request( 'ajax' ) ) {
                include_once( 'includes/class-ajax.php' );
            }

            if ( $this->is_request( 'frontend' ) ) {

            }

        }


        /**
         * Hook into actions and filters
         *
         *	@since		1.0.0
        */
        private function init_hooks() {
            
            // @since 1.3.0
            register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
            
            if ( $this->is_request( 'frontend' ) ) {
                
            }
            
            if ( $this->is_request( 'admin' ) ) {
                
                add_action( 'admin_menu', 'SUPER_MC_Menu::register_menu' );
        
                // Filters since 1.0.0
                add_filter( 'admin_enqueue_styles', array( $this, 'enqueue_styles' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
                
            }
            
        }


        /**  
         *  Deactivate
         *
         *  Upon plugin deactivation delete activation
         *
         *  @since      1.3.0
         */
        public static function deactivate(){
            
        }


        /**
         * Enqueue scripts for each admin page
         * 
         * @since       1.0.0
        */
        public function enqueue_scripts() {
            
            if ( function_exists( 'get_current_screen' ) ) {
                $current_screen = get_current_screen();
            }else{
                $current_screen = new stdClass();
                $current_screen->id = '';
            }

            // Enqueue Javascripts
            if( $enqueue_scripts = self::get_scripts() ) {
                foreach( $enqueue_scripts as $handle => $args ) {
                    if ( ( in_array( $current_screen->id, $args['screen'] ) ) || ( $args['screen'][0]=='all' ) ) {
                        if($args['method']=='register'){
                            self::$scripts[] = $handle;
                            wp_register_script( $handle, $args['src'], $args['deps'], $args['version'], $args['footer'] );
                        }else{
                            wp_enqueue_script( $handle, $args['src'], $args['deps'], $args['version'], $args['footer'] );
                        }
                    }
                }
            }
            
            // Enqueue Styles
            if( $enqueue_styles = self::get_styles() ) {
                foreach( $enqueue_styles as $handle => $args ) {
                    if ( ( in_array( $current_screen->id, $args['screen'] ) ) || ( $args['screen'][0]=='all' ) ) {
                        if($args['method']=='register'){
                            wp_register_style( $handle, $args['src'], $args['deps'], $args['version'], $args['media'] );
                        }else{
                            wp_enqueue_style( $handle, $args['src'], $args['deps'], $args['version'], $args['media'] );
                        }
                    }
                }
            }
            
        }
        
        
        /**
         * Get styles for the backend
         *
         * @access private
         * @return array
         * [$handle, $src, $deps, $ver, $media]
         *
         * @since       1.0.0
        */
        public static function get_styles() {
            $assets_path = str_replace( array( 'http:', 'https:' ), '', plugin_dir_url( __FILE__ ) ) . 'assets/';
            return array(
                'super-media-cleaner' => array(
                    'src'     => $assets_path . 'css/media-cleaner.min.css',
                    'deps'    => array(),
                    'version' => self::$version,
                    'media'   => 'all',
                    'screen'  => array(
                        'toplevel_page_super_media_cleaner_scan',
                    ),
                    'method'  => 'enqueue',
                ),
                'super-font-awesome' => array(
                    'src'     => $assets_path . 'css/fonts/css/fontawesome.css',
                    'deps'    => '',
                    'version' => self::$version,
                    'media'   => 'all',
                    'screen'  => array(
                        'toplevel_page_super_media_cleaner_scan',
                    ),
                    'method'  => 'enqueue',
                ),
                'super-font-awesome-solid' => array(
                    'src'     => $assets_path . 'css/fonts/css/solid.css',
                    'deps'    => '',
                    'version' => self::$version,
                    'media'   => 'all',
                    'screen'  => array(
                        'toplevel_page_super_media_cleaner_scan',
                    ),
                    'method'  => 'enqueue',
                ),


            );
        }
        
        
        /**
         * Get scripts for the backend
         *
         * @access private
         * @return array
         * [$handle, $src, $deps, $ver, $in_footer]
         *
         * @since       1.0.0
        */
        public static function get_scripts() {
            $assets_path = str_replace( array( 'http:', 'https:' ), '', plugin_dir_url( __FILE__ ) ) . 'assets/';
            return array(
                'super-media-cleaner' => array(
                    'src' => $assets_path . 'js/media-cleaner.min.js',
                    'deps' => array( 'jquery' ),
                    'version' => self::$version,
                    'footer' => false,
                    'screen' => array(
                        'toplevel_page_super_media_cleaner_scan'
                    ),
                    'method'  => 'enqueue',
                ),
            );
        }
        
        
        /**
         * Localize a script once.
         *
         * @access private
         * @param  string $handle
         *
         * @since       1.0.0
        */
        private static function localize_script( $handle ) {
            if ( ! in_array( $handle, self::$wp_localize_scripts ) && wp_script_is( $handle, 'registered' ) && ( $data = self::get_script_data( $handle ) ) ) {
                $name = str_replace( '-', '_', $handle ) . '_i18n';
                self::$wp_localize_scripts[] = $handle;
                wp_localize_script( $handle, $name, apply_filters( $name, $data ) );
                wp_enqueue_script( $handle );
            }        
        }
        
        
        /**
         * Localize scripts only when enqueued
         *
         * @access private
         * @param  string $handle
         *
         * @since       1.0.0
        */
        public static function localize_printed_scripts() {
            foreach ( self::$scripts as $handle ) {
                self::localize_script( $handle );
            }
        }
        
        
        /**
         * Return data for script handles.
         * @access private
         * @param  string $handle
         * @return array|bool
        */
        private static function get_script_data( $handle ) {
            $scripts = self::get_scripts();
            if( isset( $scripts[$handle]['localize'] ) ) {
                return $scripts[$handle]['localize'];
            }
            return false;
        }


        public $extensions = array(

            // Image file formats by file extension
            'ai' => 'Adobe Illustrator file',
            'bmp' => 'Bitmap image',
            'gif' => 'GIF image',
            'ico' => 'Icon file',
            'jpeg' => 'JPEG image',
            'jpg' => 'JPEG image',
            'png' => 'PNG image',
            'ps' => 'PostScript file',
            'psd' => 'PSD image',
            'svg' => 'Scalable Vector Graphics file',
            'tif' => 'TIFF image',
            'tiff' => 'TIFF image',

            // Video file formats by file extension
            '3g2' => '3GPP2 multimedia file',
            '3gp' => '3GPP multimedia file',
            'avi' => 'AVI file',
            'flv' => 'Adobe Flash file',
            'h264' => 'H.264 video file',
            'm4v' => 'Apple MP4 video file',
            'mkv' => 'Matroska Multimedia Container',
            'mov' => 'Apple QuickTime movie file',
            'mp4' => 'MPEG4 video file',
            'mpg' => 'MPEG video file',
            'mpeg' => 'MPEG video file',
            'rm' => 'RealMedia file',
            'swf' => 'Shockwave flash file',
            'vob' => 'DVD Video Object',
            'wmv' => 'Windows Media Video file',
            'ogv' => 'Ogg',

            // Audio file formats by file extensions
            'mp3' => 'MP3 audio file',
            'mp4a' => 'MP4a audio file',
            'ogg' => 'Ogg Vorbis audio file',
            'wav' => 'WAV file',
            'aif' => 'AIF audio file',
            'cda' => 'CD audio track file',
            'mid' => 'MIDI audio file.',
            'midi' => 'MIDI audio file.',
            'mpa' => 'MPEG-2 audio file',
            'wma' => 'WMA audio file',
            'wpl' => 'Windows Media Player playlist',

            // Compressed file extensions
            '7z' => '7-Zip compressed file',
            'arj' => 'ARJ compressed file',
            'deb' => 'Debian software package file',
            'pkg' => 'Package file',
            'rar' => 'RAR file',
            'rpm' => 'Red Hat Package Manager',
            'tar.gz' => 'Tarball compressed file',
            'z' => 'Z compressed file',
            'zip' => 'Zip compressed file',

            // Disc and media file extensions
            'bin' => 'Binary disc image',
            'dmg' => 'macOS X disk image',
            'iso' => 'ISO disc image',
            'toast' => 'Toast disc image',
            'vcd' => 'Virtual CD',

            // Data and database file extensions
            'csv' => 'Comma separated value file',
            'dat' => 'Data file',
            'db' => 'Database file',
            'dbf' => 'Database file',
            'log' => 'Log file',
            'mdb' => 'Microsoft Access database file',
            'sav' => 'Save file (e.g., game save file)',
            'sql' => 'SQL database file',
            'tar' => 'Linux / Unix tarball file archive',
            'xml' => 'XML file',
            'mmdb' => 'MaxMind DB File Format maps IPv4 and IPv6 addresses to data records using an efficient binary search tree',
            'json' => 'A JSON file is a file that stores simple data structures and objects',       

            // Executable file extensions
            'apk' => 'Android package file',
            'bat' => 'Batch file',
            'bin' => 'Binary file',
            'cgi' => 'Perl script file',
            'pl' => 'Perl script file',
            'com' => 'MS-DOS command file',
            'exe' => 'Executable file',
            'gadget' => 'Windows gadget',
            'jar' => 'Java Archive file',
            'py' => 'Python file',
            'wsf' => 'Windows Script File',

            // Font file extensions
            'eot' => 'Embedded OpenType (EOT) fonts are a compact form of OpenType fonts designed by Microsoft for use as embedded fonts on web pages',
            'woff' => 'The Web Open Font Format (WOFF) is a font format for use in web pages.',
            'fnt' => 'Windows font file',
            'fon' => 'Generic font file',
            'otf' => 'Open type font file',
            'ttf' => 'TrueType font file',

            // Internet related file extensions
            'htaccess' => 'Htaccess file',
            'asp' => 'Active Server Page file',
            'aspx' => 'Active Server Page file',
            'cer' => 'Internet security certificate',
            'cfm' => 'ColdFusion Markup file',
            'cgi' => 'Perl script file',
            'pl' => 'Perl script file',
            'css' => 'Cascading Style Sheet file',
            'htm' => 'HTML file',
            'html' => 'HTML file',
            'js' => 'JavaScript file',
            'jsp' => 'Java Server Page file',
            'part' => 'Partially downloaded file',
            'php' => 'PHP file',
            'py' => 'Python file',
            'rss' => 'RSS file',
            'xhtml' => 'XHTML file',

            // Presentation file formats by file extension
            'key' => 'Keynote presentation',
            'odp' => 'OpenOffice Impress presentation file',
            'pps' => 'PowerPoint slide show',
            'ppt' => 'PowerPoint presentation',
            'pptx' => 'PowerPoint Open XML presentation',

            // Programming files by file extensions
            'c' => 'C and C++ source code file',
            'class' => 'Java class file',
            'cpp' => 'C++ source code file',
            'cs' => 'Visual C# source code file',
            'h' => 'C, C++, and Objective-C header file',
            'java' => 'Java Source code file',
            'sh' => 'Bash shell script',
            'swift' => 'Swift source code file',
            'vb' => 'Visual Basic file',

            // Spreadsheet file formats by file extension
            'ods' => 'OpenOffice Calc spreadsheet file',
            'xlr' => 'Microsoft Works spreadsheet file',
            'xls' => 'Microsoft Excel file',
            'xlsx' => 'Microsoft Excel Open XML spreadsheet file',

            // System related file formats and file extensions
            'bak' => 'Backup file',
            'cab' => 'Windows Cabinet file',
            'cfg' => 'Configuration file',
            'cpl' => 'Windows Control panel file',
            'cur' => 'Windows cursor file',
            'dll' => 'DLL file',
            'dmp' => 'Dump file',
            'drv' => 'Device driver file',
            'icns' => 'macOS X icon resource file',
            'ico' => 'Icon file',
            'ini' => 'Initialization file',
            'lnk' => 'Windows shortcut file',
            'msi' => 'Windows installer package',
            'sys' => 'Windows system file',
            'tmp' => 'Temporary file',

            // Word processor and text file formats by file extension
            'doc' => 'Microsoft Word file',
            'docx' => 'Microsoft Word file',
            'odt' => 'OpenOffice Writer document file',
            'pdf' => 'PDF file',
            'rtf' => 'Rich Text Format',
            'tex' => 'A LaTeX document file',
            'txt' => 'Plain text file',
            'wks' => 'Microsoft Works file',
            'wps' => 'Microsoft Works file',
            'wpd' => 'WordPerfect document'
        );

    }
endif;


/**
 * Returns the main instance of SUPER_Media_Cleaner to prevent the need to use globals.
 *
 * @return SUPER_Media_Cleaner
 */
function SUPER_Media_Cleaner() {
    return SUPER_Media_Cleaner::instance();
}


// Global for backwards compatibility.
$GLOBALS['SUPER_Media_Cleaner'] = SUPER_Media_Cleaner();