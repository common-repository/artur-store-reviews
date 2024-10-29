<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

// check dependencies
if( ! class_exists( 'ArturStoreReview_Settings' ) ){
	?>
	<div class="wrap">
		<h2><?php esc_html_e( "Hey you! Are you messing with code ? :)\nIf you need any help, contact us at http://artur.com .", 'arturstorereview' ); ?></h2>
	</div>
	<?php
	return;
}

// settings object instance
$s_obj = ArturStoreReview_Settings::getInstance();

?>
<div class="wrap">
<h1><?php esc_html_e( "Artur Store Review", 'arturstorereview' ); ?></h1>

<form method="post" action="<?php echo esc_attr( admin_url('admin-post.php') ); ?>">
	
	<?php

	foreach( array( 'success', 'warning', 'error' ) as $group ){
		if( $s_obj->has_messages( $group ) ) {
			?>
			<div class="notice notice-<?php echo esc_attr( $group ); ?> is-dismissible">
				<?php
				foreach ( $s_obj->pop_messages( $group ) as $msg ) {
					?>
					<p><?php echo esc_html( $msg ); ?></p>
					<?php
				}
				?>
			</div>
			<?php
		}
	}
	// remove all other messages
	$s_obj->pop_messages();
	?>

	<?php wp_nonce_field( ArturStoreReview_Settings::NONCE_ACTION ); ?>
	<input type="hidden" name="action" value="<?php echo esc_attr( ArturStoreReview_Settings::SAVE_ACTION ); ?>">
	
	<?php
	/******************************************************************
	*
	*       ENABLED
	*
	******************************************************************/
	?>

	<table class="form-table">

		<tr valign="top">
			<th scope="row"><?php esc_html_e( "Enabled", 'arturstorereview' ); ?></th>
			<td>
				<fieldset>
					<label for="<?php echo esc_attr( $s_obj->input_name("enabled") ); ?>">
						<input name="<?php echo esc_attr( $s_obj->input_name("enabled") ); ?>" type="checkbox" id="<?php echo esc_attr( $s_obj->input_name("enabled") ); ?>" value="yes" <?php checked( "yes", $s_obj->get("enabled") ); ?>><?php esc_html_e("(check this box to enable Artur Store Review functionality)", 'arturstorereview' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

	</table>
	<hr>

	<?php
	/******************************************************************
	*
	*       PRODUCTION / LIVE
	*
	******************************************************************/
	?>

	<h2><?php esc_html_e("Production / live", 'arturstorereview' ); ?></h2>
	
	<h4><?php esc_html_e( "Credentials", 'arturstorereview' ); ?></h4>
	<table class="form-table">
		 
		<tr valign="top">
			<th scope="row"><?php esc_html_e( "Username", 'arturstorereview' ); ?></th>
			<td><input type="text" name="<?php echo esc_attr( $s_obj->input_name("credentials_username") ); ?>" value="<?php echo esc_attr( $s_obj->get("credentials_username") ); ?>" class="regular-text code" /></td>
		</tr>
		 
		<tr valign="top">
			<th scope="row"><?php esc_html_e( "Password", 'arturstorereview' ); ?></th>
			<td><input type="password" name="<?php echo esc_attr( $s_obj->input_name("credentials_password") ); ?>" value="<?php echo esc_attr( $s_obj->get("credentials_password") ); ?>" class="regular-text code" /><small><?php esc_html_e( "(Password is saved in plain text for its usage in API requests)", 'arturstorereview' ); ?></small></td>
		</tr>

		<tr valign="top">
			<th scope="row"><?php esc_html_e( "Web store ID", 'arturstorereview' ); ?></th>
			<td><input type="text" name="<?php echo esc_attr( $s_obj->input_name("credentials_web_store_id") ); ?>" value="<?php echo esc_attr( $s_obj->get("credentials_web_store_id") ); ?>" class="regular-text code" /></td>
		</tr>
	</table>

	<h4><?php esc_html_e( "Review", 'arturstorereview' ); ?></h4>
	<table class="form-table">
		 
		<tr valign="top">
			<th scope="row"><?php esc_html_e( "Review type", 'arturstorereview' ); ?></th>
			<td>
				<select name="<?php echo esc_attr( $s_obj->input_name("review") ); ?>">
					<?php
					foreach ( $s_obj->get_review_available_options() as $review_type => $review_type_label ) {
						?>
						<option value="<?php echo esc_attr( $review_type ); ?>" <?php selected( $s_obj->get("review"), $review_type ); ?>><?php echo esc_html( $review_type_label ); ?></option>
						<?php
					}
					?>
				</select>
			</td>
		</tr>
		 
		<tr valign="top">
			<th scope="row"><?php esc_html_e( "Review products mail delay", 'arturstorereview' ); ?></th>
			<td><input type="number" step="1" min="0" name="<?php echo esc_attr( $s_obj->input_name("review_products_mail_delay") ); ?>" value="<?php echo esc_attr( $s_obj->get("review_products_mail_delay") ); ?>" class="regular-text code" /><small><?php esc_html_e( "(in hours)", 'arturstorereview' ); ?></small></td>
		</tr>

		<tr valign="top">
			<th scope="row"><?php esc_html_e( "Review web store mail delay", 'arturstorereview' ); ?></th>
			<td><input type="number" step="1" min="0" name="<?php echo esc_attr( $s_obj->input_name("review_web_store_mail_delay") ); ?>" value="<?php echo esc_attr( $s_obj->get("review_web_store_mail_delay") ); ?>" class="regular-text code" /><small><?php esc_html_e( "(in hours)", 'arturstorereview' ); ?></small></td>
		</tr>
	</table>

	<h4><?php esc_html_e( "Test Artur store reviews", 'arturstorereview' ); ?></h4>
	<table class="form-table">
		 
		<tr valign="top">
			<th scope="row"><?php esc_html_e( "Test email address", 'arturstorereview' ); ?></th>
			<td><input type="text" name="<?php echo esc_attr( $s_obj->input_name("override_email") ); ?>" value="<?php echo esc_attr( $s_obj->get("override_email") ); ?>" class="regular-text code" /><small><?php esc_html_e( "(For testing purposes. If set, all email will be sent to this address and none to your customers.)", 'arturstorereview' ); ?></small></td>
		</tr>
	</table>
	<hr>

	<?php
	/******************************************************************
	*
	*       WOOCOMMERCE
	*
	******************************************************************/
	?>

	<h2><?php esc_html_e("WooCommerce", 'arturstorereview' ); ?></h2>
	
	<h4><?php esc_html_e( "User confirmation", 'arturstorereview' ); ?></h4>
	<p><?php esc_html_e( "By default it is required to get user confirmation for sending them emails.", 'arturstorereview' ); ?></p>
	<table class="form-table">

		<tr valign="top">
			<th scope="row"><?php esc_html_e( "Opt in", 'arturstorereview' ); ?></th>
			<td>
				<select name="<?php echo esc_attr( $s_obj->input_name("woocommerce_opt_in") ); ?>">
					<?php
					foreach ( $s_obj->get_woocommerce_opt_in_options() as $wc_opt_in_key => $label ) {
						?>
						<option value="<?php echo esc_attr( $wc_opt_in_key ); ?>" <?php selected( $s_obj->get("woocommerce_opt_in"), $wc_opt_in_key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php
					}
					?>
				</select>
			</td>
		</tr>

        <tr valign="top">
            <th scope="row"><?php esc_html_e( "Order statuses", 'arturstorereview' ); ?></th>
            <td>
                <p><?php esc_html_e('Send invitation when order has one of the listed statuses.', 'arturstorereviw'); ?></p>
                <select name="<?php echo esc_attr( $s_obj->input_name("woocommerce_send_on_statuses") ); ?>[]" multiple="multiple" class="wc-enhanced-select" style="width: 350px;">
                    <?php
                    foreach ( $s_obj->get_woocommerce_order_statuses() as $wc_order_status => $label ) {
                        ?>
                        <option value="<?php echo esc_attr( str_replace( 'wc-', '', $wc_order_status) ); ?>" <?php echo in_array( str_replace('wc-', '', $wc_order_status), $s_obj->get("woocommerce_send_on_statuses") ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $label ); ?></option>
                        <?php
                    }
                    ?>
                </select>
                <br><small><?php esc_html_e('(Invitation will be sent only first time the order has specified status)', 'arturstorereviw'); ?></small>
            </td>
        </tr>

	</table>
	<hr>
	
	<?php submit_button( __( 'Save and test', 'arturstorereview' ) ); ?>

</form>
</div>