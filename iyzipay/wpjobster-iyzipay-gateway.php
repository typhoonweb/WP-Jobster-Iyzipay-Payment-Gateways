<?php
/**
 * Plugin Name: WP Jobster Iyzipay Payment Gateways
 * Plugin URI: https://github.com/typhoonweb/WP-Jobster-Iyzipay-Payment-Gateways
 * Description: This plugin extends Jobster Theme to accept payments with Iyzipay.
 * Author: TyphoonWeb
 * Author URI: https://github.com/typhoonweb/WP-Jobster-Iyzipay-Payment-Gateways
 * Version: 2.6
 *
 * Copyright (c) 2021 WPJobster
 *
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'WPJOBSTER_SAMPLE_MIN_PHP_VER', '5.4.0' );
require_once('IyzipayBootstrap.php');
class WPJobster_Sample_Loader {
	private static $instance;
	public $priority, $unique_slug;
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public $notices = array();
	protected function __construct() {

		$this->priority = 1111; 
		$this->unique_slug = 'iyzipay';
		add_action( 'admin_init', array( $this, 'check_environment' ) );
		add_action( 'plugins_loaded', array( $this, 'init_gateways' ), 0 );
		add_filter( 'wpjobster_take_allowed_currency_' . $this->unique_slug, array( $this, 'get_gateway_currency' ) );
		add_filter( 'wpj_withdrawals_gateways_filter', function( $gateways ) { array_push( $gateways, 'iyzipay' ); return $gateways; }, 10, 1 );
		add_filter( 'wpj_enabled_withdrawals_gateways_filter', function( $gateways ) { array_push( $gateways, 'wpjobster_enable_sample_withdraw_automatic' ); return $gateways; }, 10, 1 );
		add_filter( 'wpjobster_withdraw_method', array( $this, 'withdraw_method' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

		if ( isset( $_POST[ 'wpjobster_save_' . $this->unique_slug ] ) ) {
			add_action( 'wpjobster_payment_methods_action', array( $this, 'save_gateway' ), 11 );
		}

		add_action( 'wpj_admin_orders_after_tfoot_buttons', array( $this, 'display_process_payment_request_button' ) );
		add_action( 'wpj_show_hide_automatic_withdrawal_button_filter', array( $this, 'display_admin_automatic_withdrawal_button' ), 10, 2 );

		if ( isset( $_POST['processSamplePayRequest'] ) ) {
			add_action( 'wpj_admin_orders_before_content', array( $this, 'gateway_process_payment_request_action' ), 11 );
		}

		add_action( 'wpjobster_show_withdraw_personalinfo_gateway', array( $this, 'show_payment_withdraw_personal_info' ) );
		add_action( 'wpjobster_save_withdraw_personalinfo_gateway', array( $this, 'save_payment_withdraw_personal_info' ) );
		add_action( 'wpjobster_payments_withdraw_options', array( $this, 'show_request_withdraw_payments' ), 10, 2 );
		add_action( 'wpjobster_taketo_' . $this->unique_slug . '_gateway', array( $this, 'taketogateway_function' ), 10, 2 );
		add_filter( 'wpj_payment_response_accepted_params', array( $this, 'add_gateway_param_accepted_uri_params' ) );
		add_action( 'wpjobster_processafter_' . $this->unique_slug . '_gateway', array( $this, 'processgateway_function' ), 10, 2 );

	}

	function get_gateway_currency( $currency ) {
		$currency = 'TRY';
		return $currency;
	}

	public function init_gateways() {
		load_plugin_textdomain( 'wpjobster-iyzipay', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
		add_filter( 'wpjobster_payment_gateways', array( $this, 'add_gateways' ) );
	}

	public function add_gateways( $methods ) {
		$methods[$this->priority] =
			array(
				'label'           => __( 'Iyzipay', 'wpjobster-iyzipay' ),
				'unique_id'       => $this->unique_slug,
				'action'          => 'wpjobster_taketo_' . $this->unique_slug . '_gateway',
				'response_action' => 'wpjobster_processafter_' . $this->unique_slug . '_gateway',
			);
		add_action( 'wpjobster_show_paymentgateway_forms', array( $this, 'show_gateways' ), $this->priority, 3 );

		return $methods;
	}

	public static function activation_check() {
		$environment_warning = self::get_environment_warning( true );
		if ( $environment_warning ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( $environment_warning );
		}
	}

	public function check_environment() {
		$environment_warning = self::get_environment_warning();
		if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			if ( isset( $_GET['activate'] ) ) {
				unset( $_GET['activate'] );
			}
		}
		if ( ! function_exists( 'wpj_get_wpjobster_plugins_list' ) ) {
			if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				$message = __( 'The current theme is not compatible with the plugin WPJobster Iyzipay Gateway. Activate the WPJobster theme before installing this plugin.', 'wpjobster-iyzipay' );
				$this->add_admin_notice( $this->unique_slug, 'error', $message );
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
			}
		}
	}

	static function get_environment_warning( $during_activation = false ) {
		if ( version_compare( phpversion(), WPJOBSTER_SAMPLE_MIN_PHP_VER, '<' ) ) {
			if ( $during_activation ) {
				$message = __( 'The plugin could not be activated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'wpjobster-iyzipay' );
			} else {
				$message = __( 'The Iyzipay Powered by wpjobster plugin has been deactivated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'wpjobster-iyzipay' );
			}
			return sprintf( $message, WPJOBSTER_SAMPLE_MIN_PHP_VER, phpversion() );
		}
		return false;
	}

	public function plugin_action_links( $links ) {
		$setting_link = $this->get_setting_link();
		$plugin_links = array(
			'<a href="' . $setting_link . '">' . __( 'Settings', 'wpjobster-iyzipay' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	public function get_setting_link() {
		$section_slug = $this->unique_slug;
		return admin_url( 'admin.php?page=payment-methods&active_tab=tabs' . $section_slug );
	}

	function withdraw_method( $method ) {
		if ( isset( $_POST['sample_withdraw'] ) ) {
			$method = "Iyzipay";
		}

		return $method;
	}

	public function add_admin_notice( $slug, $class, $message ) {
		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message
		);
	}

	public function admin_notices() {
		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
			echo "</p></div>";
		}
	}

	public function save_gateway() {
		if ( isset( $_POST['wpjobster_save_' . $this->unique_slug] ) ) {

			update_option( 'wpjobster_' . $this->unique_slug . '_enable', trim( $_POST['wpjobster_' . $this->unique_slug . '_enable'] ) );
			update_option( 'wpjobster_' . $this->unique_slug . '_button_caption', trim( $_POST['wpjobster_' . $this->unique_slug . '_button_caption'] ) );

			global $payment_type_enable_arr;
			foreach ( $payment_type_enable_arr as $payment_type_enable_key => $payment_type_enable ) {
				if ( $payment_type_enable_key != 'job_purchase' ) {
					if ( isset( $_POST['wpjobster_' . $this->unique_slug . '_enable_' . $payment_type_enable_key] ) ) {
						update_option( 'wpjobster_' . $this->unique_slug . '_enable_' . $payment_type_enable_key, trim( $_POST['wpjobster_' . $this->unique_slug . '_enable_' . $payment_type_enable_key] ) );
					}
				}
			}

			update_option( 'wpjobster_sample_enablesandbox', trim( $_POST['wpjobster_sample_enablesandbox'] ) );
			update_option( 'wpjobster_sample_id'           , trim( $_POST['wpjobster_sample_id'] ) );
			update_option( 'wpjobster_sample_key'          , trim( $_POST['wpjobster_sample_key'] ) );

			update_option( 'wpjobster_sample_success_page' , trim( $_POST['wpjobster_sample_success_page'] ) );
			update_option( 'wpjobster_sample_failure_page' , trim( $_POST['wpjobster_sample_failure_page'] ) );

			$enable = trim( $_POST['wpjobster_sample_withdrawal_enable'] );
			if ( $enable == 'both' ) {
				update_option( 'wpjobster_enable_sample_withdraw_automatic', 'yes' );
				update_option( 'wpjobster_enable_sample_withdraw', 'yes' );
			} elseif ( $enable == 'automatic' ) {
				update_option( 'wpjobster_enable_sample_withdraw_automatic', 'yes' );
				update_option( 'wpjobster_enable_sample_withdraw', 'no' );
			} elseif ( $enable == 'manual' ) {
				update_option( 'wpjobster_enable_sample_withdraw_automatic', 'no' );
				update_option( 'wpjobster_enable_sample_withdraw', 'yes' );
			} else {
				update_option( 'wpjobster_enable_sample_withdraw_automatic', 'no' );
				update_option( 'wpjobster_enable_sample_withdraw', 'no' );
			}

			update_option( 'wpjobster_sample_withdrawal_enable'     , trim( $_POST['wpjobster_sample_withdrawal_enable'] ) );
			update_option( 'wpjobster_sample_withdraw_enablesandbox', trim( $_POST['wpjobster_sample_withdraw_enablesandbox'] ) );
			update_option( 'wpjobster_sample_client_id'             , trim( $_POST['wpjobster_sample_client_id'] ) );
			update_option( 'wpjobster_sample_secret_key'            , trim( $_POST['wpjobster_sample_secret_key'] ) );

			echo '<div class="updated fade"><p>' . __( 'Settings saved!', 'wpjobster-iyzipay' ) . '</p></div>';
		}
	}

	public function show_gateways( $wpjobster_payment_gateways, $arr, $arr_pages ) {
		$tab_id = get_tab_id( $wpjobster_payment_gateways );

		$enable_tab_arr = array(
			'disabled'  => __( 'Disabled', 'wpjobster-iyzipay' ),
			'manual'    => __( 'Manual', 'wpjobster-iyzipay' ),
			'automatic' => __( 'Automatic', 'wpjobster-iyzipay' ),
			'both'      => __( 'Both', 'wpjobster-iyzipay' )
		); ?>

		<style>.iyzipay-automatic { display: none; }</style>

		<script>
			jQuery( document ).ready( function( $ ) {

				$( '#wpjobster_sample_withdrawal_enable' ).on( 'change rightnow', function() {
					if ( $( this ).val() == 'automatic' || $( this ).val() == 'both' ) { $( '.iyzipay-automatic' ).show(); }
					else { $( '.iyzipay-automatic' ).hide(); }
				}).triggerHandler( 'rightnow' );

			});
		</script>

		<div id="tabs<?php echo $tab_id?>">
			<form method="post" action="<?php bloginfo( 'url' ); ?>/wp-admin/admin.php?page=payment-methods&active_tab=tabs<?php echo $tab_id; ?>">

				<table width="100%" class="wpj-admin-table">

					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet(); ?></td>
						<td valign="top"><?php _e( 'Iyzipay Gateway Note:', 'wpjobster-iyzipay' ); ?></td>
						<td>
							<p><?php _e( 'Do you have any special instructions for your gateway?', 'wpjobster-iyzipay' ); ?></p>
							<p><?php _e( 'You can put them here.', 'wpjobster-iyzipay' ); ?></p>
						</td>
					</tr>

					<tr>
						<?php ?>
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Enable/Disable Iyzipay payment gateway', 'wpjobster-iyzipay' ) ); ?></td>
						<td width="200"><?php _e( 'Enable:', 'wpjobster-iyzipay' ); ?></td>
						<td><?php echo wpjobster_get_option_drop_down( $arr, 'wpjobster_' . $this->unique_slug . '_enable', 'no' ); ?></td>
					</tr>

					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Enable/Disable Iyzipay test mode.', 'wpjobster-iyzipay' ) ); ?></td>
						<td width="200"><?php _e( 'Enable Test Mode:', 'wpjobster-iyzipay' ); ?></td>
						<td><?php echo wpjobster_get_option_drop_down( $arr, 'wpjobster_' . $this->unique_slug . '_enablesandbox', 'no' ); ?></td>
					</tr>

					<?php global $payment_type_enable_arr;
					foreach ( $payment_type_enable_arr as $payment_type_enable_key => $payment_type_enable ) {
						if ( $payment_type_enable_key != 'job_purchase' ) { ?>

							<tr>
								<td valign=top width="22"><?php wpjobster_theme_bullet( $payment_type_enable['hint_label'] ); ?></td>
								<td width="200"><?php echo $payment_type_enable['enable_label']; ?></td>
								<td><?php echo wpjobster_get_option_drop_down($arr, 'wpjobster_' . $this->unique_slug . '_enable_' . $payment_type_enable_key ); ?></td>
							</tr>

						<?php }
					} ?>

					<tr>
						<?php ?>
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Put the Iyzipay button caption you want user to see on purchase page', 'wpjobster-iyzipay' ) ); ?></td>
						<td><?php _e( 'Iyzipay Button Caption:', 'wpjobster-iyzipay' ); ?></td>
						<td><input type="text" size="45" name="wpjobster_<?php echo $this->unique_slug; ?>_button_caption" value="<?php echo get_option( 'wpjobster_' . $this->unique_slug . '_button_caption' ); ?>" /></td>
					</tr>

					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Your Iyzipay Merchant ID', 'wpjobster-iyzipay' ) ); ?></td>
						<td ><?php _e( 'Iyzipay Merchant ID:', 'wpjobster-iyzipay' ); ?></td>
						<td><input type="text" size="45" name="wpjobster_sample_id" value="<?php echo get_option( 'wpjobster_sample_id' ); ?>" /></td>
					</tr>

					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Your Iyzipay Key', 'wpjobster-iyzipay' ) ); ?></td>
						<td ><?php _e( 'Iyzipay Merchant KEY:', 'wpjobster-iyzipay' ); ?></td>
						<td><input type="text" size="45" name="wpjobster_sample_key" value="<?php echo get_option( 'wpjobster_sample_key' ); ?>" /></td>
					</tr>

					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Please select a page to show when Iyzipay payment successful. If empty, it redirects to the transaction page', 'wpjobster-iyzipay' ) ); ?></td>
						<td><?php _e( 'Transaction Success Redirect:', 'wpjobster-iyzipay' ); ?></td>
						<td><?php echo wpjobster_get_option_drop_down( $arr_pages, 'wpjobster_' . $this->unique_slug . '_success_page', '', ' class="select2" ' ); ?></td>
					</tr>

					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Please select a page to show when Iyzipay payment failed. If empty, it redirects to the transaction page', 'wpjobster-iyzipay' ) ); ?></td>
						<td><?php _e( 'Transaction Failure Redirect:', 'wpjobster-iyzipay' ); ?></td>
						<td><?php echo wpjobster_get_option_drop_down( $arr_pages, 'wpjobster_' . $this->unique_slug . '_failure_page', '', ' class="select2" ' ); ?></td>
					</tr>

					<tr>
						<td></td>
						<td><h2><?php _e( "Withdrawals", "wpjobster-iyzipay" ); ?></h2></td>
						<td></td>
					</tr>

					<tr>
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Enable/Disable Iyzipay withdrawal payment gateway', 'wpjobster-iyzipay' ) ); ?></td>
						<td width="200"><?php _e( 'Enable:', 'wpjobster-iyzipay' ); ?></td>
						<td><?php echo wpjobster_get_option_drop_down( $enable_tab_arr, 'wpjobster_sample_withdrawal_enable' ); ?></td>
					</tr>

					<tr class="iyzipay-automatic">
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Enable/Disable iyzipay withdrawal test mode.', 'wpjobster-iyzipay' ) ); ?></td>
						<td width="200"><?php _e( 'Enable Test Mode:', 'wpjobster-iyzipay' ); ?></td>
						<td><?php echo wpjobster_get_option_drop_down( $arr, 'wpjobster_sample_withdraw_enablesandbox', 'no' ); ?></td>
					</tr>

					<tr class="iyzipay-automatic">
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Your Iyzipay Client ID', 'wpjobster-iyzipay' ) ); ?></td>
						<td ><?php _e( 'Iyzipay Client ID:', 'wpjobster-iyzipay' ); ?></td>
						<td><input type="text" size="45" name="wpjobster_sample_client_id" value="<?php echo apply_filters( 'wpj_sensitive_info_credentials', get_option( 'wpjobster_sample_client_id' ) ); ?>" /></td>
					</tr>

					<tr class="iyzipay-automatic">
						<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Your Iyzipay Secret key', 'wpjobster-iyzipay' ) ); ?></td>
						<td><?php _e( 'Iyzipay Secret Key:', 'wpjobster-iyzipay' ); ?></td>
						<td><input type="text" size="45" name="wpjobster_sample_secret_key" value="<?php echo apply_filters( 'wpj_sensitive_info_credentials', get_option( 'wpjobster_sample_secret_key' ) ); ?>" /></td>
					</tr>

					<tr>
						<td></td>
						<td></td>
						<td><input type="submit" name="wpjobster_save_<?php echo $this->unique_slug; ?>" value="<?php _e( 'Save Options', 'wpjobster-iyzipay' ); ?>" /></td>
					</tr>

				</table>
			</form>
		</div>

	<?php }

	public function display_admin_automatic_withdrawal_button( $default, $row ) {
		if ( strtolower( $row->methods ) == 'iyzipay' ) {
			$wpjobster_sample_api_key      = get_option( 'wpjobster_sample_api_key' );
			$wpjobster_sample_api_password = get_option( 'wpjobster_sample_api_password' );

			if ( ! empty( $wpjobster_sample_api_key ) && ! empty( $wpjobster_sample_api_password ) ) {
				return get_option( 'wpjobster_enable_sample_withdraw_automatic' ) == 'yes' ? true : false;
			} else {
				return false;
			}
		}

		return $default;
	}


	public function display_process_payment_request_button( $payment_type ) {
		$wpjobster_sample_withdrawal_enable = get_option( 'wpjobster_enable_sample_withdraw_automatic' );
		if ( $payment_type == 'withdrawal' && WPJ_Form::get( 'status', '' ) == 'pending' && $wpjobster_sample_withdrawal_enable == 'yes' ) { ?>

			<input class="button-secondary" type="submit" value="<?php echo __( 'Process Iyzipay Requests', 'wpjobster-iyzipay' ); ?>" name="processSamplePayRequest" id="processSamplePayRequest" />

			<script>
				jQuery( document ).ready( function( $ ) {

					$( 'a.mark-order-completed' ).on( 'click', function( e ) {

						if ( $( this ).hasClass( 'iyzipay-automatic-withdrawal' ) ) {
							e.preventDefault();

							$( this ).parents( 'tr' ).find( 'input[type="checkbox"]' ).prop( "checked", true );

							$( '#processSamplePayRequest' ).trigger( 'click' );
						}
					});

				});
			</script>

		<?php }
	}


	public function get_gateway_credentials() {

		$wpjobster_sample_enablesandbox = get_option( 'wpjobster_sample_enablesandbox' );

		if ( $wpjobster_sample_enablesandbox == 'on' ) {
			$sample_payment_url = 'https://sandbox-api.iyzipay.com';
		} else {
			$sample_payment_url = 'https://sandbox-api.iyzipay.com';
		}

		$merchant_key = get_option( 'wpjobster_sample_id' );
		$key = get_option( 'wpjobster_sample_key' );

		$credentials = array(
			'key'                => $key,
			'merchant_key'       => $merchant_key,
			'sample_payment_url' => $sample_payment_url,
		);

		return $credentials;
	}

	public function add_gateway_param_accepted_uri_params( $arr = array() ) {
		$arr[] = 'sample_param';
		return $arr;
	}

	public function taketogateway_function( $payment_type, $common_details ) {
		$credentials = $this->get_gateway_credentials();

		$all_data                       = array();
		$all_data['merchant_key']       = $credentials['merchant_key'];
		$all_data['key']                = $credentials['key'];
		$all_data['sample_payment_url'] = $credentials['sample_payment_url']; 

		$uid                            = $common_details['uid'];
		$wpjobster_final_payable_amount = $common_details['wpjobster_final_payable_amount'];
		$currency                       = $common_details['currency'];
		$order_id                       = $common_details['order_id'];

		$all_data['amount']       = $wpjobster_final_payable_amount;
		$all_data['currency']     = $currency;

		$payment = wpj_get_payment( array(
			'payment_type'    => $payment_type,
			'payment_type_id' => $common_details['order_id'],
		) );

		$all_data['success_url']  = get_bloginfo( 'url' ) . '/?payment_response=iyzipay&payment_type=' . $payment_type . '&wpj_payment_id=' . $payment->id;
		$all_data['fail_url']     = get_bloginfo( 'url' ) . '/?payment_response=iyzipay&action=fail&payment_type=' . $payment_type . '&wpj_payment_id=' . $payment->id;
		$all_data['firstname']    = user( $uid, 'first_name' );
		$all_data['email']        = user( $uid, 'user_email' );
		$all_data['phone']        = user( $uid, 'cell_number' );
		$all_data['lastname']     = user( $uid, 'last_name' );
		$all_data['address']      = user( $uid, 'address' );
		$all_data['city']         = user( $uid, 'city' );
		$all_data['country']      = user( $uid, 'country_name' );
		$all_data['zipcode']      = user( $uid, 'zip' );
		$all_data['order_id']     = $order_id;

		$loading_text = __( 'Loading...', 'wpjobster-iyzipay' ); ?>

		<html>

			<head></head>

			<body onload="document.getElementById( 'sampleform' ).submit();" style="">

				<div id="loader" style="display: block; position:relative; width:100%; height:100%;">
					<img style="position:absolute; left:50%; top:50%; margin-left:-50px; margin-top:-50px;" src="<?php echo get_template_directory_uri(); ?>/assets/images/ajax-loader.gif" alt="<?php echo $loading_text; ?>" />
				</div>

				<form action="<?php echo $all_data['sample_payment_url']; ?>" method="post" name="sampleform" id="sampleform" style="display: none;">

					<input type="hidden" name="key" value="<?php echo $all_data['merchant_key']; ?>" />
					<input type="hidden" name="order_id" value="<?php echo $all_data['order_id']; ?>" />
					<input type="hidden" name="amount" value="<?php echo $all_data['amount']; ?>" />
					<input type="hidden" name="currency" value="<?php echo $all_data['currency']; ?>" />

					<input type="hidden" name="success_url" value="<?php echo $all_data['success_url']; ?>" />
					<input type="hidden" name="fail_url" value="<?php echo $all_data['fail_url']; ?>" />

					<?php ?>
					<input type="hidden" name="firstname" value="<?php echo $all_data['firstname']; ?>" />
					<input type="hidden" name="email" value="<?php echo $all_data['email']; ?>" />
					<input type="hidden" name="phone" value="<?php echo $all_data['phone']; ?>" />
					<input type="hidden" name="address" value="<?php echo $all_data['address']; ?>" />
					<input type="hidden" name="city" value="<?php echo $all_data['city']; ?>" />
					<input type="hidden" name="country" value="<?php echo $all_data['country']; ?>" />
					<input type="hidden" name="zipcode" value="<?php echo $all_data['zipcode']; ?>" />

					<input type="submit" value="Pay" />

				</form>

			</body>

		</html>

		<?php exit;
	}

	function processgateway_function( $payment_type, $details ) {

		$credentials        = $this->get_gateway_credentials();
		$key                = $credentials['key'];
		$merchant_key       = $credentials['merchant_key'];
		$sample_payment_url = $credentials['sample_payment_url'];
		$status   = $_POST['status'];
		$amount   = $_POST['amount'];
		$order_id = $_POST['order_id'];
		$payment_response = $serialise = maybe_serialize( $_REQUEST );

		if ( $status == 'success' ) {

			$payment_details = "success action returned"; 
			do_action( "wpjobster_" . $payment_type . "_payment_success",
				$order_id,
				$this->unique_slug,
				$payment_details,
				$payment_response
			);

			die();

		} else {

			$payment_details = "Failed action returned"; 
			do_action( "wpjobster_" . $payment_type . "_payment_failed",
				$order_id,
				$this->unique_slug,
				$payment_details,
				$payment_response
			);

			die();

		}
	}

	public function gateway_process_payment_request_action() {

		if ( ! empty( $_POST['processSamplePayRequest'] ) ) {

			$wpjobster_sample_client_id  = get_option( 'wpjobster_sample_client_id' );
			$wpjobster_sample_secret_key = get_option( 'wpjobster_sample_secret_key' );

			if ( $wpjobster_sample_client_id != '' && $wpjobster_sample_secret_key != '' ) {

				global $wpdb;

				$tm       = current_time( 'timestamp', 1 );
				$ids      = $_POST['requests'];
				$currency = wpjobster_get_currency_classic();

				$payee_ids = array();
				$mounts = array();
				$sample_payout_requests_info = array();

				foreach ( $ids as $id ) {
					$s   = "SELECT * FROM {$wpdb->prefix}job_withdraw WHERE id = '{$id}' AND methods LIKE '%Iyzipay%' ";
					$row = $wpdb->get_results( $s );
					$row = $row[0];

					if ( ! empty($row) && $row->done == 0 ) {

						$sample_payee_id = !empty( user( $row->uid, 'sample_automatic_payee_id' ) ) ? user( $row->uid, 'sample_automatic_payee_id' ) : '';
						$payee_ids[] = $sample_payee_id;
						$mounts[] = $row->amount;
						$sample_payout_requests_info[$id]['payee_id'] = $sample_payee_id;
						$sample_payout_requests_info[$id]['amount']   = $row->amount;
						$sample_payout_requests_info[$id]['userid']   = $row->uid;
						$sample_payout_requests_info[$id]['uniqueid'] = $id;

					}
				}

				if ( ! empty( $payee_ids ) ) {

					foreach ( $sample_payout_requests_info as $sample_payout_info ) {

						$fund_transfer_status = $this->fund_transfer( $sample_payout_info['payee_id'], $sample_payout_info['amount'], $sample_payout_info['uniqueid'] );

						if ( isset( $fund_transfer_status ) && $fund_transfer_status != '' ) {
							$output_arr[$sample_payout_info['uniqueid']] = $fund_transfer_status;
						} else {
							$output_arr[$sample_payout_info['uniqueid']] = "INCOMPLETED";
						}

					}

					foreach ( $output_arr as $item_id => $output_msg ) {
						if ( $output_msg == "COMPLETED" ) {
							$id  = $item_id;
							$s   = "SELECT * FROM {$wpdb->prefix}job_withdraw WHERE id = '{$id}' AND methods LIKE '%Iyzipay%' ";
							$row = $wpdb->get_results( $s );
							$row = $row[0];

							if ( $row->done == 0 ) {
								echo '
									<div class="notice notice-success is-dismissible">
										<p>' . sprintf( __( 'Payment completed for %s!', 'wpjobster-iyzipay' ), $row->payeremail ) . '</p>
									</div>
								';

								$wpdb->query( "UPDATE {$wpdb->prefix}job_withdraw SET done='1', datedone='{$tm}' WHERE id='{$id}' AND methods LIKE '%Iyzipay%' " );

								wpj_notify_user_translated( 'withdraw_compl', $row->uid, array( '##amount_withdrawn##' => wpjobster_get_show_price_classic( $row->amount ), '##withdraw_method##' => $row->methods ), '', 'email' );

								$details = $row->methods . ': ' . $row->payeremail;
								$reason = __( 'Withdrawal to', 'wpjobster-iyzipay' ) . ' ' . $details;
								wpjobster_add_history_log( '0', $reason, $row->amount, $row->uid, '', '', 9, $details );
							}
						} else {

							echo '
								<div class="notice notice-error is-dismissible">
									<p>' . __( sprintf( "The order %s could not be processed because the seller do not have iyzipay payee id", $item_id ), 'wpjobster-iyzipay' ) . '</p>
								</div>
							';

						}
					}

				} else {

					echo '
						<div class="notice notice-error is-dismissible">
							<p>' . __( 'No Iyzipay order selected!', 'wpjobster-iyzipay' ) . '</p>
						</div>
					';

				}

			} else {

				echo '
					<div class="notice notice-error is-dismissible">
						<p>' . __( 'Iyzipay client id, secret key values are blank!', 'wpjobster-iyzipay' ) . '</p>
					</div>
				';

			}

		}

	}

	public function fund_transfer( $payee_id, $amount, $unique_id ) {

		if ( $payee_id != '' ) {
			return 'COMPLETED';
		} else {
			return 'INCOMPLETED';
		}

	}

	function show_payment_withdraw_personal_info( $uid ) {
		if ( get_option( 'wpjobster_enable_sample_withdraw' ) != "no" ) { ?>

			<div id="iyzipay-payments" class="field">
				<label><?php _e( 'Iyzipay Payment', 'wpjobster-iyzipay' ); ?></label>
				<div class="field">
					<input type="email" name="sample_payment_email" placeholder="<?php echo __( 'Iyzipay Email', 'wpjobster-iyzipay' ); ?>" value="<?php echo user( $uid, 'sample_payment_email' ); ?>" size="40" <?php if ( user( $uid, 'sample_payment_email' ) ) { echo 'readonly'; } ?> />
					<?php if ( user( $uid, 'sample_payment_email' ) ) { echo '<i class="lock icon"></i>'; } ?>
				</div>
			</div>

		<?php }

		if ( get_option( 'wpjobster_enable_sample_withdraw_automatic' ) == "yes" ) { ?>
			<div id="automated-iyzipay-payments" class="field">
				<label><?php _e( 'Automated Iyzipay Payment', 'wpjobster-iyzipay' ); ?></label>
				<div class="field">
					<input type="text" name="sample_automatic_payee_id" placeholder="<?php echo __( 'Iyzipay Payee ID', 'wpjobster-iyzipay' ); ?>" value="<?php echo user( $uid, 'sample_automatic_payee_id' ); ?>" size="40" />
				</div>
			</div>

		<?php }

	}

	function save_payment_withdraw_personal_info( $uid ) {

		if ( ! user( $uid, 'sample_payment_email' ) ) {
			update_user_meta( $uid, 'sample_payment_email', $_POST['sample_payment_email'] );
		}

		if ( ! user( $uid, 'sample_automatic_payee_id' ) ) {
			update_user_meta( $uid, 'sample_automatic_payee_id', $_POST['sample_automatic_payee_id'] );
		}

	}

	function show_request_withdraw_payments( $uid, $wpjobster_currency_position ) {
		if ( ! empty ( get_option( 'wpjobster_sample_withdrawal_enable' ) ) && get_option( 'wpjobster_sample_withdrawal_enable' ) != "disabled" ) {

			if ( get_user_meta( $uid, 'sample_payment_email', true ) || get_user_meta( $uid, 'sample_automatic_payee_id', true ) ) { ?>

				<div class="ui fitted divider"></div>

				<div class="sixteen wide column">
					<form class="ui form" method="post" enctype="application/x-www-form-urlencoded">
						<div class="field">
							<div class="three fields no-bottom-margin">
								<input type="hidden" value="<?php echo current_time( 'timestamp', 1 ) ?>" name="tm_tm" />

								<div class="eight wide field withdraw-column no-top-bottom-margin">
									<div class="withdraw-title"><?php _e( 'Iyzipay', 'wpjobster-iyzipay' ); ?></div>
								</div>

								<div class="four wide field">
									<div class="ui labeled input">
										<label class="ui label"><?php echo wpjobster_get_currency_symbol( wpjobster_get_currency() ); ?></label>
										<input class="" value="<?php if ( isset( $_POST['amount'] ) ) echo $_POST['amount']; ?>" type="text" size="10" name="amount" />
									</div>

									<?php if ( ! empty( get_user_meta( $uid, 'sample_payment_email', true ) ) ) { ?>

										<input value="<?php echo __( 'Iyzipay Email', 'wpjobster-iyzipay' ) . ': ' . get_user_meta( $uid, 'sample_payment_email', true ); ?>" type="hidden" size="30" name="details" />

									<?php } elseif ( ! empty( get_user_meta( $uid, 'sample_automatic_payee_id', true ) ) ) { ?>

										<input value="<?php echo __( 'Iyzipay Payee ID','wpjobster-iyzipay' ) . ': ' . get_user_meta( $uid, 'sample_automatic_payee_id', true ); ?>" type="hidden" size="30" name="details" />

									<?php } ?>

								</div>

								<div class="four wide field">
									<input class="withpaypal ui button secondary nomargin fluid" data-alert-message="<?php echo __( 'Error', 'wpjobster-iyzipay' ) ?>" type="submit" name="sample_withdraw" value="<?php _e( 'Withdraw', 'wpjobster-iyzipay' ); ?>" />
								</div>
							</div>
						</div>
					</form>
				</div>

			<?php } else { ?>

				<div class="ui fitted divider"></div>
				<div class="eight wide column"><?php _e( 'Iyzipay', 'wpjobster-iyzipay' ); ?></div>
				<div class="eight wide column"><?php echo sprintf( __( 'Please fill your Iyzipay details <a href="%s" class="fill-payment-color">here</a>.', 'wpjobster-iyzipay' ), get_permalink( get_option( 'wpjobster_my_account_personal_info_page_id' ) ) . 'payments/#iyzipay-payments' ); ?></div>

			<?php }
		}
	}
}

$GLOBALS['WPJobster_Sample_Loader'] = WPJobster_Sample_Loader::get_instance();
register_activation_hook( __FILE__, array( 'WPJobster_Sample_Loader', 'activation_check' ) );
