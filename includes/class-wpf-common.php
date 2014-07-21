<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPF_Common class.
 */
class WPF_Common {

	protected $slug       = 'wpf-common';
	protected $checkout   = 'https://checkout.wpfortify.com';
	protected $secret_key = '';
	protected $public_key = '';

	public function __construct() {
	
		add_action( 'wp_loaded', array( $this, 'callback' ) );

	}

	public function token( $charge ) {

		$response = $this->api( 'token', $charge );

		if ( is_wp_error( $response ) ) {

			return new WP_Error( 'wpfortify_token', __( 'Error:', $this->slug ) . $response->get_error_message( 'wpfortify_api' ) );

		}

		if( $response->token ) {

			return sprintf( '%s/token/%s/', untrailingslashit( $this->checkout ), $response->token );

		} else {

			return new WP_Error( 'wpfortify_token', __( 'No token', $this->slug ) );

		}

	}

	public function repeater( $charge ) {

		$response = $this->api( 'repeater', $charge );

		if ( is_wp_error( $response ) ) {

			return new WP_Error( 'wpfortify_repeater', __( 'Error:', $this->slug ) . $response->get_error_message( 'wpfortify_api' ) );

		}

		if( $response->id ) {

			return $response;

		} else {

			return new WP_Error( 'wpfortify_repeater', __( 'Card not charged, no response from Stripe.', $this->slug ) );

		}

	}

	public function callback_action( $response ) {

		return true;

	}

	public function callback() {

		if ( isset( $_GET[ $this->slug ] ) && $_GET[ $this->slug ] == 'callback' ) {

			$response = $this->unmask( file_get_contents( 'php://input' ) );

			if ( $response->id ) {

				if ( $this->callback_action( $response ) ) {

					echo $this->mask( array( 'status' => 'order_updated' ) );
					exit;

				} else {

					echo $this->mask( array( 'error' => __( 'Card charged, but order not updated please contact support. Charge id: ', $this->slug ) . $response->id ) );
					exit;

				}

			} else {

				echo $this->mask( array( 'error' => __( 'Card not charged, no response from Stripe.', $this->slug ) ) );
				exit;

			}

		}

	}

	public function api( $endpoint, $charge ) {

		$response = wp_remote_post( sprintf( '%s/%s/%s/', 'https://api.wpfortify.com', $endpoint, $this->public_key ), array( 'body' => $this->mask( $charge ) ) );

		if ( is_wp_error( $response ) ) {

			return new WP_Error( 'wpfortify_api', __( 'Error:', $this->slug ) . $response->get_error_message() );

		}

		if ( empty( $response['body'] ) ) {

			return new WP_Error( 'wpfortify_api', __( 'Empty response from wpFortify.', $this->slug ) );

		}

		$response = $this->unmask( $response['body'] );

		if ( is_wp_error( $response ) ) {

			return new WP_Error( 'wpfortify_api', __( 'Error:', $this->slug ) . $response->get_error_message( 'wpfortify_unmask' ) );

		}

		if ( ! empty( $response->error ) ) {

			return new WP_Error( 'wpfortify_api', $response->error );

		} elseif ( empty( $response ) ) {

			return new WP_Error( 'wpfortify_api', __( 'Empty response from wpFortify endpoint: ', $this->slug ) . $endpoint );

		} else {

			return $response;

		}

	}

	public function mask( $data ) {

		$iv = mcrypt_create_iv( mcrypt_get_iv_size( MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC ), MCRYPT_RAND );
		$json_data = json_encode( $data );
		$masked = mcrypt_encrypt( MCRYPT_RIJNDAEL_128, md5( $this->secret_key ), $json_data . md5( $json_data ), MCRYPT_MODE_CBC, $iv );

		return rtrim( base64_encode( base64_encode( $iv ) . '-' . base64_encode( $masked ) ), '=' );

	}

	public function unmask( $response ) {

		list( $iv, $data_decoded ) = array_map( 'base64_decode', explode( '-', base64_decode( $response ), 2 ) );
		$unmasked = rtrim( mcrypt_decrypt( MCRYPT_RIJNDAEL_128, md5( $this->secret_key ), $data_decoded, MCRYPT_MODE_CBC, $iv ), "\0\4" );
		$hash = substr( $unmasked, -32 );
		$unmasked = substr( $unmasked, 0, -32 );

		if ( md5( $unmasked ) == $hash ) {

			return json_decode( $unmasked );

		} else {

			return new WP_Error( 'wpfortify_unmask', __( 'Invalid response from masked data.', $this->slug ) );

		}

	}

}