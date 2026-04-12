<?php
/**
 * Plugin Name:          Portugal DPD Pickup and Lockers network for WooCommerce
 * Plugin URI:           https://www.webdados.pt/wordpress/plugins/rede-chronopost-pickup-portugal-woocommerce-wordpress/
 * Description:          Lets you deliver on the DPD Portugal Pickup network of partners or Lockers.
 * Version:              3.8
 * Author:               Naked Cat Plugins (by Webdados)
 * Author URI:           https://nakedcatplugins.com
 * Text Domain:          portugal-chronopost-pickup-woocommerce
 * Requires at least:    5.8
 * Tested up to:         7.0
 * Requires PHP:         7.2
 * WC requires at least: 7.1
 * WC tested up to:      10.7
 * Requires Plugins:     woocommerce
 */

/* WooCommerce CRUD ready */

/**
 * Initialize the plugin functionality.
 *
 * Sets up hooks, actions, and filters for the DPD Pickup network integration.
 * Only initializes when WooCommerce 7.1 or higher is active.
 *
 * This function handles:
 * - Cron scheduling for pickup points updates
 * - Integration with various shipping methods
 * - Checkout field display and validation
 * - Order meta management
 * - Email and order details customization
 * - Admin settings and notices
 * - PRO plugin integrations
 *
 * @return void
 */
function cppw_init() {
	// Only on WooCommerce >= 7.1
	if ( class_exists( 'WooCommerce' ) && version_compare( WC_VERSION, '7.1', '>=' ) ) {
		// Cron
		cppw_cronstarter_activation();
		add_action( 'cppw_update_pickup_list', 'cppw_update_pickup_list_function' );
		// De-activate cron
		register_deactivation_hook( __FILE__, 'cppw_cronstarter_deactivate' );
		// Add our settings to the available shipping methods - should be a loop with all the available ones
			add_action( 'wp_loaded', 'cppw_fields_filters' );
			// WooCommerce Table Rate Shipping - http://bolderelements.net/plugins/table-rate-shipping-woocommerce/ - Not available at plugins_loaded time
			add_filter( 'woocommerce_shipping_instance_form_fields_betrs_shipping', 'cppw_woocommerce_shipping_instance_form_fields_betrs_shipping' );
			// WooCommerce Advanced Shipping - https://codecanyon.net/item/woocommerce-advanced-shipping/8634573 - Not available at plugins_loaded time
			add_filter( 'was_after_meta_box_settings', 'cppw_was_after_meta_box_settings' );
		// Add to checkout
		add_action( 'woocommerce_review_order_before_payment', 'cppw_woocommerce_review_order_before_payment' );
		// Add to checkout - Fragment
		add_filter( 'woocommerce_update_order_review_fragments', 'cppw_woocommerce_update_order_review_fragments' );
		// Validate
		add_action( 'woocommerce_after_checkout_validation', 'cppw_woocommerce_after_checkout_validation', 10, 2 );
		// Save order meta
		add_action( 'woocommerce_checkout_update_order_meta', 'cppw_save_extra_order_meta' );
		// Show order meta on order screen and order preview
		add_action( 'woocommerce_admin_order_data_after_shipping_address', 'cppw_woocommerce_admin_order_data_after_shipping_address' );
		add_action( 'woocommerce_admin_order_preview_end', 'cppw_woocommerce_admin_order_preview_end' );
		add_filter( 'woocommerce_admin_order_preview_get_order_details', 'cppw_woocommerce_admin_order_preview_get_order_details', 10, 2 );
		// Ajax for point details update
		add_action( 'wc_ajax_cppw_point_details', 'wc_ajax_cppw_point_details' );
		// Add information to emails and order details
		if ( get_option( 'cppw_email_info', 'yes' ) === 'yes' ) {
			// Ideally we would use the same space used by the shipping address, but it's not possible - https://github.com/woocommerce/woocommerce/issues/19258
			add_action( 'woocommerce_email_customer_details', 'cppw_woocommerce_email_customer_details', 30, 3 );
			add_action( 'woocommerce_order_details_after_order_table', 'cppw_woocommerce_order_details_after_order_table', 11 );
		}
		// Hide shipping address
		if ( get_option( 'cppw_hide_shipping_address', 'yes' ) === 'yes' ) {
			add_filter( 'woocommerce_order_needs_shipping_address', 'cppw_woocommerce_order_needs_shipping_address', 10, 3 );
		}
		// Change orders list shipping address
		add_action( 'manage_shop_order_posts_custom_column', 'cppw_manage_shop_order_custom_column', 9, 2 ); // Posts
		add_action( 'woocommerce_shop_order_list_table_custom_column', 'cppw_manage_shop_order_custom_column', 9, 2 ); // HPOS
		// Add instructions to the checkout
		if ( trim( get_option( 'cppw_instructions', '' ) ) !== '' ) {
			add_action( 'woocommerce_after_shipping_rate', 'cppw_woocommerce_after_shipping_rate', 10, 2 );
		}
		// Settings
		if ( is_admin() && ! wp_doing_ajax() ) {
			add_filter( 'woocommerce_shipping_settings', 'cppw_woocommerce_shipping_settings' );
			add_action( 'admin_notices', 'cppw_admin_notices' );
		}
		// PRO Plugin integrations
		add_filter( 'cppw_point_is_locker', 'cppw_point_is_locker_filter', 10, 2 );
		add_filter( 'cppw_get_pickup_points', 'cppw_get_pickup_points' );
		// Enquerue scripts
		add_action( 'wp_enqueue_scripts', 'cppw_wp_enqueue_scripts' );
	}
}
add_action( 'plugins_loaded', 'cppw_init', 999 ); // 999 because of WooCommerce Table Rate

/**
 * Enqueue plugin stylesheets and JavaScript files.
 *
 * Loads the necessary CSS and JS files on checkout and cart pages.
 * Applies Flatsome theme fixes when that theme is active.
 * Localizes JavaScript with shipping methods configuration and shop country data.
 *
 * @return void
 */
function cppw_wp_enqueue_scripts() {
	if ( ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_cart' ) && is_cart() ) ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = get_plugin_data( __FILE__ );
		wp_enqueue_style( 'cppw-css', plugins_url( '/assets/style.css', __FILE__ ), array(), $plugin_data['Version'] );
		if ( class_exists( 'Flatsome_Default' ) && apply_filters( 'cppw_fix_flatsome', true ) ) {
			wp_enqueue_style( 'cppw-flatsome-css', plugins_url( '/assets/style-flatsome.css', __FILE__ ), array(), $plugin_data['Version'] );
		}
		if ( is_checkout() ) {
			wp_enqueue_script( 'cppw-js', plugins_url( '/assets/functions.js', __FILE__ ), array( 'jquery' ), $plugin_data['Version'], true );
			wp_localize_script(
				'cppw-js',
				'cppw',
				array(
					'shipping_methods' => cppw_get_shipping_methods(),
					'shop_country'     => wc_get_base_location()['country'],
				)
			);
		}
	}
}

/**
 * Register filters for shipping method settings fields.
 *
 * Iterates through all available WooCommerce shipping methods and adds
 * appropriate filters to inject DPD Pickup settings fields.
 * Handles special cases for Flexible Shipping and Table Rate Shipping plugins.
 *
 * Note: https://woocommerce.wp-a2z.org/oik_api/wc_shippingget_shipping_methods/
 *
 * @return void
 */
function cppw_fields_filters() {
	// Avoid fatal errors on some weird scenarios
	if ( is_null( WC()->countries ) ) {
		WC()->countries = new WC_Countries();
	}
	// Load our filters
	foreach ( WC()->shipping()->get_shipping_methods() as $method ) { // https://woocommerce.wp-a2z.org/oik_api/wc_shippingget_shipping_methods/
		if ( ! $method->supports( 'shipping-zones' ) ) {
			continue;
		}
		switch ( $method->id ) {
			// Flexible Shipping for WooCommerce - https://wordpress.org/plugins/flexible-shipping/
			case 'flexible_shipping':
			case 'flexible_shipping_single':
				add_filter( 'flexible_shipping_method_settings', 'cppw_woocommerce_shipping_instance_form_fields_flexible_shipping', 10, 2 );
				add_filter( 'flexible_shipping_process_admin_options', 'cppw_woocommerce_shipping_instance_form_fields_flexible_shipping_save' );
				break;
			// The WooCommerce or other standard methods that implement the 'woocommerce_shipping_instance_form_fields_' filter
			default:
				add_filter( 'woocommerce_shipping_instance_form_fields_' . $method->id, 'cppw_woocommerce_shipping_instance_form_fields' );
				break;
		}
	}
}

