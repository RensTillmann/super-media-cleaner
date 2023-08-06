<?php
/**
 * Installation related functions and actions.
 *
 * @author      feeling4design
 * @category    Admin
 * @package     SUPER_Media_Cleaner/Classes
 * @class       SUPER_MC_Menu
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if( !class_exists( 'SUPER_MC_Menu' ) ) :

/**
 * SUPER_MC_Menu Class
 */
class SUPER_MC_Menu {
    
    /** 
	 *	Add menu items
	 *
	 *	@since		1.0.0
	*/
    public static function register_menu(){
        global $menu, $submenu;
        add_menu_page(
            'Super Media Cleaner',
            'Super Media Cleaner',
            'manage_options',
            'super_media_cleaner_scan',
            'SUPER_MC_Pages::scan_page'
        );
    }
}
endif;