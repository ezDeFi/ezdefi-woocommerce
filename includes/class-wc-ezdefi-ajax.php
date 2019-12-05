<?php

defined( 'ABSPATH' ) or exit;

class WC_Ezdefi_Ajax
{
	protected $db;

    protected $api;

	/**
	 * Constructs the class
	 */
    public function __construct()
    {
    	$this->api = new WC_Ezdefi_Api();
		$this->db = new WC_Ezdefi_Db();

        $this->init();
    }

	/**
	 * Init ajax callback
	 */
    public function init()
    {
        add_action( 'wp_ajax_wc_ezdefi_get_currency', array( $this, 'wc_ezdefi_get_currency_ajax_callback' ) );
        add_action( 'wp_ajax_nopriv_wc_ezdefi_get_currency', array( $this, 'wc_ezdefi_get_currency_ajax_callback' ) );

        add_action( 'wp_ajax_wc_ezdefi_check_wallet', array( $this, 'wc_ezdefi_check_wallet_ajax_callback' ) );
        add_action( 'wp_ajax_nopriv_wc_ezdefi_check_wallet', array( $this, 'wc_ezdefi_check_wallet_ajax_callback' ) );

	    add_action( 'wp_ajax_wc_ezdefi_get_payment', array( $this, 'wc_ezdefi_get_payment_ajax_callback' ) );
	    add_action( 'wp_ajax_nopriv_wc_ezdefi_get_payment', array( $this, 'wc_ezdefi_get_payment_ajax_callback' ) );

	    add_action( 'wp_ajax_wc_ezdefi_create_payment', array( $this, 'wc_ezdefi_create_payment_ajax_callback' ) );
	    add_action( 'wp_ajax_nopriv_wc_ezdefi_create_payment', array( $this, 'wc_ezdefi_create_payment_ajax_callback' ) );

	    add_action( 'wp_ajax_wc_ezdefi_check_order_status', array( $this, 'wc_ezdefi_check_order_status_ajax_callback' ) );
	    add_action( 'wp_ajax_nopriv_wc_ezdefi_check_order_status', array( $this, 'wc_ezdefi_check_order_status_ajax_callback' ) );

	    add_action( 'wp_ajax_wc_ezdefi_assign_amount_id', array( $this, 'wc_ezdefi_assign_amount_id_ajax_callback' ) );
	    add_action( 'wp_ajax_nopriv_wc_ezdefi_assign_amount_id', array( $this, 'wc_ezdefi_assign_amount_id_ajax_callback' ) );

	    add_action( 'wp_ajax_wc_ezdefi_delete_amount_id', array( $this, 'wc_ezdefi_delete_amount_id_ajax_callback' ) );
	    add_action( 'wp_ajax_nopriv_wc_ezdefi_delete_amount_id', array( $this, 'wc_ezdefi_delete_amount_id_ajax_callback' ) );
    }

    /**
     * Get currency ajax callback
     */
    public function wc_ezdefi_get_currency_ajax_callback()
    {
    	$keyword = $_POST['keyword'];
	    $api_url = $_POST['api_url'];

	    $api = new WC_Ezdefi_Api( $api_url );

	    $response = $api->get_list_currency( $keyword );

        if( is_wp_error( $response ) ) {
            wp_send_json_error( __( 'Can not get currency', 'woocommerce-gateway-ezdefi' ) );
        }

	    $response = json_decode( $response['body'], true );

        $currency = $response['data'];

        wp_send_json_success( $currency );
    }

    /**
     * Check wallet address ajax callback
     */
    public function wc_ezdefi_check_wallet_ajax_callback()
    {
	    if( ! isset( $_POST['address'] ) || ! isset( $_POST['api_url'] ) || ! isset( $_POST['api_key'] ) ) {
		    wp_die( 'false' );
	    }

	    $address = $_POST['address'];
        $api_url = $_POST['api_url'];
        $api_key = $_POST['api_key'];
	    $currency_chain = strtolower( $_POST['currency_chain'] );

        $api = new WC_Ezdefi_Api( $api_url, $api_key );

	    $response = $api->get_list_wallet();

	    if( is_wp_error( $response ) ) {
		    wp_die( 'false' );
	    }

        $response = json_decode( $response['body'], true );

        $list_wallet = $response['data'];

	    $key = array_search( strtolower( $address ), array_column( $list_wallet, 'address' ) );

	    if( $key === false ) {
		    wp_die( 'false' );
	    }

	    $wallet = $list_wallet[$key];

	    $status = strtolower( $wallet['status'] );

	    $wallet_type = strtolower( $wallet['walletType'] );

	    if( $status === 'active' && $wallet_type === $currency_chain ) {
		    wp_die( 'true' );
	    } else {
		    wp_die( 'false' );
	    }
    }