/**
 * Add DPD Pickup option field to standard shipping method settings.
 *
 * Injects a Yes/No select field into shipping method instance settings
 * that allows enabling DPD Pickup point selection for that method.
 * Used for WooCommerce core and compatible third-party shipping methods.
 *
 * @param array $settings Existing shipping method settings array.
 * @return array Modified settings array with DPD Pickup field added.
 */
function cppw_woocommerce_shipping_instance_form_fields( $settings ) {
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	$settings['cppw'] = array(
		'title'       => __( 'DPD Pickup in Portugal', 'portugal-chronopost-pickup-woocommerce' ),
		'type'        => 'select',
		'description' => __( 'Shows a field to select a point from the DPD Pickup network in Portugal', 'portugal-chronopost-pickup-woocommerce' ),
		'default'     => '',
		'options'     => array(
			''  => __( 'No', 'portugal-chronopost-pickup-woocommerce' ),
			'1' => __( 'Yes', 'portugal-chronopost-pickup-woocommerce' ),
		),
		'desc_tip'    => true,
	);
	return $settings;
}

/**
 * Add DPD Pickup option field to Flexible Shipping settings.
 *
 * Integrates DPD Pickup selection with the Flexible Shipping for WooCommerce plugin.
 * Preserves existing settings when rendering the configuration form.
 *
 * Plugin URL: https://wordpress.org/plugins/flexible-shipping/
 *
 * @param array $settings      Current Flexible Shipping method settings.
 * @param array $shipping_method The shipping method configuration data.
 * @return array Modified settings with DPD Pickup field.
 */
function cppw_woocommerce_shipping_instance_form_fields_flexible_shipping( $settings, $shipping_method ) {
	$settings['cppw'] = array(
		'title'       => __( 'DPD Pickup in Portugal', 'portugal-chronopost-pickup-woocommerce' ),
		'type'        => 'select',
		'description' => __( 'Shows a field to select a point from the Chronopost Pickup network in Portugal', 'portugal-chronopost-pickup-woocommerce' ),
		'default'     => isset( $shipping_method['cppw'] ) && intval( $shipping_method['cppw'] ) === 1 ? '1' : '',
		'options'     => array(
			''  => __( 'No', 'portugal-chronopost-pickup-woocommerce' ),
			'1' => __( 'Yes', 'portugal-chronopost-pickup-woocommerce' ),
		),
		'desc_tip'    => true,
	);
	return $settings;
}

/**
 * Save DPD Pickup option for Flexible Shipping methods.
 *
 * Processes and stores the DPD Pickup field value when Flexible Shipping
 * settings are saved.
 *
 * Plugin URL: https://wordpress.org/plugins/flexible-shipping/
 *
 * @param array $shipping_method The shipping method data being saved.
 * @return array Modified shipping method data with DPD Pickup value.
 */
