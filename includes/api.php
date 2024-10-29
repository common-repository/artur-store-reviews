<?php

/**
 * Artur API wrapper
 * specification available at: https://kaldi-it.atlassian.net/wiki/spaces/VSPUBLIC/pages/33631405/Artur+-+API
 */

class ArturStoreReview_Api {

	const PRODUCTION_URL = 'https://www.artur.com/';
	const STAGING_URL = 'https://merlin.kaldi.si/';

	const ENDPOINT_PING = 'merlin/api/profiles/{profileId}/ping';
	const ENDPOINT_INVITATIONS = 'merlin/api/invitations';

	private static $instance = null;

	public static function getInstance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self;

		return self::$instance;
	}

	private function __construct() {

	}

	/**
	 * sends request to API
	 * @return WP_Error | false error if any and false on success
	 */
	public function create_invitation( $args )
	{
		// settings
		$settings = ArturStoreReview_Settings::getInstance();

		// get credentials
		$credentials = $this->credentials();

		// prep request url
		$request_url = rtrim( $credentials['api_url'], '/' ) . '/' . ltrim( self::ENDPOINT_INVITATIONS, '/' );

		// prep body args
		$body_args = $args;

		// set profile id
		$body_args['profileId'] = $credentials['web_store_id'];

		// we do not test by default
		$body_args['test'] = false;

		// enable test mail id needed
		if( false !== $credentials['test_mail'] ){
			$body_args['customerEmail'] = $credentials['test_mail'];
			$body_args['test'] = true;
		}
		
		// set mail intervals
		$body_args['sendAfterHours'] = $credentials['review_web_store_mail_delay'];
		$body_args['sendProductReviewAfterHours'] = $credentials['review_products_mail_delay'];

		// set review type
		switch ( $credentials['review_type'] ) {
			case 'web_store_only':	// review set to TRUE and leave empty array for products
				$body_args['reviewProfile'] = true;
				$body_args['products'] = array();
				break;

			case 'products_only':		// review set to FALSE and keep products array
				$body_args['reviewProfile'] = false;
				break;
			
			case 'web_store_and_products': 	// review set to TRUE and keep products array
			default:
				$body_args['reviewProfile'] = true;
				break;
		}

		// request args
		$request_args = array(
			'timeout' => $credentials['request_timeout'],
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $credentials['username'] . ':' . $credentials['password'] ),
				'Content-Type' => 'application/json',
			),
			'body' => json_encode( $body_args ),
		);

		// send request
		$request_result = wp_remote_post( $request_url, $request_args );

		// echo "<pre>";
		// var_dump( $request_args );
		// echo "</pre>";
		// echo "<pre>";
		// var_dump( $request_result );
		// echo "</pre>";
		// die();

		// check reponse code - success
		if( 200 === $request_result['response']['code'] ){
			// no errors, return false for error
			return false;
		}

		// check user credentials not ok
		if( 403 === $request_result['response']['code'] ){
			return new WP_Error( 'unauthorized', __( 'Unauthorized call. Please check your Artur.com settings and try again.', 'arturstorereview' ) );
		}

		// server not found or profileId not found
		if( 404 === $request_result['response']['code'] ){
			return new WP_Error( 'coompany_not_found', __( 'Requested field "profileId" id not found.', 'arturstorereview' ) );
		}

		// other unknown errors
		return new WP_Error( 'unknown', __( 'Uknown error occured while connecting to Artur.com server.', 'arturstorereview' ) );
	}

	/**
	 * checks api connection
	 * @return WP_Error|false error if any and false on success
	 */
	public function ping( $use_sandbox = false )
	{
		// get credentials
		$credentials = $this->credentials( $use_sandbox );

		// prep request url
		$request_url = rtrim( $credentials['api_url'], '/' ) . '/' . ltrim( self::ENDPOINT_PING, '/' );

		// replace profile id in url
		$request_url = str_replace( '{profileId}', urlencode( $credentials['web_store_id'] ), $request_url );

		// var_dump( $credentials['username'], $credentials['password'], base64_encode( $credentials['username'] . ':' . $credentials['password'] ) );
		// die();

		// request args
		$request_args = array(
			'timeout' => $credentials['request_timeout'],
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $credentials['username'] . ':' . $credentials['password'] ),
			),
		);

		// send request
		$request_result = wp_remote_get( $request_url, $request_args );

		// check reponse code - success
		if( 200 === $request_result['response']['code'] ){
			// no errors, return false for error
			return false;
		}

		// check user has been removed from Artur system
		if( 401 === $request_result['response']['code'] ){
			return new WP_Error( 'user_removed_from_arthur', __( 'User was removed from Artur system. Please contact Artur.com for more information.', 'arturstorereview' ) );
		}

		// check user credentials not ok
		if( 403 === $request_result['response']['code'] ){
			return new WP_Error( 'authorization_not_ok', __( 'Provided username/password combination is wrong or user is authenticated, but not authorized for specified field "profileId"', 'arturstorereview' ) );
		}

		// server not found
		if( 404 === $request_result['response']['code'] ){
			return new WP_Error( 'server_not_found', __( 'Server not found or connection to server refused. Please try again later or contact us at Artur.com .', 'arturstorereview' ) );
		}

		// other unknown errors
		return new WP_Error( 'unknown', __( 'Uknown error occured while connecting to Artur.com server.', 'arturstorereview' ) );
	}

	private function credentials( $use_sandbox = false ){
		$settings = ArturStoreReview_Settings::getInstance();

		$test_mail = $settings->get('override_email');
		if( empty( $test_mail ) || ! is_email( $test_mail ) ){
			$test_mail = false;
		}

		return array(
			'api_url' => $use_sandbox ? self::STAGING_URL : self::PRODUCTION_URL,
			'username' => $use_sandbox ? $settings->get('sandbox_credentials_username') : $settings->get('credentials_username'),
			'password' => $use_sandbox ? $settings->get('sandbox_credentials_password') : $settings->get('credentials_password'),
			'web_store_id' => $use_sandbox ? $settings->get('sandbox_credentials_web_store_id') : $settings->get('credentials_web_store_id'),
			
			'test_mail' => $test_mail,

			'review_type' => $use_sandbox ? $settings->get('sandbox_review') : $settings->get('review'),
			'review_products_mail_delay' => $use_sandbox ? $settings->get('sandbox_review_products_mail_delay') : $settings->get('review_products_mail_delay'),
			'review_web_store_mail_delay' => $use_sandbox ? $settings->get('sandbox_review_web_store_mail_delay') : $settings->get('review_web_store_mail_delay'),
			
			'request_timeout' => 10,	// 10 seconds

		);
	}

}