	/**
	 * Get EZDefi payment ajax callback
	 */
	public function wc_ezdefi_get_payment_ajax_callback()
	{
		$data = $this->validate_post_data( $_POST, __( 'Can not create payment', 'woocommerce-gateway-ezdefi' ) );

		$order = $data['order'];

		$ezdefi_payment = ( $data['order']->get_meta( 'ezdefi_payment' ) ) ? $order->get_meta( 'ezdefi_payment' ) : array();

		$method = $data['method'];

		if( array_key_exists( $method, $ezdefi_payment ) && $ezdefi_payment[$method] !== '' ) {
			$paymentid = $ezdefi_payment[$method];
			return $this->get_ezdefi_payment( $paymentid );
		}

		$symbol = $_POST['symbol'];

		return $this->create_ezdefi_payment( $order, $symbol, $method );
	}

    /**
     * Create ezDeFi payment ajax callback
     */
    public function wc_ezdefi_create_payment_ajax_callback()
    {
	    $data = $this->validate_post_data( $_POST, __( 'Can not create payment', 'woocommerce-gateway-ezdefi' ) );

	    $symbol = $_POST['symbol'];

	    return $this->create_ezdefi_payment( $data['order'], $symbol, $data['method'], true );
    }

	/**
	 * Validate post data before creating ezDeFi Payment
	 *
	 * @param array $data
	 * @param string $message
	 *
	 * @return array
	 */
	private function validate_post_data( $data, $message = '' )
	{
		if( ! isset( $data['uoid'] ) || ! isset( $data['symbol'] ) || ! isset( $data['method'] ) ) {
			wp_send_json_error( $message );
		}

		$uoid = $_POST['uoid'];

		$data = array();

		$data['order'] = $this->get_order( $uoid, __( 'Can not create payment', 'woocommerce-gateway-ezdefi' ) );

		$data['method'] = $this->validate_payment_method( $_POST['method'], __( 'Can not create payment', 'woocommerce-gateway-ezdefi' ) );

		return $data;
	}

	/**
	 * Validate payment method
	 *
	 * @param string $method
	 * @param string $message
	 *
	 * @return mixed
	 */
	private function validate_payment_method( $method, $message )
	{
		$accepted_method = $this->db->get_option( 'payment_method' );

		if( ! array_key_exists( $method, $accepted_method ) ){
			wp_send_json_error( $message );
		}

		return $method;
	}

	/**
	 * Get WC Order or Send JSON error
	 *
	 * @param int $uoid
	 * @param string $message
	 *
	 * @return bool|WC_Order|WC_Order_Refund
	 */
	private function get_order( $uoid, $message )
	{
		$order = wc_get_order( $uoid );

		if( ! $order ) {
			wp_send_json_error( $message );
		}

		return $order;
	}

	/**
	 * Get ezDeFi payment then render HTML
	 *
	 * @param int $paymentid
	 */
	private function get_ezdefi_payment( $paymentid )
	{
		$response = $this->api->get_ezdefi_payment( $paymentid );

		if( is_wp_error( $response ) ) {
			wp_send_json_error( __( 'Can not get payment', 'woocommerce-gateway-ezdefi' ) );
		}

		$response = json_decode( $response['body'], true );

		$ezdefi_payment = $response['data'];

		$uoid = substr( $ezdefi_payment['uoid'], 0, strpos( $ezdefi_payment['uoid'],'-' ) );

		$order = wc_get_order( $uoid );

		if( ! $order ) {
			wp_send_json_error( __( 'Can not get payment', 'woocommerce-gateway-ezdefi' ) );
        }

		$html = $this->generate_payment_html( $ezdefi_payment, $order );

		wp_send_json_success( $html );
	}

