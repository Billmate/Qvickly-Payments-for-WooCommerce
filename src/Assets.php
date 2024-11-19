<?php
/**
 * Class Assets.
 *
 * Assets management.
 */

namespace Krokedil\Qvickly\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets.
 */
class Assets {

	const SDK_HANDLE      = 'qvickly-payments-bootstrap';
	const CHECKOUT_HANDLE = 'qvickly-payments-for-woocommerce';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueues the scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! wc_string_to_bool( Qvickly_Payments()->settings( 'enabled' ) ) ) {
			return;
		}

		// Since order received is simply considered a checkout page but with additional query variables, we must explicitly exclude it to prevent a new session from being created.
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		// The reference is stored in the session. Create the session if necessary.
		Qvickly_Payments()->session()->get_session();
		$reference  = Qvickly_Payments()->session()->get_reference();
		$session_id = Qvickly_Payments()->session()->get_payment_number();

		$standard_woo_checkout_fields = array(
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_address_2',
			'billing_postcode',
			'billing_city',
			'billing_phone',
			'billing_email',
			'billing_state',
			'billing_country',
			'billing_company',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_postcode',
			'shipping_city',
			'shipping_state',
			'shipping_country',
			'shipping_company',
			'terms',
			'terms-field',
			'_wp_http_referer',
		);

		$src          = plugins_url( 'src/assets/js/qvickly-payments.js', QVICKLY_PAYMENTS_MAIN_FILE );
		$dependencies = array( 'jquery' );
		wp_register_script( self::CHECKOUT_HANDLE, $src, $dependencies, QVICKLY_PAYMENTS_VERSION, false );

		$pay_for_order = is_wc_endpoint_url( 'order-pay' );
		wp_localize_script(
			self::CHECKOUT_HANDLE,
			'QvicklyPaymentsParams',
			array(
				'sessionId'                 => $session_id,
				'changePaymentMethodNonce'  => wp_create_nonce( 'qvickly_payments_change_payment_method' ),
				'changePaymentMethodUrl'    => \WC_AJAX::get_endpoint( 'qvickly_payments_change_payment_method' ),
				'logToFileNonce'            => wp_create_nonce( 'qvickly_payments_wc_log_js' ),
				'logToFileUrl'              => \WC_AJAX::get_endpoint( 'qvickly_payments_wc_log_js' ),
				'createOrderNonce'          => wp_create_nonce( 'qvickly_payments_create_order' ),
				'createOrderUrl'            => \WC_AJAX::get_endpoint( 'qvickly_payments_create_order' ),
				'pendingPaymentNonce'       => wp_create_nonce( 'qvickly_payments_pending_payment' ),
				'pendingPaymentUrl'         => \WC_AJAX::get_endpoint( 'qvickly_payments_pending_payment' ),
				'payForOrder'               => $pay_for_order,
				'standardWooCheckoutFields' => $standard_woo_checkout_fields,
				'submitOrderUrl'            => \WC_AJAX::get_endpoint( 'checkout' ),
				'gatewayId'                 => 'qvickly_payments',
				'reference'                 => $reference,
				'companyNumberPlacement'    => Qvickly_Payments()->settings( 'company_number_placement' ),
				'i18n'                      => array(
					'companyNumberMissing' => __( 'Please enter a company number.', 'qvickly-payments-for-woocommerce' ),
				),
			)
		);

		wp_enqueue_script( self::CHECKOUT_HANDLE );

		$env = wc_string_to_bool( Qvickly_Payments()->settings( 'test_mode' ) ) ? 'sandbox' : 'live';
		wp_enqueue_script( self::SDK_HANDLE, "https://payments.$env.qvickly.com/bootstrap.js", array( self::CHECKOUT_HANDLE ), QVICKLY_PAYMENTS_VERSION, true );
	}
}
