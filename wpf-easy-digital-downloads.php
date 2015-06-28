<?php
/*
Plugin Name: wpFortify for Easy Digital Downloads
Plugin URI: http://wordpress.org/plugins/wpf-easy-digital-downloads/
Description: wpFortify provides a hosted SSL checkout page for Stripe payments. A free wpFortify account is required for this plugin to work.
Version: 0.2.2
Author: wpFortify
Author URI: https://wpfortify.com
License: GPLv2+
License URI: http://www.gnu.org/licenses/gpl-2.0.html

*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if( class_exists( 'Easy_Digital_Downloads' ) ) {

	if ( ! class_exists( 'WPF_Common' ) ) {

		include_once( 'includes/class-wpf-common.php' );

	}

	class WPF_EDD extends WPF_Common {

		/**
		* Constructor
		*/
		public function __construct() {
				
			add_action( 'init', array( $this, 'textdomain' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_filter( 'edd_settings_gateways', array( $this, 'settings' ), 1 );
			add_filter( 'edd_payment_gateways', array( $this, 'register' ) );
			add_action( 'edd_gateway_wpfortify', array( $this, 'process_payment' ) );
			add_action( 'edd_wpfortify_cc_form', '__return_false' );			

			global $edd_options;
			
			if ( $edd_options['wpf_custom_checkout'] ) {
				
				$this->checkout = $edd_options['wpf_custom_checkout'];
					
			}
			$this->secret_key = $edd_options['wpf_secret_key'];
			$this->public_key = $edd_options['wpf_public_key'];
			$this->slug       = 'wpf-easy-digital-downloads';

			parent::__construct();
		
		}

		/**
		* Localisation
		*/
		public function textdomain() {

			load_plugin_textdomain( $this->slug, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		}

		public function callback_action( $response ) {

			edd_insert_payment_note( $response->metadata->order_id, sprintf( __( 'Payment completed: %s', $this->slug ) , $response->id ) );
			edd_update_payment_status( $response->metadata->order_id, 'publish' );
			
			return true;
		
		}		
		
		/**
		 * Add setting link to plugins page
		 */
		public function plugin_action_links( $links ) {
			
			$plugin_links = array(
				'<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways' ) . '">' . __( 'Settings', $this->slug ) . '</a>'
			);
			
			return array_merge( $plugin_links, $links );
		
		}		
		
		/**
		* Settings
		*/
		public function settings( $settings ) {

			$wpf_settings = array(
				array(
					'id'    => 'wpf_settings',
					'name'  => '<strong>' . __( 'wpFortify Settings', $this->slug ) . '</strong>',
					'type'  => 'header'
				),
				array(
					'id'    => 'wpf_secret_key',
					'name'  => __( 'Secret Key', $this->slug ),
					'desc'  => __( 'Enter the access keys from your wpFortify account.', $this->slug ),
					'type'  => 'text'
				),
				array(
					'id'    => 'wpf_public_key',
					'name'  => __( 'Public Key', $this->slug ),
					'desc'  => __( 'Enter the access keys from your wpFortify account.', $this->slug ),
					'type'  => 'text'
				),
				array(
					'id'    => 'wpf_custom_checkout',
					'name'  => __( 'Custom Checkout', $this->slug ),
					'desc'  => __( 'Optional: Enter the URL to your custom checkout page. Example: <code>https://example.wpfortify.com/</code>', $this->slug ),
					'type'  => 'text'
				),
				array(
					'id'    => 'wpf_checkout_image',
					'name'  => __( 'Checkout Image', $this->slug ),
					'desc'  => __( 'Optional: Enter the URL to the secure image from your wpFortify account. Example: <code>https://wpfortify.com/media/example.png</code>', $this->slug ),
					'type'  => 'text'
				),
				array(
					'id'    => 'wpf_title',
					'name'  => __( 'Checkout Title', $this->slug ),
					'desc'  => __( 'Optional: Enter a new title. Default is "', $this->slug ) . get_bloginfo() . '".' ,
					'type'  => 'text'
				),
				array(
					'id'    => 'wpf_description',
					'name'  => __( 'Checkout Description', $this->slug ),
					'desc'  => __( 'Optional: Enter a new description. Default is "Order #123 ($456)". Available filters: <code>{{order_id}} {{order_amount}}</code>. Example: <code>Order #{{order_id}} (${{order_amount}}</code>', $this->slug ),
					'type'  => 'text'
				),
				array(
					'id'    => 'wpf_button',
					'name'  => __( 'Checkout Button', $this->slug ),
					'desc'  => __( 'Optional: Enter new button text. Default is "Pay with Card". Available filters: <code>{{order_id}} {{order_amount}}</code>. Example: <code>Pay with Card (${{order_amount}})</code>', $this->slug ),
					'type'  => 'text'
				),				
			);

			return array_merge( $settings, $wpf_settings );

		}

		/**
		* Register gateway
		*/
		public function register( $gateways ) {

			$gateways['wpfortify'] = array(
				'admin_label'       => __( 'wpFortify (Stripe)', $this->slug ),
				'checkout_label'    => __( 'Credit Card', $this->slug )
			);

			return $gateways;

		}

		/**
		* Process payment
		*/
		public function process_payment( $purchase_data ) {

			global $edd_options;

			$payment_data = array(
				'price'         => $purchase_data['price'],
				'date'          => $purchase_data['date'],
				'user_email'    => $purchase_data['user_email'],
				'purchase_key'  => $purchase_data['purchase_key'],
				'currency'      => edd_get_currency(),
				'downloads'     => $purchase_data['downloads'],
				'user_info'     => $purchase_data['user_info'],
				'cart_details'  => $purchase_data['cart_details'],
				'gateway'       => 'wpfortify',
				'status'        => 'pending'
			);

			$payment = edd_insert_payment( $payment_data );

			if( $payment ) {

				$testmode    = $edd_options['test_mode'] === '1' ? true : false;
				$site_url    = get_bloginfo( 'url' );
				$site_title  = get_bloginfo();
				$description = sprintf( '%s %s ($%s)', __( 'Order #', $this->slug ), $payment, $purchase_data['price'] );
				$button      = __( 'Pay with Card', $this->slug );

				if ( $edd_options['wpf_title'] ) {
				
					$site_title = $edd_options['wpf_title'];
				
				}
				
				if ( $edd_options['wpf_description'] ) {
					
					$description = str_replace( array( '{{order_id}}', '{{order_amount}}' ), array( $payment, $purchase_data['price'] ), $edd_options['wpf_description'] );
				
				}
				
				if ( $edd_options['wpf_button'] ) {
					
					$button = str_replace( array( '{{order_id}}', '{{order_amount}}' ), array( $payment, $purchase_data['price'] ), $edd_options['wpf_button'] );
				
				}				
				
				// Data for wpFortify
				$wpf_charge = array (
					'wpf_charge' => array(
						'plugin'       => $this->slug,
						'action'       => 'charge_card',
						'site_title'   => $site_title,
						'site_url'     => $site_url,
						'listen_url'   => $site_url . '/?' . $this->slug . '=callback',
						'return_url'   => get_permalink( $edd_options['success_page'] ),
						'cancel_url'   => get_permalink( $edd_options['failure_page'] ),
						'image_url'    => $edd_options['wpf_checkout_image'],
						'customer_id'  => '',
						'card_id'      => '',
						'email'        => $purchase_data['user_email'],
						'amount'       => $purchase_data['price'],
						'description'  => $description,
						'button'       => $button,
						'currency'     => edd_get_currency(),
						'testmode'     => $testmode,
						'capture'      => true,
						'metadata'     => array(
							'order_id' => $payment
						)
					)
				);
							
				$response = $this->token( $wpf_charge );

				if ( is_wp_error( $response ) ) {

					edd_set_error( 0, $response->get_error_message( 'wpfortify_token' ) );
					edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );

				}

				if( $response ) {

					edd_empty_cart();

					wp_redirect( $response );
					exit;

				} else {
				
					edd_set_error( 0, __( 'Error redirecting to wpFortify', $this->slug ) );
					edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );				
				
				}				

			} else {

				edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );

			}

		}

	}

	new WPF_EDD();

}