	/**
	 * Create ezDeFi payment then render HTML
	 *
	 * @param array $order
	 * @param string $symbol
	 * @param string $method
	 * @param bool $clear_meta_data
	 */
    private function create_ezdefi_payment( $order, $symbol, $method, $clear_meta_data = false )
    {
    	$currency_data = $this->db->get_currency_option( $symbol );

    	if( ! $currency_data ) {
		    wp_send_json_error( __( 'Can not create payment', 'woocommerce-gateway-ezdefi' ) );
	    }

	    $amount_id = ( $method === 'amount_id' ) ? true : false;

	    $response = $this->api->create_ezdefi_payment( $order, $currency_data, $amount_id );

	    if( is_wp_error( $response ) ) {
	    	$error_message = $response->get_error_message( 'create_ezdefi_payment' );
		    $order->add_order_note( $error_message );
		    wp_send_json_error( $error_message );
	    }

	    $response = json_decode( $response['body'], true );

	    $payment = $response['data'];

	    $html = $this->generate_payment_html( $payment, $order );

	    if( $clear_meta_data ) {
		    $ezdefi_payment = array();
	    } else {
		    $ezdefi_payment = ( $order->get_meta( 'ezdefi_payment' ) ) ? $order->get_meta( 'ezdefi_payment' ) : array();
	    }

	    $ezdefi_payment[$method] = $payment['_id'];

	    $order->update_meta_data( 'ezdefi_payment', $ezdefi_payment );
	    $order->update_meta_data( 'ezdefi_currency', $symbol );
	    $order->update_meta_data( 'ezdefi_amount_id', $payment['originValue'] );
	    $order->save_meta_data();

	    wp_send_json_success( $html );
    }

