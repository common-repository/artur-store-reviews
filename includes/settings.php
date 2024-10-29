<?php

class ArturStoreReview_Settings {

	const SETTINGS = "artur-store-review";
	const MESSAGES = 'artur-store-review-messages';
	const URL_SLUG = "artur-store-review";
	const SAVE_ACTION = "artur_store_review_save_options";
	const NONCE_ACTION = "artur_store_review_save_options";
	const INPUT_PREFIX = "asrinput_";

	private static $instance = null;

	public static function getInstance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self;

		return self::$instance;
	}

	private function __construct() {
		if( !is_admin() || ! current_user_can('manage_options')){
			return;
		}
		
		add_action( 'admin_menu', array( $this, 'add_plugin_settings_page' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save_options' ) );
		
	}

	/*************************************************
			OPTIONS MENU / PAGE - START
	*************************************************/
	
	public function add_plugin_settings_page(){
		add_options_page(
            __('Artur Store Review', 'artur_store_review'), 
            __('Artur Store Review', 'artur_store_review'), 
            'manage_options',
            self::URL_SLUG,
            array( $this, 'settings_page_html' )
        );
	}

	public function settings_page_html(){
	    wp_enqueue_script('wc-enhanced-select');
	    wp_enqueue_style('woocommerce_admin_styles');
		include ARTURSTOREREVIEW_PATH . '/views/settings.php';
	}

	public function save_options(){

		// nonce and user check
		if( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], self::NONCE_ACTION ) || ! current_user_can('manage_options') ){
			return;
		}

		$new_options = $this->all();
		$defaults = $this->defaults();

		$review_available_options = $this->get_review_available_options();
		$review_available_options = array_keys( $review_available_options );

		$woocommerce_opt_in_available_options = $this->get_woocommerce_opt_in_options();
		$woocommerce_opt_in_available_options = array_keys( $woocommerce_opt_in_available_options );

		foreach ($new_options as $param => $val) {
			
			$post_key = $this->input_name( $param );
			$post_val = isset( $_POST[$post_key] ) ? $_POST[$post_key] : '';

			// parse by type in defaults
			$default_value = $defaults[ $param ];

			// parse integers
			if( true === is_numeric( $default_value ) && (int)$default_value == $default_value ){
				$post_val = (int)$post_val;
			}
			// parse boolean / checkbox
			if( 'yes' === $default_value || 'no' === $default_value ){
				$post_val = ( 'yes' === $post_val ) ? 'yes' : 'no';
			}
			// parse text
			if( "" === $default_value ){
				$post_val = sanitize_text_field( $post_val );
			}
			// parse array
            if( is_array($default_value) ){
                $post_val = is_array($post_val) ? $post_val : $default_value;
            }

			// review option check and default set as fallback if needed
			if( in_array( $default_value, $review_available_options, true ) && ! in_array( $post_val, $review_available_options, true ) ){
				$post_val = $default_value;
			}
			// woocommerce opt_in options
			if( in_array( $default_value, $woocommerce_opt_in_available_options, true ) && ! in_array( $post_val, $woocommerce_opt_in_available_options, true ) ){
				$post_val = $default_value;
			}

			$this->set( $param, $post_val, false );
		}

		$this->save();

		$this->add_message( 'success', __( 'Settings saved and tested.' ) );

		$this->test_settings();

		wp_redirect( add_query_arg('page', self::URL_SLUG, admin_url('options-general.php')) );
		exit();
	}
	
	/*************************************************
			OPTIONS MENU / PAGE - END
	*************************************************/

	private function test_settings(){

		// get API instance
		$artur_api_obj = ArturStoreReview_Api::getInstance();

		// ping production
		$production_ping_error = $artur_api_obj->ping( false );
		if( is_wp_error( $production_ping_error ) ){
			$this->add_message( 'error', sprintf( __( 'Production server call error. Please check your credential infromation. Message: "%s"', 'arturstorereview'), $production_ping_error->get_error_message() ) );
		} else {
			$this->add_message( 'success', __( 'Server check ok.', 'arturstorereview') );
		}

		// check production mail override
		$override_email = $this->get('override_email');
		if( ! empty( $override_email ) && ! is_email( $override_email ) ){
			$this->add_message( 'error', __( 'Testing is enabled but provided email is not valid. Please set valid email in "Test email" and save settings.', 'arturstorereview' ) );
		}

		// allow others to do their checks here
		do_action('arturstorereview_test_settings');

		// if no errors, add success message
		if( ! $this->has_messages( 'error' ) && ! $this->has_messages( 'warning' ) ){
			$this->add_message( 'success', __( 'All tests ok.' ) );
		}
	}

	private function defaults(){
		return array(
			/*******************  plugin  *******************/

			// is artur store review active or not
			'enabled' => 'yes',

			/*******************  production  *******************/
			
			// API credentials
			'credentials_username' => "",
			'credentials_password' => "",
			'credentials_web_store_id' => "",

			// override email recipient
			'override_email' => "",

			// review
			'review' => "web_store_and_products",	// "web_store_and_products", "web_store_only", "products_only"
													// $this->get_review_available_options()
			'review_products_mail_delay' => 0,	// in hours
			'review_web_store_mail_delay' => 0,	// in hours

			// TODO - when plugin supports extending, add this to woocommerce file
			/*******************  woocommerce  *******************/
			'woocommerce_opt_in' => 'opt_in',	// $this->get_woocommerce_opt_in_options();
            'woocommerce_send_on_statuses' => ['completed'],
		);
	}

	private $settings_cache = null;
	public function all(){
		if( ! is_null( $this->settings_cache ) ){
			return $this->settings_cache;
		}
		
		$settings_val = get_option( self::SETTINGS, false );

		$settings = shortcode_atts( $this->defaults(), $settings_val );

		$this->settings_cache = $settings;

		return $this->settings_cache;
	}

	/**
	 * return avaiable options for "review" and "sandbox_review" setting parameter
	 * @return array list of $type => $translated_label strings representing review setting parameter options
	 */
	public function get_review_available_options() {
		return array(
			'web_store_and_products' => __( "Web store and products", "arturstorereview" ),
			'web_store_only' => __( "Web store only", "arturstorereview" ),
			'products_only' => __( "Products only", "arturstorereview" ),
		);
	}

	public function get_woocommerce_order_statuses()
    {
        if( function_exists('wc_get_order_statuses') )
            return  wc_get_order_statuses();

        return [
            'wc-completed' => __('Completed', 'arturstorereview'),
        ];
    }

	/**
	 * return avaiable options for "woocommerce_opt_in" setting parameter
	 * @return array list of $key => $label strings representing woocommerce_opt_in setting parameter options
	 */
	public function get_woocommerce_opt_in_options() {
		return array(
			'opt_in' => __( "User must agree to recieve invitation for review (default)", "arturstorereview" ),
			'opt_out' => __( "User agrees to recieve invitation for review by default but can opt out.", "arturstorereview" ),
			'custom' => __( "Hide user confirmation box. (I agree to follow all legal responsibilities in other ways.)", "arturstorereview" ),
		);
	}

	public function get($param){
		$all = $this->all();
		if( isset( $all[$param] ) ){
			return $all[$param];
		}
		return null;
	}

	public function set( $param, $value, $save_options = true ){
		
		// fill options cache		
		$this->all();

		// if param exists in options cache, set its value
		if( isset( $this->settings_cache[$param] ) ){
			$this->settings_cache[$param] = $value;
		}

		// save options
		if( $save_options ){
			$this->save();
		}
	}

	private function save(){
		$settings = $this->all();

		update_option( self::SETTINGS, $settings );
		$this->settings_cache = null;
	}

	/******************************************************************
	*
	*       MESSAGES
	*
	******************************************************************/
	
	public function add_message( $group, $msg )
	{
		$messages = get_option( self::MESSAGES, false );

		if( ! is_array( $messages ) ){
			$messages = array();
		}

		if( ! isset( $messages[ $group ] ) || ! is_array( $messages[ $group ] ) ){
			$messages[ $group ] = array();
		}

		$messages[ $group ][] = $msg;

		update_option( self::MESSAGES, $messages );
	}

	public function pop_messages( $group = null ){

		$pop_mesages = array();

		$messages = get_option( self::MESSAGES, false );

		if( ! is_array( $messages ) ){
			$messages = array();
		}

		// pop all messages
		if( is_null( $group ) ){
			$pop_mesages = $messages;
			$messages = array();
		}
		// pop specific group if exists
		else if( isset( $messages[ $group ] ) && is_array( $messages[ $group ] ) ){

			$pop_mesages = $messages[ $group ];
			unset( $messages[ $group ] );
		}

		// save the rest of the messages
		update_option( self::MESSAGES, $messages );

		// return messages
		return $pop_mesages;
	}

	public function has_messages( $group = null )
	{
		$messages = get_option( self::MESSAGES, false );

		if( ! is_array( $messages ) ){
			$messages = array();
		}

		// pop all messages
		if( is_null( $group ) ){
			foreach ( $messages as $msg_group => $group_messages ) {
				if( is_array( $group_messages ) && count( $group_messages ) > 0 ){
					return true;
				}
			}
		}
		// pop specific group if exists
		else if( isset( $messages[ $group ] ) && is_array( $messages[ $group ] ) ){
			if( count( $messages[ $group ] ) > 0 ){
				return true;
			}
		}

		return false;
	}

	/******************************************************************
	*
	*       VIEW HELPERS
	*
	******************************************************************/
	
	public function input_name( $for ) {
		return self::INPUT_PREFIX . str_replace( ' ', '_', $for );
	}

	/**
	 * returns boolean value for parameter
	 * @param  string $param parameter key
	 * @return boolean        if parameter is boolean type and marked as true
	 */
	public function bool($param) {
		$param_val = $this->get( $param );
		return ( 'yes' === $param_val );
	}

}