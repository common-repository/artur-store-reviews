<?php
/**
* Plugin Name: Artur Store Reviews
* Description: External service artur.com
* Plugin URI: http://wordpress.org/plugins/artur-store-reviews
* Author: artur.com
* Author URI: http://artur.com
* Version: 1.2.1
* License: GPL2
* Text Domain: arturstorereview
* Domain Path: languages
*/

/*
Copyright (C) 2017  artur.com

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('ARTURSTOREREVIEW_VERSION', '1.2.1');
define('ARTURSTOREREVIEW_FILE', __FILE__);
define('ARTURSTOREREVIEW_PATH', dirname(ARTURSTOREREVIEW_FILE));

require_once(ARTURSTOREREVIEW_PATH . '/includes/settings.php');
require_once(ARTURSTOREREVIEW_PATH . '/includes/api.php');
require_once(ARTURSTOREREVIEW_PATH . '/includes/woocommerce.php');

add_action( 'plugins_loaded', array( 'ArturStoreReview', 'getInstance' ) );
// register_activation_hook( __FILE__, array( 'ArturStoreReview', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ArturStoreReview', 'deactivate' ) );

class ArturStoreReview {

	private static $instance = null;

	public static function getInstance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self;

		return self::$instance;
	}

	private function __construct() {

		// load translations
		$this->load_textdomain();

		// init settings
		ArturStoreReview_Settings::getInstance();

		// add woocommerce support
		ArturStoreReview_Woocommerce::getInstance();

	}

	public function load_textdomain(){
		load_plugin_textdomain( 'arturstorereview', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/*************************************************
			UTILS - START
	*************************************************/

	public static function is_plugin_active($plugin_name){
		if(!function_exists('is_plugin_active')){
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		return is_plugin_active($plugin_name);
	}
	
	/*************************************************
			UTILS - END
	*************************************************/


	// public static function activate() {}

	public static function deactivate() {

		delete_option( ArturStoreReview_Settings::SETTINGS );
        delete_option( ArturStoreReview_Settings::MESSAGES );

	}

}
