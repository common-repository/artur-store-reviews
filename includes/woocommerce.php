<?php

/**
 * Extention for WooCommerce plugin
 */

class ArturStoreReview_Woocommerce {

	const META_STATUS = '_arturstorereview_status';
	const META_DATA = '_arturstorereview_data';

	const WOOCOMMERCE_SUPPORTED_VERSION = '2.4';

	const BULK_LIMIT = 200;

	private static $instance = null;

	public static function getInstance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self;

		return self::$instance;
	}

	private function __construct() {

		if( is_admin() ){
			// settings check hook
			add_action( 'arturstorereview_test_settings', array( $this, 'test_settings' ) );
		}

		// if woocommerce is not active, do not register any more hooks
		if( ! ArturStoreReview::is_plugin_active('woocommerce/woocommerce.php') ){
			return;
		}

		// get settings for artur plugin
		$settings = ArturStoreReview_Settings::getInstance();

		// if artur plugin is not enabled, do not continue with any other hooks / functionality
		if( ! $settings->bool('enabled') ){
			return;
		}

		// check woocommerce version support
		if( ! $this->woocommerce_version_check( self::WOOCOMMERCE_SUPPORTED_VERSION ) ){
			// we do not support older versions
			return;
		}

		// add agree checkbox field to checkout page
		add_action( 'woocommerce_after_order_notes', array( $this, 'add_agree_checkbox_to_checkout_page' ) );

		// when checkout is complete / we have order obj to process
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'woocommerce_checkout_order_processed' ), 10, 1 );

		// when order status changes
        $wc_order_statuses = $settings->get('woocommerce_send_on_statuses');
        foreach ($wc_order_statuses as $wc_hook_status)
        {
            add_action( 'woocommerce_order_status_' . $wc_hook_status, array( $this, 'woocommerce_order_status_changed' ) );
        }
	}

	public function test_settings()
	{
		// get settings for messages
		$settings = ArturStoreReview_Settings::getInstance();

		// check woocommerce installed and activated
		if( ! ArturStoreReview::is_plugin_active('woocommerce/woocommerce.php') ){
			$settings->add_message( 'error', __( 'WooCommerce plugin not activated.', 'arturstorereview' ) );
		}

		// check woocommerce version
		else if( ! $this->woocommerce_version_check( self::WOOCOMMERCE_SUPPORTED_VERSION ) ){
			$settings->add_message( 'error', sprintf( __( 'WooCommerce version not supported. We support WooCommerce v%s and above.', 'arturstorereview' ), self::WOOCOMMERCE_SUPPORTED_VERSION ) );
		}

		// all ok
		else {
			$settings->add_message( 'success', __( 'WooCommerce plugin is activated and supported check ok.', 'arturstorereview' ) );
		}
	}

	public function add_agree_checkbox_to_checkout_page( $checkout ) {

		// settings
		$settings = ArturStoreReview_Settings::getInstance();

		// skip if custom option is selected for agree
		if( 'custom' === $settings->get('woocommerce_opt_in') ){
			return;
		}

		// default value
		$checked_default = ( 'opt_out' === $settings->get('woocommerce_opt_in') ) ? 1 : 0;

		?>
		
		<h3><?php esc_html_e('Participate in review', 'arturstorereview' ); ?></h3>

		<div class="woocommerce-additional-fields__field-wrapper">
		
			<?php

			woocommerce_form_field( 'arturstorereview_wc_agree', array(
				'type'          => 'checkbox',
				'class'         => array('input-checkbox'),
				'label'         => __( 'I want to participate in store review', 'arturstorereview' ),
				'required'  => false,
				),
				$checkout->get_value( 'arturstorereview_wc_agree' ) ? $checkout->get_value( 'arturstorereview_wc_agree' ) : $checked_default
			);

			?>
	 
		</div>
		
		<?php
	}

	public function woocommerce_checkout_order_processed( $order_id ) {
		// settings
		$settings = ArturStoreReview_Settings::getInstance();

		// check opt in options
		if( 'opt_in' === $settings->get('woocommerce_opt_in') || 'opt_out' === $settings->get('woocommerce_opt_in') ){
			if( ! isset( $_POST['arturstorereview_wc_agree'] ) || ! $_POST['arturstorereview_wc_agree'] ){
				// we will not process this order as user did not agree to participate
				return;
			}
		}
		$this->handle_order( $order_id );
	}

	public function woocommerce_order_status_changed( $order_id ) {
		// if order is not marked as "on_hold", skip it
		if( 'on_hold' !== $this->wc_order_artur_status( $order_id ) ){
			return;
		}

		$this->handle_order( $order_id );
	}

	private function handle_order( $order_id ) {
		// skip artur completed statuses
		$artur_completed_statuses = array( 'sent', 'error' );
		$curr_artur_status = $this->wc_order_artur_status( $order_id );
		if( in_array( $curr_artur_status, $artur_completed_statuses ) ){
			return;
		}

		// check order status
		$skip_order_statuses = $this->skip_order_statuses();
		$order_status = get_post_status( $order_id );
		if( in_array( $order_status, $skip_order_statuses ) ){
			$this->mark_wc_order_on_hold( $order_id );	// mark it as ON HOLD si when order status changes, we can detect that
			return;
		}

		$this->process_wc_order( $order_id );
	}

	private function mark_wc_order_on_hold( $order_id ){
		update_post_meta( $order_id, self::META_STATUS, 'on_hold' );
	}

	private function mark_wc_order_sent( $order_id ){
		update_post_meta( $order_id, self::META_STATUS, 'sent' );
	}

	private function mark_wc_order_error( $order_id ){
		update_post_meta( $order_id, self::META_STATUS, 'error' );
	}

	private function wc_order_artur_status( $order_id ){
		return get_post_meta( $order_id, self::META_STATUS, true );
	}

	private function process_wc_order( $order_id ){
		// skip artur completed statuses
		$artur_completed_statuses = array( 'sent', 'error' );
		$curr_artur_status = $this->wc_order_artur_status( $order_id );
		if( in_array( $curr_artur_status, $artur_completed_statuses ) ){
			return;
		}

		// check order status
		$skip_order_statuses = $this->skip_order_statuses();
		$order_status = get_post_status( $order_id );
		if( in_array( $order_status, $skip_order_statuses ) ){
			$this->mark_wc_order_on_hold( $order_id );	// mark it as ON HOLD si when order status changes, we can detect that
			return;
		}

		// get invitation args from order
		$invitation_args = $this->get_invitation_args_from_wc_order( $order_id );
		
		// get artur api instance
		$artur_api = ArturStoreReview_Api::getInstance();

		// send request
		$api_error = $artur_api->create_invitation( $invitation_args );

		// check for api error
		if( $api_error && is_wp_error( $api_error ) ){
			$this->mark_wc_order_error( $order_id );
			// TODO - log error
			return;
		}

		// no errors, mark order artur status sent
		$this->mark_wc_order_sent( $order_id );
	}

	private function skip_order_statuses(){
		return array( 'wc-pending', 'wc-failed', 'wc-on-hold', 'wc-refunded', 'wc-cancelled' );
	}

	private function woocommerce_version_check( $version ) {
		if ( class_exists('WooCommerce') ) {
			global $woocommerce;
			// var_dump('woocommerce_version_check', $woocommerce->version, $version, version_compare( $woocommerce->version, $version, ">=" ) );
			if( version_compare( $woocommerce->version, $version, ">=" ) ) {
				return true;
			}
		}
		return false;
	}

	private function get_invitation_args_from_wc_order( $order_id ){

		// get order obj
		$order_obj = wc_get_order( $order_id );

		$invitation_args = array(
			"customerEmail" => "",
			"purchaseDateUtcMs" => 0,
			"customerName" => "",
			"customerSurname" => "",
			"orderNumber" => "",
			"products" => array(),
		);

		// woocommerce version >= 3.0
		if( $this->woocommerce_version_check('3.0') ){

			// get order data / meta
			$order_data = $order_obj->get_data();

			// fill meta
			$invitation_args['customerEmail'] = $order_data['billing']['email'];
			$invitation_args['purchaseDateUtcMs'] = $order_data['date_created']->getTimestamp();
			$invitation_args['customerName'] = $order_data['billing']['first_name'];
			$invitation_args['customerSurname'] = $order_data['billing']['last_name'];
			$invitation_args['orderNumber'] = $order_data['number'];

			// fill products
			$order_items = $order_obj->get_items();
			foreach ( $order_items as $item_key => $item_obj ) {

				// prep data
				$item_data =  $item_obj->get_data();
				$wc_product = $item_obj->get_product();

				// get image url
				$product_image_url = "";
				$product_image_id = $wc_product->get_image_id();
				if( $product_image_id > 0 ){
					$product_image_src = wp_get_attachment_image_src( $product_image_id, array( 500, 500 ) );
					$product_image_url = $product_image_src[0];
				} else if( function_exists('wc_placeholder_img_src')) {
					// use placeholder as image from woocommerce
					$product_image_url = wc_placeholder_img_src();
				}

				// set description
				$product_description = $wc_product->get_short_description();
				if( empty( $product_description ) ){
					$product_description = $wc_product->get_description();
				}

				// set categories
				$categories = array();
				$category_ids = $wc_product->get_category_ids();
				foreach ( $category_ids as $category_id ) {
					$category_id = (int) $category_id;
					$category = get_term( $category_id, 'product_cat' );
					if ( ! $category || is_wp_error( $category ) )
						continue;
					$categories[] = $category->name;
				}

				$invitation_args['products'][] = array(

					"sourceId" => $item_data['product_id'],
					"imgUrl" => $product_image_url,
					"group" => "produkti",
					"name" => $item_data['name'],
					"description" => $product_description,
					"tags" => $categories,

				);

			}
		}

		// woocommerce version < 3.0
		else {

			// fill meta
			$invitation_args['customerEmail'] = $order_obj->billing_email;
			$invitation_args['purchaseDateUtcMs'] = strtotime( $order_obj->order_date );
			$invitation_args['customerName'] = $order_obj->billing_first_name;
			$invitation_args['customerSurname'] = $order_obj->billing_last_name;
			$invitation_args['orderNumber'] = $order_obj->get_order_number();

			// fill products
			$order_items = $order_obj->get_items();
			foreach ( $order_items as $item_id => $item_data ) {

				// prep data
				$wc_product = wc_get_product( $item_data['product_id'] );

				// get image url
				$product_image_url = "";
				$product_image_id = $wc_product->get_image_id();
				if( $product_image_id > 0 ){
					$product_image_src = wp_get_attachment_image_src( $product_image_id, array( 500, 500 ) );
					$product_image_url = $product_image_src[0];
				} else if( function_exists('wc_placeholder_img_src')) {
					// use placeholder as image from woocommerce
					$product_image_url = wc_placeholder_img_src();
				}

				// set description
				$product_description = get_post_field( 'post_excerpt', (int)$item_data['product_id'] );
				if( empty( $product_description ) ){
					$product_description = get_post_field( 'post_content', (int)$item_data['product_id'] );
				}

				// set categories
				$categories = array();
				$category_ids = wp_get_object_terms( (int)$item_data['product_id'], 'product_cat', array('fields' => 'ids') );
				foreach ( $category_ids as $category_id ) {
					$category_id = (int) $category_id;
					$category = get_term( $category_id, 'product_cat' );
					if ( ! $category || is_wp_error( $category ) )
						continue;
					$categories[] = $category->name;
				}

				$invitation_args['products'][] = array(

					"sourceId" => $item_data['product_id'],
					"imgUrl" => $product_image_url,
					"group" => "produkti",
					"name" => $item_data['name'],
					"description" => $product_description,
					"tags" => $categories,

				);

			}
		}

		return $invitation_args;
	}



}