function cppw_woocommerce_shipping_instance_form_fields_flexible_shipping_save( $shipping_method ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$shipping_method['cppw'] = isset( $_POST['woocommerce_flexible_shipping_cppw'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_flexible_shipping_cppw'] ) ) : '';
	return $shipping_method;
}

/**
 * Add DPD Pickup option field to WooCommerce Table Rate Shipping settings.
 *
 * Integrates DPD Pickup with the Table Rate Shipping plugin by Bolder Elements.
 * Adds the field to the general settings section of the shipping method.
 *
 * Plugin URL: http://bolderelements.net/plugins/table-rate-shipping-woocommerce/
 *
 * @param array $settings Current Table Rate Shipping settings structure.
 * @return array Modified settings with DPD Pickup field in general section.
 */
function cppw_woocommerce_shipping_instance_form_fields_betrs_shipping( $settings ) {
	$settings['general']['settings']['cppw'] = array(
		'title'       => __( 'DPD Pickup in Portugal', 'portugal-chronopost-pickup-woocommerce' ),
		'type'        => 'select',
		'description' => __( 'Shows a field to select a point from the DPD Pickup network in Portugal', 'portugal-chronopost-pickup-woocommerce' ),
		'default'     => '',
		'options'     => array(
			''  => __( 'No', 'portugal-chronopost-pickup-woocommerce' ),
			'1' => __( 'Yes', 'portugal-chronopost-pickup-woocommerce' ),
		),
		'desc_tip'    => true,
	);
	return $settings;
}

/**
 * Add DPD Pickup option field to WooCommerce Advanced Shipping meta box.
 *
 * Outputs HTML for DPD Pickup selection in the Advanced Shipping plugin settings.
 * Renders a dropdown field directly in the meta box settings area.
 *
 * Plugin URL: https://codecanyon.net/item/woocommerce-advanced-shipping/8634573
 *
 * @param array $settings Current Advanced Shipping method settings.
 * @return void Outputs HTML directly.
 */
function cppw_was_after_meta_box_settings( $settings ) {
	?>
		<p class='was-option'>
			<label for='tax'><?php esc_html_e( 'DPD Pickup in Portugal', 'portugal-chronopost-pickup-woocommerce' ); ?></label>
			<select name='_was_shipping_method[cppw]' style='width: 189px;'>
				<option value="" <?php selected( $settings['cppw'], '' ); ?>><?php esc_html_e( 'No', 'portugal-chronopost-pickup-woocommerce' ); ?></option>
				<option value="1" <?php selected( $settings['cppw'], '1' ); ?>><?php esc_html_e( 'Yes', 'portugal-chronopost-pickup-woocommerce' ); ?></option>
			</select>
		</p>
		<?php
}

/**
 * Get all shipping method IDs that have DPD Pickup enabled.
 *
 * Queries the database and options to find all shipping methods across different
 * plugins that have DPD Pickup integration activated. Handles multiple shipping
 * method types including:
 * - Flexible Shipping for WooCommerce (https://wordpress.org/plugins/flexible-shipping/)
 * - WooCommerce Table Rate Shipping (http://bolderelements.net/plugins/table-rate-shipping-woocommerce/)
 * - Table Rate Shipping by WooCommerce (https://woocommerce.com/products/table-rate-shipping/)
 * - WooCommerce Advanced Shipping (https://codecanyon.net/item/woocommerce-advanced-shipping/8634573)
 * - Standard WooCommerce shipping methods
 *
 * @return array Array of shipping method IDs with DPD Pickup enabled.
 */
function cppw_get_shipping_methods() {
	$shipping_methods = array();
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods" );
	foreach ( $results as $method ) {
		switch ( $method->method_id ) {
			// Flexible Shipping for WooCommerce - https://wordpress.org/plugins/flexible-shipping/
			case 'flexible_shipping':
				$options = get_option( 'flexible_shipping_methods_' . $method->instance_id, array() );
				foreach ( $options as $key => $fl_options ) {
					if ( isset( $fl_options['cppw'] ) && intval( $fl_options['cppw'] ) === 1 ) {
						$shipping_methods[] = $method->method_id . '_' . $method->instance_id . '_' . $fl_options['id'];
					}
				}
				break;
			// WooCommerce Table Rate Shipping - http://bolderelements.net/plugins/table-rate-shipping-woocommerce/
			case 'betrs_shipping':
				$options = get_option( 'woocommerce_betrs_shipping_' . $method->instance_id . '_settings', array() );
				if ( isset( $options['cppw'] ) && intval( $options['cppw'] ) === 1 ) {
					$options_instance = get_option( 'betrs_shipping_options-' . $method->instance_id, array() );
					if ( isset( $options_instance['settings'] ) && is_array( $options_instance['settings'] ) ) {
						foreach ( $options_instance['settings'] as $setting ) {
							if ( isset( $setting['option_id'] ) ) {
								$shipping_methods[] = $method->method_id . ':' . $method->instance_id . '-' . $setting['option_id'];
							}
						}
					}
				}
				break;
			// Table Rate Shipping - https://woocommerce.com/products/table-rate-shipping/
			case 'table_rate':
				$options = get_option( 'woocommerce_table_rate_' . $method->instance_id . '_settings', array() );
				if ( isset( $options['cppw'] ) && intval( $options['cppw'] ) === 1 ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$rates = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT rate_id FROM {$wpdb->prefix}woocommerce_shipping_table_rates WHERE shipping_method_id = %d",
							$method->instance_id
						)
					);
					foreach ( $rates as $rate ) {
						$shipping_methods[] = $method->method_id . ':' . $method->instance_id . ':' . $rate->rate_id;
					}
				}
				break;
			// The WooCommerce or other standard methods that implement the 'woocommerce_shipping_instance_form_fields_' filter
			default:
				$options = get_option( 'woocommerce_' . $method->method_id . '_' . $method->instance_id . '_settings', array() );
				if ( isset( $options['cppw'] ) && intval( $options['cppw'] ) === 1 ) {
					$shipping_methods[] = $method->method_id . ':' . $method->instance_id;
				}
				break;
		}
	}
	// WooCommerce Advanced Shipping - https://codecanyon.net/item/woocommerce-advanced-shipping/8634573
	if ( class_exists( 'WooCommerce_Advanced_Shipping' ) ) {
		$methods = get_posts(
			array(
				'posts_per_page'   => '-1',
				'post_type'        => 'was',
				'orderby'          => 'menu_order',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		foreach ( $methods as $method ) {
			$settings = get_post_meta( $method->ID, '_was_shipping_method', true );
			if ( is_array( $settings ) && isset( $settings['cppw'] ) && intval( $settings['cppw'] ) === 1 ) {
				$shipping_methods[] = (string) $method->ID;
			}
		}
	}
	// Filter and return them
	$shipping_methods = array_unique( apply_filters( 'cppw_get_shipping_methods', $shipping_methods ) );
	return $shipping_methods;
}

/**
 * Display DPD Pickup point selection field on checkout page.
 *
 * Renders a hidden div that becomes visible when appropriate shipping methods
 * are selected. Contains the pickup point dropdown and details display area.
 *
 * @return void Outputs HTML directly.
 */
function cppw_woocommerce_review_order_before_payment() {
	$shipping_methods = cppw_get_shipping_methods();
	if ( count( $shipping_methods ) > 0 ) {
		$classes = 'form-row form-row-wide validate-required';
		if ( get_option( 'cppw_checkout_default_empty' ) === 'yes' ) {
			$classes .= ' validate-required woocommerce-invalid';
		}
		?>
		<div id="cppw" style="display: none;">
			<p class="<?php echo esc_attr( $classes ); ?>" id="cppw_field">
				<label for="cppw_point">
					<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/dpd_230_100.png' ); ?>" width="230" height="100" id="dpd_img"/>
				<?php esc_html_e( 'Select the DPD Pickup point', 'portugal-chronopost-pickup-woocommerce' ); ?>
					<span class="cppw-clear"></span>
				</label>
				<?php echo cppw_points_fragment(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</p>
			<div class="cppw-clear"></div>
		</div>
		<?php
	}
}

/**
 * Display custom instructions after shipping rate on checkout.
 *
 * Shows configured instructions text below shipping methods that have
 * DPD Pickup enabled, helping customers understand the service.
 *
 * @param WC_Shipping_Rate $method The shipping rate method object.
 * @param int              $index  The index of the shipping method.
 * @return void Outputs HTML directly.
 */
function cppw_woocommerce_after_shipping_rate( $method, $index ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$show = false;
	switch ( $method->get_method_id() ) {
		case 'flexible_shipping':
			$options = get_option( 'flexible_shipping_methods_' . $method->get_instance_id(), array() );
			foreach ( $options as $key => $fl_options ) {
				$show = isset( $fl_options['cppw'] ) && ( intval( $fl_options['cppw'] ) === 1 );
			}
			break;
		// phpcs:disable
		/*
		case 'advanced_shipping':
			break;
		*/
		// phpcs:enable
		case 'table_rate':
			$options = get_option( 'woocommerce_table_rate_' . $method->get_instance_id() . '_settings', array() );
			$show    = isset( $options['cppw'] ) && intval( $options['cppw'] ) === 1;
			break;
		default:
			$options = get_option( 'woocommerce_' . $method->get_method_id() . '_' . $method->get_instance_id() . '_settings', array() );
			$show    = isset( $options['cppw'] ) && intval( $options['cppw'] ) === 1;
			break;
	}
	if ( $show ) {
		?>
		<div class="cppw_shipping_method_instructions"><?php echo nl2br( esc_html( trim( get_option( 'cppw_instructions', '' ) ) ) ); ?></div>
		<?php
	}
}

/**
 * Check if a DPD point is a locker.
 *
 * Determines if the pickup point is a DPD Locker by checking if the name
 * contains the word "locker".
 *
 * Note: This is a workaround since DPD doesn't provide a dedicated flag
 * to distinguish lockers from regular pickup points.
 *
 * @param array $point The pickup point data array.
 * @return bool True if point is a locker, false otherwise.
 */
function cppw_point_is_locker( $point ) {
	return stristr( $point['nome'], 'locker' ); // Big hack dear DPD people... big hack!
}

/**
 * Filter callback for checking if a point is a locker.
 *
 * Wrapper function for use with the 'cppw_point_is_locker' filter hook.
 *
 * @param bool  $is_locker The default boolean value (unused).
 * @param array $point     The pickup point data array.
 * @return bool True if point is a locker, false otherwise.
 */
function cppw_point_is_locker_filter( $is_locker, $point ) {
	return cppw_point_is_locker( $point );
}

/**
 * Generate pickup points selection dropdown HTML fragment.
 *
 * Creates the pickup point select field with nearby and other points organized
 * in optgroups. Points are sorted by proximity to customer's postcode.
 * Only displays for Portugal (PT) country code.
 *
 * @return string HTML fragment with pickup point dropdown and details.
 */
function cppw_points_fragment() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$postcode = '';
	$country  = '';
	$nearby   = intval( get_option( 'cppw_nearby_points', 10 ) );
	$total    = intval( get_option( 'cppw_total_points', 50 ) );
	if ( isset( $_POST['s_postcode'] ) && trim( sanitize_text_field( wp_unslash( $_POST['s_postcode'] ) ) ) !== '' ) {
		$postcode = trim( sanitize_text_field( wp_unslash( $_POST['s_postcode'] ) ) );
	} elseif ( isset( WC()->session ) ) {
		$customer = WC()->session->get( 'customer' );
		if ( ! empty( $customer ) ) {
			$postcode = $customer['shipping_postcode'];
		}
	}
	$postcode = wc_format_postcode( $postcode, 'PT' );
	if ( isset( $_POST['s_country'] ) && trim( sanitize_text_field( wp_unslash( $_POST['s_country'] ) ) ) !== '' ) {
		$country = trim( sanitize_text_field( wp_unslash( $_POST['s_country'] ) ) );
	} elseif ( isset( WC()->session ) ) {
		$customer = WC()->session->get( 'customer' );
		if ( ! empty( $customer ) ) {
			$country = $customer['shipping_country'];
		}
	}
	ob_start();
	?>
	<span class="cppw-points-fragment">
	<?php
	if ( $country === 'PT' ) {
		$points = cppw_get_pickup_points( $postcode );
		if ( is_array( $points ) && count( $points ) > 0 ) {
			// Developers can choose not to show all $points
			$points = apply_filters( 'cppw_available_points', $points, $postcode );
			// Remove lockers from list?
			if ( apply_filters( 'cppw_hide_lockers', false ) ) {
				foreach ( $points as $key => $ponto ) {
					if ( cppw_point_is_locker( $ponto ) ) {
						unset( $points[ $key ] );
					}
				}
			}
			// Let's do it then
			if ( count( $points ) > 0 ) {
				?>
					<select name="cppw_point" id="cppw_point">
						<?php if ( get_option( 'cppw_checkout_default_empty' ) === 'yes' ) { ?>
							<option value="">- <?php esc_html_e( 'Select point', 'portugal-chronopost-pickup-woocommerce' ); ?> -</option>
						<?php } ?>
						<optgroup label="<?php esc_html_e( 'Near you', 'portugal-chronopost-pickup-woocommerce' ); ?>">
							<?php
							$i = 0;
							foreach ( $points as $ponto ) {
								++$i;
								if ( $i === 1 ) {
									$first = $ponto;
								}
								if ( $i === $nearby + 1 ) {
									?>
								</optgroup>
								<optgroup label="<?php esc_html_e( 'Other spots', 'portugal-chronopost-pickup-woocommerce' ); ?>">
								<?php } ?>
								<option value="<?php echo esc_attr( $ponto['number'] ); ?>">
									<?php echo esc_html( $ponto['localidade'] ); ?>
									-
									<?php echo esc_html( $ponto['nome'] ); ?>
								</option>
								<?php
								if ( $i === $total ) {
									break;
								}
							}
							?>
						</optgroup>
					</select>
					<input type="hidden" name="cppw_point_active" id="cppw_point_active" value="0"/>
					<?php
					cppw_point_details( get_option( 'cppw_checkout_default_empty' ) === 'yes' ? null : $first );
			} else {
				?>
				<p><strong><?php esc_html_e( 'ERROR: No DPD points were found.', 'portugal-chronopost-pickup-woocommerce' ); ?></strong></p>
				<?php
			}
		} else {
			?>
			<p><strong><?php esc_html_e( 'ERROR: There are no DPD points in the database. The update process has not yet ended successfully.', 'portugal-chronopost-pickup-woocommerce' ); ?></strong></p>
			<?php
		}
	}
	?>
	</span>
	<?php
	return ob_get_clean();
	// phpcs:enable
}

/**
 * Update pickup points dropdown via AJAX fragments.
 *
 * Refreshes the pickup points dropdown when checkout is updated,
 * ensuring points are recalculated based on current shipping address.
 *
 * @param array $fragments Existing checkout fragments to update.
 * @return array Modified fragments with updated pickup points HTML.
 */
function cppw_woocommerce_update_order_review_fragments( $fragments ) {
	$fragments['.cppw-points-fragment'] = cppw_points_fragment();
	return $fragments;
}

/**
 * Display detailed information for a selected pickup point.
 *
 * Renders point details including:
 * - Static map (Mapbox or Google Maps)
 * - Point name and address
 * - Phone number (if enabled and available)
 * - Opening hours (if enabled and available)
 *
 * @param array|null $point The pickup point data array, or null for empty state.
 * @return void Outputs HTML directly.
 */
function cppw_point_details( $point ) {
	if ( $point ) {
		$mapbox_public_token = trim( get_option( 'cppw_mapbox_public_token', '' ) );
		$google_api_key      = trim( get_option( 'cppw_google_api_key', '' ) );
		$map_width           = intval( apply_filters( 'cppw_map_width', 80 ) );
		$map_height          = intval( apply_filters( 'cppw_map_height', 80 ) );
		$img_html            = '<!-- No map because neither Mapbox public token or Google Maps API Key are filled in -->';
		if ( trim( $mapbox_public_token ) !== '' ) {
				$img_html = sprintf(
					'<img src="https://api.mapbox.com/styles/v1/mapbox/streets-v10/static/pin-s+FF0000(%s,%s)/%s,%s,%d,0,0/%dx%d%s?access_token=%s" width="%d" height="%d"/>',
					esc_attr( trim( $point['gps_lon'] ) ),
					esc_attr( trim( $point['gps_lat'] ) ),
					esc_attr( trim( $point['gps_lon'] ) ),
					esc_attr( trim( $point['gps_lat'] ) ),
					apply_filters( 'cppw_map_zoom', 10 ),
					$map_width,
					$map_height,
					intval( apply_filters( 'cppw_map_scale', 2 ) === 2 ) ? '@2x' : '',
					esc_attr( $mapbox_public_token ),
					$map_width,
					$map_height
				);
		} elseif ( trim( $google_api_key ) !== '' ) {
				$img_html = sprintf(
					'<img src="https://maps.googleapis.com/maps/api/staticmap?center=%s,%s&amp;markers=%s,%s&amp;size=%dx%d&amp;scale=%d&amp;zoom=%d&amp;language=%s&amp;key=%s" width="%d" height="%d"/>',
					esc_attr( trim( $point['gps_lat'] ) ),
					esc_attr( trim( $point['gps_lon'] ) ),
					esc_attr( trim( $point['gps_lat'] ) ),
					esc_attr( trim( $point['gps_lon'] ) ),
					$map_width,
					$map_height,
					intval( apply_filters( 'cppw_map_scale', 2 ) === 2 ) ? 2 : 1,
					apply_filters( 'cppw_map_zoom', 11 ),
					esc_attr( get_locale() ),
					esc_attr( $google_api_key ),
					$map_width,
					$map_height
				);
		}
		?>
			<span class="cppw-points-fragment-point-details">
				<span id="cppw-points-fragment-point-details-address">
					<span id="cppw-points-fragment-point-details-map">
						<a href="https://www.google.pt/maps?q=<?php echo esc_attr( trim( $point['gps_lat'] ) ); ?>,<?php echo esc_attr( trim( $point['gps_lon'] ) ); ?>" target="_blank">
						<?php echo wp_kses_post( $img_html ); ?>
						</a>
					</span>
					<strong><?php echo esc_html( $point['nome'] ); ?></strong>
					<br/>
				<?php echo esc_html( $point['morada1'] ); ?>
					<br/>
				<?php echo esc_html( $point['cod_postal'] ); ?>
				<?php echo esc_html( $point['localidade'] ); ?>
				<?php if ( get_option( 'cppw_display_phone', 'yes' ) === 'yes' || get_option( 'cppw_display_schedule', 'yes' ) === 'yes' ) { ?>
						<small>
							<?php if ( get_option( 'cppw_display_phone', 'yes' ) === 'yes' && isset( $point['telefone'] ) && trim( $point['telefone'] ) !== '' ) { ?>
								<br/>
								<?php esc_html_e( 'Phone:', 'portugal-chronopost-pickup-woocommerce' ); ?> <?php echo esc_html( $point['telefone'] ); ?>
							<?php } ?>
							<?php if ( get_option( 'cppw_display_schedule', 'yes' ) === 'yes' ) { ?>
								<?php if ( isset( $point['horario_semana'] ) && trim( $point['horario_semana'] ) !== '' ) { ?>
									<br/>
									<?php esc_html_e( 'Work days:', 'portugal-chronopost-pickup-woocommerce' ); ?> <?php echo esc_html( $point['horario_semana'] ); ?>
								<?php } ?>
								<?php if ( isset( $point['horario_sabado'] ) && trim( $point['horario_sabado'] ) !== '' ) { ?>
									<br/>
									<?php esc_html_e( 'Saturday:', 'portugal-chronopost-pickup-woocommerce' ); ?> <?php echo esc_html( $point['horario_sabado'] ); ?>
								<?php } ?>
								<?php if ( isset( $point['horario_domingo'] ) && trim( $point['horario_domingo'] ) !== '' ) { ?>
									<br/>
									<?php esc_html_e( 'Sunday:', 'portugal-chronopost-pickup-woocommerce' ); ?> <?php echo esc_html( $point['horario_domingo'] ); ?>
								<?php } ?>
							<?php } ?>
						</small>
					<?php } ?>
				</span>
				<span class="cppw-clear"></span>
				<input type="hidden" id="cppw_point_active_is_locker" value="<?php echo cppw_point_is_locker( $point ) ? 1 : 0; ?>"/>
			</span>
			<?php
	} else {
		?>
			<span class="cppw-points-fragment-point-details">
				<!-- empty -->
				<span class="cppw-clear"></span>
			</span>
		<?php
	}
}

/**
 * AJAX handler for updating pickup point details.
 *
 * Responds to AJAX requests when customer selects a different pickup point
 * in the dropdown. Returns HTML fragment with updated point information.
 *
 * @return void Sends JSON response and terminates.
 */
function wc_ajax_cppw_point_details() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$fragments = array();
	if ( isset( $_POST['cppw_point'] ) ) {
		$cppw_point = trim( sanitize_text_field( wp_unslash( $_POST['cppw_point'] ) ) );
		$points     = cppw_get_pickup_points();
		if ( isset( $points[ $cppw_point ] ) ) {
			ob_start();
			cppw_point_details( $points[ $cppw_point ] );
			$fragments = array(
				'.cppw-points-fragment-point-details' => ob_get_clean(),
			);
		}
	}
	if ( count( $fragments ) === 0 ) {
		ob_start();
		cppw_point_details( null );
		$fragments = array(
			'.cppw-points-fragment-point-details' => ob_get_clean(),
		);
	}
	wp_send_json(
		array(
			'fragments' => $fragments,
		)
	);
	// phpcs:enable
}

/**
 * Validate DPD Pickup point selection during checkout.
 *
 * Ensures a pickup point is selected when required. If the option to force
 * point selection is enabled and the field is visible but empty, adds a
 * validation error to prevent checkout completion.
 *
 * @param array    $fields WooCommerce checkout fields data.
 * @param WP_Error $errors WooCommerce errors object.
 * @return void Adds error to $errors object if validation fails.
 */
function cppw_woocommerce_after_checkout_validation( $fields, $errors ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( get_option( 'cppw_checkout_default_empty' ) === 'yes' ) {
		if ( isset( $_POST['cppw_point'] ) && ( trim( sanitize_text_field( wp_unslash( $_POST['cppw_point'] ) ) ) === '' ) && isset( $_POST['cppw_point_active'] ) && ( intval( $_POST['cppw_point_active'] ) === 1 ) ) {
			$errors->add(
				'cppw_point_validation',
				__( 'You need to select a <strong>DPD Pickup point</strong>.', 'portugal-chronopost-pickup-woocommerce' ),
				array( 'id' => 'cppw_point' )
			);
		}
	}
	// phpcs:enable
}

/**
 * Save selected DPD Pickup point to order meta.
 *
 * Stores the chosen pickup point ID as order metadata when a valid point
 * is selected and the order uses a compatible shipping method.
 * Validates against enabled shipping methods before saving.
 *
 * @param int $order_id The WooCommerce order ID.
 * @return void
 */
function cppw_save_extra_order_meta( $order_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( isset( $_POST['cppw_point'] ) && ( trim( sanitize_text_field( wp_unslash( $_POST['cppw_point'] ) ) ) !== '' ) && isset( $_POST['cppw_point_active'] ) && ( intval( $_POST['cppw_point_active'] ) === 1 ) ) {
		$cppw_point            = trim( sanitize_text_field( wp_unslash( $_POST['cppw_point'] ) ) );
		$order                 = new WC_Order( $order_id );
		$cppw_shipping_methods = cppw_get_shipping_methods();
		$order_shipping_method = $order->get_shipping_methods();
		$save                  = false;
		foreach ( $order_shipping_method as $method ) {
			switch ( $method['method_id'] ) {
				case 'flexible_shipping':
					$options = get_option( 'flexible_shipping_methods_' . $method['instance_id'], array() );
					foreach ( $options as $key => $fl_options ) {
						if ( isset( $fl_options['cppw'] ) && intval( $fl_options['cppw'] ) === 1 && in_array( $method['method_id'] . '_' . $method['instance_id'] . '_' . $fl_options['id'], $cppw_shipping_methods, true ) ) {
							$save = true;
						}
					}
					break;
				case 'advanced_shipping':
					// We'll trust on intval( $_POST['cppw_point_active'] ) ===  1 because we got no way to identify which of the Advanced Shipping rules was used
					$save = true;
					break;
				case 'table_rate':
					$options = get_option( 'woocommerce_table_rate_' . $method['instance_id'] . '_settings', array() );
					if ( isset( $options['cppw'] ) && intval( $options['cppw'] ) === 1 ) {
						$save = true;
					}
					break;
				case 'betrs_shipping':
					$options_instance = get_option( 'betrs_shipping_options-' . $method['instance_id'], array() );
					if ( isset( $options_instance['settings'] ) && is_array( $options_instance['settings'] ) ) {
						foreach ( $options_instance['settings'] as $setting ) {
							if ( isset( $setting['option_id'] ) && in_array( $method['method_id'] . ':' . $method['instance_id'] . '-' . $setting['option_id'], $cppw_shipping_methods, true ) ) {
								$save = true;
								break;
							}
						}
					}
					break;
				default:
					// Others
					if ( in_array( $method['method_id'], $cppw_shipping_methods, true ) || in_array( $method['method_id'] . ':' . $method['instance_id'], $cppw_shipping_methods, true ) ) {
						$save = true;
					}
					break;
			}
			break; // Only one shipping method supported
		}
		if ( $save ) {
			// Save order meta
			$order->update_meta_data( 'cppw_point', $cppw_point );
			$order->save();
		}
	}
	// phpcs:enable
}

/**
 * Display DPD Pickup point information on admin order edit screen.
 *
 * Shows the selected pickup point details in the order shipping address
 * section for admin users.
 *
 * @param WC_Order $order The WooCommerce order object.
 * @return void Outputs HTML directly.
 */
function cppw_woocommerce_admin_order_data_after_shipping_address( $order ) {
	$cppw_point = $order->get_meta( 'cppw_point' );
	if ( trim( $cppw_point ) !== '' ) {
		?>
			<h3><?php esc_html_e( 'DPD Pickup point', 'portugal-chronopost-pickup-woocommerce' ); ?></h3>
			<p><strong><?php echo esc_html( $cppw_point ); ?></strong></p>
			<?php
			$points = cppw_get_pickup_points();
			if ( isset( $points[ trim( $cppw_point ) ] ) ) {
				$point = $points[ trim( $cppw_point ) ];
				cppw_point_information( $point, false, true, true );
			} else {
				?>
				<p><?php esc_html_e( 'Unable to find point on the database', 'portugal-chronopost-pickup-woocommerce' ); ?></p>
				<?php
			}
	}
}

/**
 * Display admin notices for pickup points database status.
 *
 * Checks if pickup points are loaded in the database and displays warnings
 * if empty. Provides option to manually trigger an update.
 * Only shown on WooCommerce settings pages.
 *
 * @return void Outputs HTML notices directly.
 */
function cppw_admin_notices() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
	global $pagenow;
	if ( $pagenow === 'admin.php' && isset( $_GET['page'] ) && trim( sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) === 'wc-settings' ) {
		$points = cppw_get_pickup_points();
		if ( count( $points ) === 0 ) {
			if ( isset( $_GET['cppw_force_update'] ) ) {
				if ( cppw_update_pickup_list_function() ) {
					?>
						<div class="notice notice-success">
							<p><?php esc_html_e( 'DPD Pickup points updated.', 'portugal-chronopost-pickup-woocommerce' ); ?></p>
						</div>
						<?php
				} else {
					?>
						<div class="notice notice-error">
							<p><?php esc_html_e( 'It was not possible to update the DPD Pickup points.', 'portugal-chronopost-pickup-woocommerce' ); ?></p>
						</div>
						<?php
				}
			} else {
				?>
					<div class="notice notice-error">
						<p><?php esc_html_e( 'ERROR: There are no DPD points in the database. The update process has not yet ended successfully.', 'portugal-chronopost-pickup-woocommerce' ); ?></p>
						<p><a href="admin.php?page=wc-settings&amp;cppw_force_update"><strong><?php esc_html_e( 'Click here to force the update process', 'portugal-chronopost-pickup-woocommerce' ); ?></strong></a></p>
					</div>
					<?php
			}
		}
	}
	// phpcs:enable
}

