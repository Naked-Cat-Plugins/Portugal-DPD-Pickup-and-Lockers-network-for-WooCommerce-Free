<?php
/**
 * DPD Pickup for WooCommerce - Pro nag
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Add DPD Pickup for WooCommerce nag
 */
function webdados_dpd_pickup_pro_nag() {
	?>
		<script type="text/javascript">
		jQuery(function($) {
			$( document ).on( 'click', '#webdados_dpd_pickup_pro_nag .notice-dismiss', function () {
				// AJAX SET TRANSIENT FOR 60 DAYS
				$.ajax( ajaxurl, {
					type: 'POST',
					data: {
						action: 'dismiss_webdados_dpd_pickup_pro_nag',
					}
				});
			});
		});
		</script>
		<div id="webdados_dpd_pickup_pro_nag" class="notice notice-info is-dismissible">
			<p style="line-height: 1.4em;">
				<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'pro-pickup.png' ); ?>" width="200" height="200" style="float: left; max-width: 65px; height: auto; margin-right: 1em;"/>
				<strong><?php esc_html_e( 'Do you want to deliver to DPD Pickup Points outside Portugal?', 'portugal-chronopost-pickup-woocommerce' ); ?></strong>
			</p>
			<p style="line-height: 1.4em;">
			<?php
				printf(
					/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag. */
					esc_html__( 'You should check out our new plugin: %1$sDPD / SEUR / Geopost Pickup and Lockers network for WooCommerce%2$s', 'portugal-chronopost-pickup-woocommerce' ),
					'<a href="https://nakedcatplugins.com/product/dpd-seur-geopost-pickup-and-lockers-network-for-woocommerce/" target="_blank">',
					'</a>'
				);
			?>
				<br/>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %1$s: opening anchor tag, %2$s: closing anchor tag. */
						__( '%1$sBuy it here%2$s and use the coupon <strong>webdados</strong> for 10%% discount!', 'portugal-chronopost-pickup-woocommerce' ),
						'<a href="https://nakedcatplugins.com/product/dpd-seur-geopost-pickup-and-lockers-network-for-woocommerce/" target="_blank">',
						'</a>'
					)
				);
				?>
			</p>
		</div>
		<?php
}
add_action( 'admin_notices', 'webdados_dpd_pickup_pro_nag' );

/**
 * Dismiss the DPD Pickup for WooCommerce pro plugin nag notice.
 */
function dismiss_webdados_dpd_pickup_pro_nag() {
	$days                 = 120;
	$expiration_timestamp = time() + ( $days * DAY_IN_SECONDS );
	update_user_meta( get_current_user_id(), 'webdados_dpd_pickup_pro_nag_dismissed_until', $expiration_timestamp );
	wp_die();
}
add_action( 'wp_ajax_dismiss_webdados_dpd_pickup_pro_nag', 'dismiss_webdados_dpd_pickup_pro_nag' );