	/**
	 * Generate Payment HTML
	 *
	 * @param array $payment
	 *
	 * @return false|string
	 */
    public function generate_payment_html( $payment, $order ) {
        $total = $order->get_total();
        $discount = $this->db->get_currency_option( $payment['currency'] )['discount'];
        $total = $total - ( $total * ( $discount / 100 ) );
	    ob_start(); ?>
	    <div class="ezdefi-payment" data-paymentid="<?php echo $payment['_id']; ?>">
		    <?php if( ! $payment ) : ?>
			    <span><?php echo __( 'Can not get payment', 'woocommerce-gateway-ezdefi' ); ?></span>
		    <?php else : ?>
			    <?php $value = $payment['value'] / pow( 10, $payment['decimal'] ); ?>
			    <p class="exchange">
				    <span><?php echo $order->get_currency(); ?> <?php echo $total; ?></span>
				    <img width="16" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAQAAAAAYLlVAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QAAKqNIzIAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAAHdElNRQfjChgQMyxZjA7+AAACP0lEQVRo3u2YvWsUQRTAf8nFQs5LCEY0aCGIB1ErRVMoFpYGTGNlo2AnBxHlrLQJKVSwiV//gqCV4gemEGJhiBYXRAtBDIhICiUGL8GP3Fjs7rs5vN0o5M1LsW+a2XkDv9/MvF12t4B2dDDODqbVOan46zgaVKzwN3A4O4VuarGAo8EZC4VeXnoKJruQK+QKa12hI2VyFyUFhY08Ymfcd1S49feU7VSZ5DPL4qrXGpxuhW/iJj8DgJutTrGJ38vHoPCobUnwg9QN8HeTItzGNP2yF7M85D11lTvhLAPSn2CYpah7R5zmOUmnChrgsrf6p6xPhvfRiAe/slsNnoqHcRketsDDbDw8ZYPvlsR5CzwMSGpICT+WhYdBSR4Ov3p9gbGV8Hr3PEAPx6XvPXZC7sBm3qSvPoRApJCB71KB+jHHERbab34YAZjLSuoW4T+EuYBNHJXC32W+A2taYAN9lgJFHjDZfGsNHUWe4XC8VVHwirD9hBLPZcpM+mN0NQTaHUGR+xySq3vpj1Gd8FfvuKjCyDiC5OyjdklpkSeE0N+aCLF6gNGY8IuCBb4zfklxzFjg4ZRQRi3wB/guB1AOjV9HhUXh3Ibo87zEYw7KpFqUWPUoUWaIrXL9gf18iRSeGPyamGdPYlI2wL/zflPQx4+g8CWu0tN6OiNBwL/5xAQjXhWQFCFc4IqMvOYY3xSKcIHlrPQ5z/UVvSr3wQqRK+QKuYIfVU9hSuGt+L924ZoFvqmgji+kZl6wSI2qtsAfm/EoPAbFFD0AAAAldEVYdGRhdGU6Y3JlYXRlADIwMTktMTAtMjRUMTY6NTE6NDQrMDA6MDBiAik3AAAAJXRFWHRkYXRlOm1vZGlmeQAyMDE5LTEwLTI0VDE2OjUxOjQ0KzAwOjAwE1+RiwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAAASUVORK5CYII=" />
				    <span class="currency"><?php echo $value . ' ' . $payment['currency']; ?></span>
			    </p>
			    <p><?php echo __( 'You have', 'woocommerce-gateway-ezdefi' ); ?> <span class="count-down" data-endtime="<?php echo $payment['expiredTime']; ?>"></span> <?php echo __( 'to scan this QR Code', 'woocommerce-gateway-ezdefi' ); ?></p>
			    <p>
				    <a href="<?php echo $payment['deepLink']; ?>">
					    <img class="qrcode" src="<?php echo $payment['qr']; ?>" />
				    </a>
			    </p>
			    <?php if( $payment['amountId'] === true ) : ?>
                    <p>
                        <strong><?php _e( 'Pay Manually', 'edd-ezdefi' ); ?>:</strong> <?php echo $payment['chain']['name'] . ' ' . $payment['chain']['symbol']; ?><br/>
                        <strong><?php _e( 'Address', 'edd-ezdefi' ); ?>:</strong> <?php echo $payment['to']; ?><br/>
                        <strong><?php _e( 'Amount', 'edd-ezdefi' ); ?>:</strong> <?php echo $payment['originValue']; ?><br/>
                    </p>
                    <p>
					    <?php _e( 'You have to pay exact amount so that your order can be handle property.', 'edd-ezdefi' ); ?><br/>
					    <?php _e( 'If you have difficulty for sending exact amount, try to use', 'edd-ezdefi' ); ?> <a href="" class="ezdefiEnableBtn">ezDeFi Wallet</a>
                    </p>
			    <?php else : ?>
				    <p>
					    <a href=""><?php echo __( 'Download ezDeFi for IOS', 'woocommerce-gateway-ezdefi' ); ?></a><br />
					    <a href=""><?php echo __( 'Download ezDeFi for Android', 'woocommerce-gateway-ezdefi' ); ?></a>
				    </p>
			    <?php endif; ?>
		    <?php endif; ?>
	    </div>
	    <?php return ob_get_clean();
    }

    /**
     * Check order status ajax callback
     */
    public function wc_ezdefi_check_order_status_ajax_callback()
    {
    	$order_id = $_POST['order_id'];
    	$order = wc_get_order( $order_id );
    	$status = $order->get_status();

    	wp_die( $status );
    }

    public function wc_ezdefi_assign_amount_id_ajax_callback()
    {
        if( ! isset( $_POST['amount_id'] ) || ! isset( $_POST['order_id'] ) ) {
            wp_send_json_error();
        }

        $amount_id = $_POST['amount_id'];

        $order_id = $_POST['order_id'];

	    $order = wc_get_order( $order_id );

	    if( ! $order ) {
		    wp_send_json_error();
	    }

        $this->delete_amount_id_exception( $amount_id );

	    $order->update_status( 'completed' );

	    wp_send_json_success();
    }

    public function wc_ezdefi_delete_amount_id_ajax_callback()
    {
	    $amount_id = $_POST['amount_id'];

	    $this->delete_amount_id_exception( $amount_id );
    }

    protected function delete_amount_id_exception($amount_id)
    {
	    global $wpdb;

	    $table_name = $wpdb->prefix . 'woocommerce_ezdefi_exception';

	    $wpdb->delete( $table_name, array( 'amount_id' => $amount_id ) );
    }
}

new WC_Ezdefi_Ajax();