/**
 * Update pickup points from DPD webservices.
 *
 * Fetches the latest pickup points data from DPD Portugal webservices.
 * Attempts multiple endpoints in random order for redundancy:
 * - DPD official webservice
 * - Webdados proxy server
 *
 * Falls back to FTP if webservices fail. Updates are stored in WordPress
 * options and include point details like location, GPS coordinates, schedules.
 *
 * @return bool True if update successful, false otherwise.
 */
function cppw_update_pickup_list_function() {
	// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$urls = array(
		'https://webservices.chronopost.pt:7554/PUDOPoints/rest/PUDOPoints/Country/PT',
		'https://chronopost.webdados.pt/webservice_proxy.php',
	);
	shuffle( $urls ); // Random order
	$args = array(
		'headers'   => array(
			'Accept' => 'application/json',
		),
		'sslverify' => false,
		'timeout'   => 25,
	);
	update_option( 'cppw_points_last_update_try_datetime', date_i18n( 'Y-m-d H:i:s' ), false );
	$done = false;
	foreach ( $urls as $url ) {
		$response = wp_remote_get( $url, $args );
		if ( ( ! is_wp_error( $response ) ) && is_array( $response ) && intval( $response['response']['code'] ) === 200 ) {
			$body = json_decode( $response['body'] );
			if ( ! empty( $body ) ) {
				if ( is_array( $body->B2CPointsArr ) && count( $body->B2CPointsArr ) > 1 ) {
					$points = array();
					foreach ( $body->B2CPointsArr as $point ) {
						$points[ trim( $point->number ) ] = array(
							'number'          => cppw_fix_spot_text( $point->number ),
							'nome'            => cppw_fix_spot_text( $point->name ),
							'morada1'         => cppw_fix_spot_text( $point->address ),
							'cod_postal'      => cppw_fill_postcode( $point->postalCode ),
							'localidade'      => cppw_fix_spot_text( $point->postalCodeLocation ),
							'gps_lat'         => cppw_fix_spot_text( $point->latitude ),
							'gps_lon'         => cppw_fix_spot_text( $point->longitude ),
							'telefone'        => cppw_fix_spot_text( $point->phoneNumber ),
							'horario_semana'  => cppw_get_spot_schedule( $point, '2' ),
							'horario_sabado'  => cppw_get_spot_schedule( $point, 'S' ),
							'horario_domingo' => cppw_get_spot_schedule( $point, 'D' ),
						);
					}
					update_option( 'cppw_points', $points, false );
					update_option( 'cppw_points_last_update_datetime', date_i18n( 'Y-m-d H:i:s' ), false );
					update_option( 'cppw_points_last_update_server', $url, false );
					$done = true;
					return true;
				} elseif ( apply_filters( 'cppw_update_pickup_list_error_log', false ) ) {
					error_log( '[DPD Portugal Pickup WooCommerce] It was not possible to get the points update: no points array in response (' . $url . ')' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			} elseif ( apply_filters( 'cppw_update_pickup_list_error_log', false ) ) {
					error_log( '[DPD Portugal Pickup WooCommerce] It was not possible to get the points update: no body in response (' . $url . ')' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		} elseif ( apply_filters( 'cppw_update_pickup_list_error_log', false ) ) {
				error_log( '[DPD Portugal Pickup WooCommerce] It was not possible to get the points update via webservice: (' . $url . ') ' . ( is_wp_error( $response ) ? print_r( $response, true ) : 'unknown error' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}
	if ( ! $done ) {
		// FTP fallback
		$ftp_error = true;
		$conn_id   = ftp_connect( 'ftp.dpd.pt' );
		if ( ! empty( $conn_id ) ) {
			if ( ftp_login( $conn_id, 'pickme', 'pickme' ) ) {
				ftp_pasv( $conn_id, true );
				$h = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				if ( ftp_fget( $conn_id, $h, 'lojaspickme.txt', FTP_ASCII, 0 ) ) {
					$fstats = fstat( $h );
					fseek( $h, 0 );
					$contents = fread( $h, $fstats['size'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
					fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					ftp_close( $conn_id );
					if ( trim( $contents ) !== '' ) {
						$temp_points = explode( PHP_EOL, $contents );
						if ( count( $temp_points ) > 1 ) {
							$ftp_error = false;
							$points    = array();
							foreach ( $temp_points as $temp_point ) {
								$temp_point = trim( $temp_point );
								if ( $temp_point !== '' ) {
									$point_number = substr( $temp_point, 0, 5 );
									if ( trim( $point_number ) !== '' ) {
										$points[ $point_number ] = array(
											'number'     => cppw_fix_spot_text( $point_number ),
											'nome'       => cppw_fix_spot_text( substr( $temp_point, 5, 32 ) ),
											'morada1'    => cppw_fix_spot_text( substr( $temp_point, 37, 64 ) ),
											'cod_postal' => cppw_fill_postcode( substr( $temp_point, 101, 10 ) ),
											'localidade' => cppw_fix_spot_text( substr( $temp_point, 111, 26 ) ),
											'gps_lat'    => cppw_fix_spot_text( substr( $temp_point, 137, 9 ) ),
											'gps_lon'    => cppw_fix_spot_text( substr( $temp_point, 146, 9 ) ),
											// No phone or schedule via FTP - We leave it blank to avoid notices
											'telefone'   => '',
											'horario_semana' => '',
											'horario_sabado' => '',
											'horario_domingo' => '',
										);
									}
								}
							}
							update_option( 'cppw_points', $points, false );
							return true;
						}
					}
				}
			}
		}
		if ( $ftp_error && apply_filters( 'cppw_update_pickup_list_error_log', false ) ) {
			error_log( '[DPD Portugal Pickup WooCommerce] It was not possible to get the points update via ftp' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return false;
	}
	// phpcs:enable
}

/**
 * Format and standardize pickup point text data.
 *
 * Applies consistent capitalization and fixes common text formatting issues
 * in pickup point names and addresses received from DPD services.
 *
 * @param string $thestring The text string to format.
 * @return string Formatted and trimmed text.
 */
function cppw_fix_spot_text( $thestring ) {
	$thestring = strtolower( $thestring );
	$thestring = ucwords( $thestring );
	$org       = array( 'Ç', ' Da ', ' De ', ' Do ', 'Ii', ' E ', 'dpd' );
	$rep       = array( 'ç', ' da ', ' de ', ' do ', 'II', ' e ', 'DPD' );
	$thestring = str_ireplace( $org, $rep, $thestring );
	return trim( $thestring );
}

/**
 * Format Portuguese postcode to standard NNNN-NNN format.
 *
 * Ensures postcodes received from DPD are properly formatted with
 * zero-padding and hyphen separator.
 *
 * @param string $cp The postcode to format.
 * @return string Formatted postcode in NNNN-NNN format.
 */
function cppw_fill_postcode( $cp ) {
	$cp = trim( $cp );
	// Até 4
	if ( strlen( $cp ) < 4 ) {
		$cp = str_pad( $cp, 4, '0' );
	}
	if ( strlen( $cp ) === 4 ) {
		$cp .= '-';
	}
	if ( strlen( $cp ) < 8 ) {
		$cp = str_pad( $cp, 8, '0' );
	}
	return trim( $cp );
}

/**
 * Extract and format pickup point opening hours for specific day.
 *
 * Parses morning and afternoon opening/closing times from DPD point data
 * and formats them into a readable schedule string.
 *
 * @param object $point The DPD point object from webservice.
 * @param string $day   Day identifier ('2' for weekdays, 'S' for Saturday, 'D' for Sunday).
 * @return string Formatted schedule string or empty if closed.
 */
function cppw_get_spot_schedule( $point, $day ) {
	// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
	$morningOpenHour    = 'morningOpenHour' . $day;
	$morningCloseHour   = 'morningCloseHour' . $day;
	$afterNoonOpenHour  = 'afterNoonOpenHour' . $day;
	$afterNoonCloseHour = 'afterNoonCloseHour' . $day;
	if ( $point->{$morningOpenHour} !== '0' && $point->{$morningCloseHour} !== '0' ) {
		$horario = cppw_fix_spot_schedule( $point->{$morningOpenHour} );
		if ( $point->{$morningCloseHour} === $point->{$afterNoonOpenHour} ) { // No closing for lunch
			$horario .= '-' . cppw_fix_spot_schedule( $point->{$afterNoonCloseHour} );
		} elseif ( $point->{$afterNoonOpenHour} !== '0' && $point->{$afterNoonCloseHour} !== '0' ) {
				$horario .= '-' . cppw_fix_spot_schedule( $point->{$morningCloseHour} ) . ', ' . cppw_fix_spot_schedule( $point->{$afterNoonOpenHour} ) . '-' . cppw_fix_spot_schedule( $point->{$afterNoonCloseHour} );
		} else {
			$horario .= '-' . cppw_fix_spot_schedule( $point->{$morningCloseHour} );
		}
	} else {
		$horario = '';
	}
	return $horario;
	// phpcs:enable
}

/**
 * Format time string to HH:MM format.
 *
 * Converts time values from DPD format to standard HH:MM display format.
 *
 * @param string $thestring Time string to format.
 * @return string Formatted time in HH:MM format.
 */
function cppw_fix_spot_schedule( $thestring ) {
	$minutos = trim( substr( $thestring, -2 ) );
	$horas   = trim( substr( $thestring, 0, -2 ) );
	if ( strlen( $horas ) === 1 ) {
		$horas = '0' . $horas;
	}
	return trim( $horas . ':' . $minutos );
}

/**
 * Schedule daily cron job for updating pickup points.
 *
 * Sets up WordPress cron to automatically update the DPD pickup points
 * database once per day. Also triggers an immediate update on first run.
 *
 * @return void
 */
function cppw_cronstarter_activation() {
	if ( ! wp_next_scheduled( 'cppw_update_pickup_list' ) ) {
		// Schedule
		wp_schedule_event( time(), 'daily', 'cppw_update_pickup_list' );
		// And run now - just in case
		do_action( 'cppw_update_pickup_list' );
	}
}

/**
 * Remove scheduled cron job on plugin deactivation.
 *
 * Cleans up the scheduled pickup points update task when plugin is deactivated.
 *
 * @return void
 */
function cppw_cronstarter_deactivate() {
	// find out when the last event was scheduled
	$timestamp = wp_next_scheduled( 'cppw_update_pickup_list' );
	// unschedule previous event if any
	wp_unschedule_event( $timestamp, 'cppw_update_pickup_list' );
}

/**
 * Retrieve all DPD pickup points from database.
 *
 * Returns the complete list of pickup points. If a postcode is provided,
 * points are sorted by proximity - first by postcode similarity, then by
 * GPS distance from the nearest point in that postcode area.
 *
 * @param string $postcode Optional. Portuguese postcode to sort points by proximity.
 * @return array Array of pickup point data, sorted if postcode provided.
 */
function cppw_get_pickup_points( $postcode = '' ) {
	$points = get_option( 'cppw_points', array() );
	if ( is_array( $points ) && count( $points ) > 0 ) {
		// SORT by postcode ?
		if ( $postcode !== '' ) {
			$postcode      = cppw_fill_postcode( $postcode );
			$postcode      = intval( str_replace( '-', '', $postcode ) );
			$points_sorted = array();
			$cp_order      = array();
			// Sort by post code mathematically
			foreach ( $points as $key => $ponto ) {
					$diff                              = abs( $postcode - intval( str_replace( '-', '', $ponto['cod_postal'] ) ) );
					$points_sorted[ $key ]             = $ponto;
					$points_sorted[ $key ]['cp_order'] = $diff;
					$cp_order[ $key ]                  = $diff;
			}
			array_multisort( $cp_order, SORT_ASC, $points_sorted );
			// Now by GPS distance
			$pontos2   = array();
			$distancia = array();
			foreach ( $points_sorted as $ponto ) {
				$gps_lat = $ponto['gps_lat'];
				$gps_lon = $ponto['gps_lon'];
				break;
			}
			$i = 0;
			foreach ( $points_sorted as $key => $ponto ) {
				if ( $i === 0 ) {
					$points_sorted[ $key ]['distancia'] = 0.0;
					$distancia[ $key ]['distancia']     = 0.0;
				} else {
					$points_sorted[ $key ]['distancia'] = cppw_gps_distance( $gps_lat, $gps_lon, $ponto['gps_lat'], $ponto['gps_lon'] );
					$distancia[ $key ]['distancia']     = cppw_gps_distance( $gps_lat, $gps_lon, $ponto['gps_lat'], $ponto['gps_lon'] );
				}
				++$i;
			}
			array_multisort( $distancia, SORT_ASC, $points_sorted );
			return $points_sorted;
		} else {
			return $points;
		}
	} else {
		return array();
	}
}

/**
 * Calculate distance between two GPS coordinates.
 *
 * Uses the Haversine formula to calculate the great-circle distance
 * between two points on Earth's surface.
 *
 * @param float $lat1 Latitude of first point.
 * @param float $lon1 Longitude of first point.
 * @param float $lat2 Latitude of second point.
 * @param float $lon2 Longitude of second point.
 * @return float Distance in kilometers.
 */
function cppw_gps_distance( $lat1, $lon1, $lat2, $lon2 ) {
	$lat1  = floatval( $lat1 );
	$lon1  = floatval( $lon1 );
	$lat2  = floatval( $lat2 );
	$lon2  = floatval( $lon2 );
	$theta = $lon1 - $lon2;
	$dist  = sin( deg2rad( $lat1 ) ) * sin( deg2rad( $lat2 ) ) + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * cos( deg2rad( $theta ) );
	$dist  = acos( $dist );
	$dist  = rad2deg( $dist );
	$miles = $dist * 60 * 1.1515;
	return ( $miles * 1.609344 ); // Km
}

/**
 * Add plugin settings to WooCommerce Shipping settings page.
 *
 * Injects DPD Pickup configuration options into the WooCommerce Shipping
 * settings, including:
 * - Number of points to display
 * - Map API tokens (Mapbox/Google Maps)
 * - Email and display options
 * - Customer instructions
 *
 * @param array $settings Existing WooCommerce shipping settings.
 * @return array Modified settings with DPD Pickup options added.
 */
function cppw_woocommerce_shipping_settings( $settings ) {
	$updated_settings = array();
	foreach ( $settings as $section ) {
		if ( isset( $section['id'] ) && 'shipping_options' === $section['id'] && isset( $section['type'] ) && 'sectionend' === $section['type'] ) {
			$updated_settings[] = array(
				'title'    => __( 'DPD Pickup network in Portugal', 'portugal-chronopost-pickup-woocommerce' ),
				'desc'     => __( 'Total of points to show', 'portugal-chronopost-pickup-woocommerce' ),
				'id'       => 'cppw_total_points',
				'default'  => 50,
				'type'     => 'number',
				'autoload' => false,
				'css'      => 'width: 60px;',
			);
			$updated_settings[] = array(
				'desc'     => __( 'Near by points to show', 'portugal-chronopost-pickup-woocommerce' ),
				'id'       => 'cppw_nearby_points',
				'default'  => 10,
				'type'     => 'number',
				'autoload' => false,
				'css'      => 'width: 60px;',
			);
			$updated_settings[] = array(
				'desc'     => __( 'Do not pre-select a point in the DPD Pickup field and force the client to choose it', 'portugal-chronopost-pickup-woocommerce' ),
				'id'       => 'cppw_checkout_default_empty',
				'default'  => 0,
				'type'     => 'checkbox',
				'autoload' => false,
			);
			$updated_settings[] = array(
				'desc'     => __( 'Instructions for clients', 'portugal-chronopost-pickup-woocommerce' ),
				'id'       => 'cppw_instructions',
				'default'  => __( 'Pick up your order in one of the more than 600 DPD Pickup points available in Portugal mainland', 'portugal-chronopost-pickup-woocommerce' ),
				'desc_tip' => __( 'If you are using the mixed service, you should use this field to inform the client the Pickup point will only be used if DPD fails to deliver the order on the shipping address', 'portugal-chronopost-pickup-woocommerce' ),
				'type'     => 'textarea',
				'autoload' => false,
			);
			$updated_settings[] = array(
				'desc'     => __( 'Mapbox Public Token (recommended)', 'portugal-chronopost-pickup-woocommerce' ) . ' (<a href="https://www.mapbox.com/account/access-tokens" target="_blank">' . __( 'Get one', 'portugal-chronopost-pickup-woocommerce' ) . '</a>)',
				'desc_tip' => __( 'Go to your Mapbox account and get a Public Token, if you want to use this service static maps instead of Google Maps', 'portugal-chronopost-pickup-woocommerce' ),
				'id'       => 'cppw_mapbox_public_token',
				'default'  => '',
				'type'     => 'text',
				'autoload' => false,
				'css'      => 'min-width: 350px;',
			);
			$updated_settings[] = array(
				'desc'     => __( 'Google Maps API Key', 'portugal-chronopost-pickup-woocommerce' ) . ' (<a href="https://developers.google.com/maps/documentation/maps-static/get-api-key" target="_blank">' . __( 'Get one', 'portugal-chronopost-pickup-woocommerce' ) . '</a>)',
				'desc_tip' => __( 'Go to the Google APIs Console and create a project, then go to the Static Maps API documentation website and click on Get a key, choose your project and generate a new key (if the Mapbox public token is filled in, this will be ignored and can be left blank)', 'portugal-chronopost-pickup-woocommerce' ),
				'id'       => 'cppw_google_api_key',
				'default'  => '',
				'type'     => 'text',
				'autoload' => false,
				'css'      => 'min-width: 350px;',
			);
			$updated_settings[] = array(
				'desc'     => __( 'Add DPD Pickup point information on emails sent to the customer and order details on the "My Account" page', 'portugal-chronopost-pickup-woocommerce' ),
				'id'       => 'cppw_email_info',
				'default'  => 1,
				'type'     => 'checkbox',
				'autoload' => false,
			);
			$updated_settings[] = array(
				'desc'     => __( 'Hide shipping address on order details and emails sent to the customer', 'portugal-chronopost-pickup-woocommerce' ),
				'id'       => 'cppw_hide_shipping_address',
				'default'  => 1,
				'type'     => 'checkbox',
				'autoload' => false,
			);
			$updated_settings[] = array(
				'desc'     => __( 'Display the DPD Pickup point phone number (if available) on the checkout', 'portugal-chronopost-pickup-woocommerce' ),
				'id'       => 'cppw_display_phone',
				'default'  => 1,
				'type'     => 'checkbox',
				'autoload' => false,
			);
			$updated_settings[] = array(
				'desc'     => __( 'Display the DPD Pickup point opening/closing hours (if available) on the checkout', 'portugal-chronopost-pickup-woocommerce' ),
				'id'       => 'cppw_display_schedule',
				'default'  => 1,
				'type'     => 'checkbox',
				'autoload' => false,
			);
		}
		$updated_settings[] = $section;
	}
	return $updated_settings;
}

/**
 * Generate formatted pickup point information output.
 *
 * Creates formatted display of point details including name, address, phone,
 * and opening hours. Output format can be HTML or plain text.
 *
 * @param array $point        The pickup point data array.
 * @param bool  $plain_text   Whether to output as plain text (true) or HTML (false).
 * @param bool  $echo_html    Whether to echo output (true) or return it (false).
 * @param bool  $order_screen Whether displayed on order screen (affects formatting).
 * @return string|void HTML or text output, echoed or returned based on $echo_html parameter.
 */
function cppw_point_information( $point, $plain_text = false, $echo_html = true, $order_screen = false ) {
	ob_start();
	?>
		<p>
		<?php echo esc_html( $point['nome'] ); ?>
		<br/>
		<?php echo esc_html( $point['morada1'] ); ?>
		<br/>
		<?php echo esc_html( $point['cod_postal'] ); ?> <?php echo esc_html( $point['localidade'] ); ?>
		<?php if ( get_option( 'cppw_display_phone', 'yes' ) === 'yes' || get_option( 'cppw_display_schedule', 'yes' ) === 'yes' ) { ?>
				<small>
					<?php if ( get_option( 'cppw_display_phone', 'yes' ) === 'yes' && trim( $point['telefone'] ) !== '' ) { ?>
						<br/>
						<?php esc_html_e( 'Phone:', 'portugal-chronopost-pickup-woocommerce' ); ?> <?php echo esc_html( $point['telefone'] ); ?>
					<?php } ?>
					<?php if ( get_option( 'cppw_display_schedule', 'yes' ) === 'yes' ) { ?>
						<?php if ( trim( $point['horario_semana'] ) !== '' ) { ?>
							<br/>
							<?php esc_html_e( 'Work days:', 'portugal-chronopost-pickup-woocommerce' ); ?> <?php echo esc_html( $point['horario_semana'] ); ?>
						<?php } ?>
						<?php if ( trim( $point['horario_sabado'] ) !== '' ) { ?>
							<br/>
							<?php esc_html_e( 'Saturday:', 'portugal-chronopost-pickup-woocommerce' ); ?> <?php echo esc_html( $point['horario_sabado'] ); ?>
						<?php } ?>
						<?php if ( trim( $point['horario_domingo'] ) !== '' ) { ?>
							<br/>
							<?php esc_html_e( 'Sunday:', 'portugal-chronopost-pickup-woocommerce' ); ?> <?php echo esc_html( $point['horario_domingo'] ); ?>
						<?php } ?>
					<?php } ?>
				</small>
			<?php } ?>
		</p>
		<?php
		$html = ob_get_clean();
		if ( $plain_text ) {
			$html = wp_strip_all_tags( str_replace( "\t", '', $html ) ) . "\n";
			$html = "\n" . strtoupper( __( 'DPD Pickup point', 'portugal-chronopost-pickup-woocommerce' ) ) . "\n" . $point['number'] . "\n" . $html;
		} elseif ( ! $order_screen ) {
				$html = '<h2>' . __( 'DPD Pickup point', 'portugal-chronopost-pickup-woocommerce' ) . '</h2><p><strong>' . $point['number'] . '</strong></p>' . $html;
		}
		if ( $echo_html ) {
			// HTML validation skipped as content is escaped earlier
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			return $html;
		}
}

/**
 * Display DPD Pickup point information in order emails.
 *
 * Adds the selected pickup point details to WooCommerce order emails
 * sent to customers and administrators.
 *
 * @param WC_Order $order        The WooCommerce order object.
 * @param bool     $sent_to_admin Whether email is being sent to admin.
 * @param bool     $plain_text    Whether email is in plain text format.
 * @return void Outputs HTML/text directly.
 */
function cppw_woocommerce_email_customer_details( $order, $sent_to_admin = false, $plain_text = false ) {
	$cppw_point = $order->get_meta( 'cppw_point' );
	if ( trim( $cppw_point ) !== '' ) {
		$points = cppw_get_pickup_points();
		if ( isset( $points[ trim( $cppw_point ) ] ) ) {
			$point = $points[ trim( $cppw_point ) ];
			cppw_point_information( $point, $plain_text );
		}
	}
}

/**
 * Display DPD Pickup point information on order details page.
 *
 * Shows the selected pickup point details on the order details page
 * in customer's account area.
 *
 * @param WC_Order $order The WooCommerce order object.
 * @return void Outputs HTML directly.
 */
function cppw_woocommerce_order_details_after_order_table( $order ) {
	$cppw_point = $order->get_meta( 'cppw_point' );
	if ( trim( $cppw_point ) !== '' ) {
		$points = cppw_get_pickup_points();
		if ( isset( $points[ trim( $cppw_point ) ] ) ) {
			$point = $points[ trim( $cppw_point ) ];
			?>
				<section>
				<?php cppw_point_information( $point ); ?>
				</section>
				<?php
		}
	}
}

/**
 * Output template tag for DPD Pickup info in admin order preview.
 *
 * Adds a placeholder in the order preview modal template that will be
 * populated with pickup point information via JavaScript.
 *
 * @return void Outputs template tag directly.
 */
function cppw_woocommerce_admin_order_preview_end() {
	?>
		{{{ data.cppw_info }}}
	<?php
}

/**
 * Add DPD Pickup point data to admin order preview details.
 *
 * Injects pickup point information into the order preview modal data
 * for display in the WordPress admin orders list.
 *
 * @param array    $data  Order preview data array.
 * @param WC_Order $order The WooCommerce order object.
 * @return array Modified data with pickup point information.
 */
function cppw_woocommerce_admin_order_preview_get_order_details( $data, $order ) {
	$data['cppw_info'] = '';
	$cppw_point        = $order->get_meta( 'cppw_point' );
	if ( trim( $cppw_point ) !== '' ) {
		$points = cppw_get_pickup_points();
		if ( isset( $points[ trim( $cppw_point ) ] ) ) {
			$point = $points[ trim( $cppw_point ) ];
			ob_start();
			?>
				<div class="wc-order-preview-addresses">
					<div class="wc-order-preview-note">
					<?php cppw_point_information( $point ); ?>
					</div>
				</div>
				<?php
				$data['cppw_info'] = ob_get_clean();
		}
	}
	return $data;
}

/**
 * Determine if order needs shipping address display.
 *
 * Hides shipping address from order details and emails when a DPD Pickup
 * point is selected and the hide option is enabled.
 *
 * @param bool     $needs_address Whether address should be displayed.
 * @param bool     $hide          Hide parameter from WooCommerce.
 * @param WC_Order $order         The WooCommerce order object.
 * @return bool False if pickup point is set, original value otherwise.
 */
function cppw_woocommerce_order_needs_shipping_address( $needs_address, $hide, $order ) {
	$cppw_point = $order->get_meta( 'cppw_point' );
	if ( trim( $cppw_point ) !== '' ) {
		$needs_address = false;
	}
	return $needs_address;
}

/**
 * Customize shipping address column in admin orders list.
 *
 * Replaces standard shipping address with DPD Pickup point information
 * in the orders list table. Works with both traditional posts and HPOS.
 *
 * Note: https://github.com/woocommerce/woocommerce/issues/19258
 *
 * @param string       $column_name     The name of the column being displayed.
 * @param int|WC_Order $postid_or_order Post ID or WC_Order object.
 * @return void Outputs HTML directly.
 */
function cppw_manage_shop_order_custom_column( $column_name, $postid_or_order ) {
	if ( $column_name === 'shipping_address' ) {
		$order      = is_a( $postid_or_order, 'WC_Order' ) ? $postid_or_order : wc_get_order( $postid_or_order );
		$cppw_point = $order->get_meta( 'cppw_point' );
		if ( trim( $cppw_point ) !== '' ) {
			?>
				<style type="text/css">
					#order-<?php echo intval( $order->get_id() ); ?> .column-shipping_address a,
					#post-<?php echo intval( $order->get_id() ); ?> .column-shipping_address a {
						display: none;
					}
				</style>
				<p>
				<?php esc_html_e( 'DPD Pickup point', 'portugal-chronopost-pickup-woocommerce' ); ?> <?php echo esc_html( $cppw_point ); ?>
				<br/>
				<?php
				$points = cppw_get_pickup_points();
				if ( isset( $points[ trim( $cppw_point ) ] ) ) {
					$point = $points[ trim( $cppw_point ) ];
					echo esc_html( $point['nome'] );
					?>
					<br/>
					<?php echo esc_html( $point['morada1'] ); ?>
					<br/>
					<?php echo esc_html( $point['cod_postal'] ); ?>
					<?php
					echo esc_html( $point['localidade'] );
				} else {
					esc_html_e( 'Unable to find point on the database', 'portugal-chronopost-pickup-woocommerce' );
				}
				?>
				</p>
				<?php
		}
	}
}

/* DPD Portugal for WooCommerce nag */
add_action(
	'admin_init',
	function () {
		if (
			( ! defined( 'WEBDADOS_DPD_PRO_NAG' ) )
			&&
			( ! class_exists( 'Woo_DPD_Portugal' ) )
			&&
			empty( get_transient( 'webdados_dpd_portugal_pro_nag' ) )
			&&
			( intval( get_user_meta( get_current_user_id(), 'webdados_dpd_portugal_pro_nag_dismissed_until', true ) ) < time() )
		) {
			define( 'WEBDADOS_DPD_PRO_NAG', true );
			require_once 'pro-nag/pro-nag.php';
		}
		if (
		( ! defined( 'WEBDADOS_DPD_PICKUP_PRO_NAG' ) )
		&&
		( ! class_exists( 'Woo_DPD_Pickup' ) )
		&&
		empty( get_transient( 'webdados_dpd_pickup_pro_nag' ) )
		&&
		( intval( get_user_meta( get_current_user_id(), 'webdados_dpd_pickup_pro_nag_dismissed_until', true ) ) < time() )
		) {
			define( 'WEBDADOS_DPD_PICKUP_PRO_NAG', true );
			require_once 'pro-nag/pro-pickup-nag.php';
		}
	}
);

/* HPOS Compatible */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/* Recomment ifthenpay */
if ( ! defined( 'WEBDADOS_RECOMMEND_IFTHENPAY' ) ) {
	require_once 'recommend-ifthenpay/recommend-ifthenpay.php';
}

/* If you’re reading this you must know what you’re doing ;- ) Greetings from sunny Portugal! */
