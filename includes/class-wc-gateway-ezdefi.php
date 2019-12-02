<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    return;
}

/**
 * Class WC_Gateway_Ezdefi
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Ezdefi extends WC_Payment_Gateway
{
    public $api_url;

    public $api_key;

    public $currency;

    public $payment_method;

    public $api;

    public $db;

	/**
	 * Constructs the class
	 */
    public function __construct()
    {
        $this->set_general_property();

        $this->init_form_fields();

        $this->init_settings();

        $this->set_settings_value();

        $this->api = new WC_Ezdefi_Api( $this->api_url, $this->api_key );

        $this->db = new WC_Ezdefi_Db();

        $this->init_hooks();

//        $order = wc_get_order( 1249 );
//        $currency_data = $this->db->get_currency_option( 'nusd' );
//        var_dump( $this->api->create_ezdefi_payment( $order, $currency_data, true ) );
//        wp_die();
    }

	/**
	 * Set general property of class
	 */
    protected function set_general_property()
    {
	    $this->id = 'ezdefi';
	    $this->method_title = __( 'Ezdefi', 'woocommerce-gateway-ezdefi' );
	    $this->method_description = __( 'Payment Without Middleman', 'woocommerce-gateway-ezdefi' );
	    $this->has_fields = true;
    }

	/**
	 * Set settings value
	 */
    protected function set_settings_value()
    {
	    $this->enabled = $this->get_option( 'enabled' );
	    $this->title = $this->get_option( 'title' );
	    $this->description = $this->get_option( 'description' );
	    $this->api_url = $this->get_option( 'api_url' );
	    $this->api_key = $this->get_option( 'api_key' );
	    $this->currency = $this->get_option( 'currency' );
	    $this->payment_method = $this->get_option( 'payment_method' );

	    $amount_clear_recurrence = $this->get_option('amount_clear_recurrence');
	    $this->amount_clear_recurrence = ( $amount_clear_recurrence != '' ) ? $amount_clear_recurrence : 'daily';
    }

	/**
	 * Init hooks
	 */
    public function init_hooks()
    {
        global $woocommerce;

        if( is_object( $woocommerce ) && version_compare( $woocommerce->version, '3.7.0', '>=' ) ) {
	        add_action( 'woocommerce_before_thankyou', array(
		        $this, 'qrcode_section'
	        ) );
        } else {
	        add_filter( 'do_shortcode_tag', array(
                $this, 'prepend_woocommerce_checkout_shortcode'
            ), 10, 4 );
        }

	    add_action( 'wp_enqueue_scripts', array(
            $this, 'payment_scripts'
        ) );

        add_action( 'admin_enqueue_scripts', array(
            $this, 'admin_scripts'
        ) );

        add_action( 'woocommerce_api_' . $this->id, array(
            $this, 'gateway_callback_handle'
        ) );
    }

	/**
	 * Register needed scripts for admin
	 */
    public function admin_scripts()
    {
        if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
            return;
        }

        wp_register_script( 'wc_ezdefi_tiptip', plugins_url( 'assets/js/jquery.tipTip.js', WC_EZDEFI_MAIN_FILE ), array( 'jquery' ), WC_EZDEFI_VERSION, true );
        wp_register_script( 'wc_ezdefi_validate', plugins_url( 'assets/js/jquery.validate.min.js', WC_EZDEFI_MAIN_FILE ), array( 'jquery' ), WC_EZDEFI_VERSION, true );
        wp_register_style( 'wc_ezdefi_select2', plugins_url( 'assets/css/select2.min.css', WC_EZDEFI_MAIN_FILE ) );
        wp_register_script( 'wc_ezdefi_select2', plugins_url( 'assets/js/select2.min.js', WC_EZDEFI_MAIN_FILE ), array( 'jquery' ), WC_EZDEFI_VERSION, true );
        wp_register_style( 'wc_ezdefi_admin', plugins_url( 'assets/css/ezdefi-admin.css', WC_EZDEFI_MAIN_FILE ) );
        wp_register_script( 'wc_ezdefi_admin', plugins_url( 'assets/js/ezdefi-admin.js', WC_EZDEFI_MAIN_FILE ), array( 'jquery' ), WC_EZDEFI_VERSION, true );
    }

	/**
	 * Init plugin setting fields
	 */
    public function init_form_fields()
    {
        $this->form_fields = require dirname( __FILE__ ) . '/admin/ezdefi-settings.php';
    }

	/**
	 * Add needed scripts for admin
	 */
    public function generate_settings_html( $form_fields = array(), $echo = true )
    {
        wp_enqueue_script( 'wc_ezdefi_tiptip', plugins_url( 'assets/js/jquery.tipTip.js', WC_EZDEFI_MAIN_FILE ), array( 'jquery' ), WC_EZDEFI_VERSION, true );
        wp_enqueue_script( 'wc_ezdefi_validate', plugins_url( 'assets/js/jquery.validate.min.js', WC_EZDEFI_MAIN_FILE ), array( 'jquery' ), WC_EZDEFI_VERSION, true );
        wp_enqueue_style( 'wc_ezdefi_select2', plugins_url( 'assets/css/select2.min.css', WC_EZDEFI_MAIN_FILE ) );
        wp_enqueue_script( 'wc_ezdefi_select2', plugins_url( 'assets/js/select2.min.js', WC_EZDEFI_MAIN_FILE ), array( 'jquery' ), WC_EZDEFI_VERSION, true );
        wp_enqueue_style( 'wc_ezdefi_admin', plugins_url( 'assets/css/ezdefi-admin.css', WC_EZDEFI_MAIN_FILE ) );
        wp_enqueue_script( 'wc_ezdefi_admin', plugins_url( 'assets/js/ezdefi-admin.js', WC_EZDEFI_MAIN_FILE ), array( 'jquery' ), WC_EZDEFI_VERSION, true );
        wp_localize_script( 'wc_ezdefi_admin', 'wc_ezdefi_data',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' )
            )
        );

        return parent::generate_settings_html($form_fields, $echo);
    }

	/**
     * Genereate HTMl for payment method setting field
     *
	 * @param $key
	 * @param $data
	 *
	 * @return false|string
	 */
    public function generate_method_settings_html( $key, $data )
    {
	    $field_key = $this->get_field_key( $key );

	    ob_start();
	    ?>
	    <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <input name="<?php echo $field_key; ?>[amount_id]" id="<?php echo $field_key; ?>[amount_id]" type="checkbox" class="" value="1" <?php echo ( isset( $this->payment_method['amount_id'] ) && $this->payment_method['amount_id'] === '1' ) ? 'checked' : '' ;?>>
                    <label for="<?php echo $field_key; ?>[amount_id]"><?php echo __( 'Simple method', 'woocommerce-gateway-ezdefi' ); ?></label>
                    <p class="description"><?php echo __( 'Allow client to pay without using ezDeFi wallet', 'woocommerce-gateway-ezdefi' ); ?></p>
                </fieldset>
                <fieldset>
                    <input name="<?php echo $field_key; ?>[ezdefi_wallet]" id="<?php echo $field_key; ?>[ezdefi_wallet]" type="checkbox" class="" value="1" <?php echo ( isset( $this->payment_method['ezdefi_wallet'] ) && $this->payment_method['ezdefi_wallet'] === '1' ) ? 'checked' : '' ;?>>
                    <label for="<?php echo $field_key; ?>[ezdefi_wallet]"><?php echo __( 'Pay with Ezdefi wallet', 'woocommerce-gateway-ezdefi' ); ?></label>
                    <p class="description"><?php echo __( 'Allow client to pay using ezDeFi wallet', 'woocommerce-gateway-ezdefi' ); ?></p>
                </fieldset>
            </td>
        </tr>
	    <?php

	    return ob_get_clean();
    }

	/**
     * Validate payment method setting field
     *
	 * @param $key
	 * @param $value
	 *
	 * @return array|string
	 */
    public function validate_method_settings_field( $key, $value ) {
	    if( ! is_array( $value ) ) {
		    return '';
	    }

	    foreach( $value as $i => $v ) {
		    $value[$i] = $v;
	    }

	    return $value;
    }

	/**
     * Genereate HTMl for currency setting field
     *
	 * @param $key
	 * @param $data
	 *
	 * @return false|string
	 */
    public function generate_currency_settings_html( $key, $data )
    {
        $field_key = $this->get_field_key( $key );

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
            </th>
            <td class="forminp">
                <table id="wc-ezdefi-currency-settings-table" class="widefat striped">
                    <thead>
                        <tr>
                            <th scope="col" class="sortable-zone">
                                <span class="dashicons dashicons-editor-help help-tip"></span>
                            </th>
                            <th scope="col" class="logo"></th>
                            <th scope="col" class="name"><?php echo __( 'Name', 'woocommerce-gateway-ezdefi' ); ?></th>
                            <th scope="col" class="discount"><?php echo __( 'Discount', 'woocommerce-gateway-ezdefi' ); ?></th>
                            <th scope="col" class="lifetime"><?php echo __( 'Payment Lifetime', 'woocommerce-gateway-ezdefi' ); ?></th>
                            <th scope="col" class="wallet"><?php echo __( 'Wallet Address', 'woocommerce-gateway-ezdefi' ); ?></th>
                            <th scope="col" class="block-confirm"><?php echo __( 'Block Confirmation', 'woocommerce-gateway-ezdefi' ); ?></th>
                            <th scope="col" class="decimal"><?php echo __( 'Decimal', 'woocommerce-gateway-ezdefi' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if( ! empty( $this->currency ) && is_array( $this->currency ) ) : ?>
                            <?php foreach( $this->currency as $index => $c ) : ?>
                                <tr>
                                    <td class="sortable-handle">
                                        <span class="dashicons dashicons-menu"></span>
                                    </td>
                                    <td class="logo">
                                        <img src="<?php echo isset( $c['logo'] ) ? $c['logo'] : '' ;?>" class="ezdefi-currency-logo" alt="">
                                    </td>
                                    <td class="name">
                                        <input class="currency-symbol" type="hidden" value="<?php echo isset( $c['symbol'] ) ? $c['symbol'] : '' ;?>" name="<?php echo $field_key . '[' . $index . '][symbol]'; ?>]">
                                        <input class="currency-name" type="hidden" value="<?php echo isset( $c['name'] ) ? $c['name'] : '' ;?>" name="<?php echo $field_key . '[' . $index . '][name]'; ?>]">
                                        <input class="currency-logo" type="hidden" value="<?php echo isset( $c['logo'] ) ? $c['logo'] : '' ;?>" name="<?php echo $field_key . '[' . $index . '][logo]'; ?>">
                                        <input class="currency-desc" type="hidden" value="<?php echo isset( $c['desc'] ) ? $c['desc'] : '' ;?>" name="<?php echo $field_key . '[' . $index . '][desc]'; ?>">
                                        <input class="currency-chain" type="hidden" value="<?php echo ( isset( $c['chain'] ) ) ? $c['chain'] : ''; ?>" name="<?php echo $field_key . '[' . $index . '][chain]'; ?>">
                                        <div class="view">
                                            <span><?php echo isset( $c['name'] ) ? $c['name'] : '' ;?></span>
                                            <div class="actions">
                                                <a href="" class="editBtn"><?php echo __( 'Edit', 'woocommerce-gateway-ezdefi' ); ?></a>
                                                |
                                                <a href="" class="deleteBtn"><?php echo __( 'Delete', 'woocommerce-gateway-ezdefi' ); ?></a>
                                            </div>
                                        </div>
                                        <div class="edit">
                                            <select name="<?php echo $field_key . '[' . $index . '][select]'; ?>" class="select-select2">
                                                <?php if( isset( $c['symbol'] ) ) : ?>
                                                    <option value="<?php echo $c['symbol'];?>" selected="selected"><?php echo $c['symbol'];?></option>
                                                <?php endif; ?>
                                            </select>
                                            <div class="actions">
                                                <a href="" class="cancelBtn"><?php echo __( 'Cancel', 'woocommerce-gateway-ezdefi' ); ?></a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="discount">
                                        <div class="view">
                                            <?php echo isset( $c['discount'] ) ? $c['discount'] . '%' : '' ;?>
                                        </div>
                                        <div class="edit">
                                            <input type="number" name="<?php echo $field_key . '[' . $index . '][discount]'; ?>" value="<?php echo isset( $c['discount'] ) ? $c['discount'] : '' ;?>"><span> %</span>
                                        </div>
                                    </td>
                                    <td class="lifetime">
                                        <div class="view">
                                            <?php echo isset( $c['lifetime'] ) ? $c['lifetime'] : '' ;?>
                                        </div>
                                        <div class="edit">
                                            <input type="number" name="<?php echo $field_key . '[' . $index . '][lifetime]'; ?>" value="<?php echo isset( $c['lifetime'] ) ? $c['lifetime'] : '' ;?>">
                                        </div>
                                    </td>
                                    <td class="wallet">
                                        <div class="view">
                                            <?php echo isset( $c['wallet'] ) ? $c['wallet'] : '' ;?>
                                        </div>
                                        <div class="edit">
                                            <input type="text" class="currency-wallet" name="<?php echo $field_key . '[' . $index . '][wallet]'; ?>" value="<?php echo isset( $c['wallet'] ) ? $c['wallet'] : '' ;?>">
                                        </div>
                                    </td>
                                    <td class="block_confirm">
                                        <div class="view">
                                            <?php echo isset( $c['block_confirm'] ) ? $c['block_confirm'] : '' ;?>
                                        </div>
                                        <div class="edit">
                                            <input type="number" name="<?php echo $field_key . '[' . $index . '][block_confirm]'; ?>" value="<?php echo isset( $c['block_confirm'] ) ? $c['block_confirm'] : '' ;?>">
                                        </div>
                                    </td>
                                    <td class="decimal">
                                        <div class="view">
			                                <?php echo isset( $c['decimal'] ) ? $c['decimal'] : '' ;?>
                                        </div>
                                        <div class="edit">
                                            <input type="number" name="<?php echo $field_key . '[' . $index . '][decimal]'; ?>" value="<?php echo isset( $c['decimal'] ) ? $c['decimal'] : '' ;?>">
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr class="editing">
                                <td class="sortable-handle">
                                    <span class="dashicons dashicons-menu"></span>
                                </td>
                                <td class="logo">
                                    <img src="https://s2.coinmarketcap.com/static/img/coins/64x64/2714.png" class="ezdefi-currency-logo" alt="">
                                </td>
                                <td class="name">
                                    <input class="currency-symbol" type="hidden" value="nusd" name="<?php echo $field_key . '[0][symbol]'; ?>]">
                                    <input class="currency-name" type="hidden" value="nusd" name="<?php echo $field_key . '[0][name]'; ?>]">
                                    <input class="currency-logo" type="hidden" value="https://s2.coinmarketcap.com/static/img/coins/64x64/2714.png" name="<?php echo $field_key . '[0][logo]'; ?>">
                                    <input class="currency-desc" type="hidden" value="NewSD - Stablecoin token for payment" name="<?php echo $field_key . '[0][desc]'; ?>">
                                    <input class="currency-chain" type="hidden" value="eth" name="<?php echo $field_key . '[0][chain]'; ?>">
                                    <div class="view">
                                        <span>Nusd</span>
                                        <div class="actions">
                                            <a href="" class="editBtn">Edit</a>
                                            |
                                            <a href="" class="deleteBtn">Delete</a>
                                        </div>
                                    </div>
                                    <div class="edit">
                                        <select name="<?php echo $field_key . '[0][select]'; ?>" class="select-select2">
                                            <option value="nusd" selected="selected">nusd</option>
                                        </select>
                                        <div class="actions">
                                            <a href="" class="cancelBtn">Cancel</a>
                                        </div>
                                    </div>
                                </td>
                                <td class="discount">
                                    <div class="view">
                                    </div>
                                    <div class="edit">
                                        <input type="number" name="<?php echo $field_key . '[0][discount]'; ?>" value=""><span> %</span>
                                    </div>
                                </td>
                                <td class="lifetime">
                                    <div class="view">
                                    </div>
                                    <div class="edit">
                                        <input type="number" name="<?php echo $field_key . '[0][lifetime]'; ?>" value="">
                                    </div>
                                </td>
                                <td class="wallet">
                                    <div class="view">
                                    </div>
                                    <div class="edit">
                                        <input type="text" class="currency-wallet" name="<?php echo $field_key . '[0][wallet]'; ?>" value="">
                                    </div>
                                </td>
                                <td class="block_confirm">
                                    <div class="view">
                                    </div>
                                    <div class="edit">
                                        <input type="number" name="<?php echo $field_key . '[0][block_confirm]'; ?>" value="">
                                    </div>
                                </td>
                                <td class="decimal">
                                    <div class="view">
                                    </div>
                                    <div class="edit">
                                        <input type="number" name="<?php echo $field_key . '[0][decimal]'; ?>" value="">
                                    </div>
                                </td>
                            </tr>
                            <tr class="editing">
                                <td class="sortable-handle">
                                    <span class="dashicons dashicons-menu"></span>
                                </td>
                                <td class="logo">
                                    <img src="https://s2.coinmarketcap.com/static/img/coins/64x64/2714.png" class="ezdefi-currency-logo" alt="">
                                </td>
                                <td class="name">
                                    <input class="currency-symbol" type="hidden" value="ntf" name="<?php echo $field_key . '[1][symbol]'; ?>]">
                                    <input class="currency-name" type="hidden" value="ntf" name="<?php echo $field_key . '[1][name]'; ?>]">
                                    <input class="currency-logo" type="hidden" value="https://s2.coinmarketcap.com/static/img/coins/64x64/2714.png" name="<?php echo $field_key . '[1][logo]'; ?>">
                                    <input class="currency-desc" type="hidden" value="" name="<?php echo $field_key . '[1][desc]'; ?>">
                                    <input class="currency-chain" type="hidden" value="eth" name="<?php echo $field_key . '[1][chain]'; ?>">
                                    <div class="view">
                                        <span>Ntf</span>
                                        <div class="actions">
                                            <a href="" class="editBtn"><?php echo __( 'Edit', 'woocommerce-gateway-ezdefi' ); ?></a>
                                            |
                                            <a href="" class="deleteBtn"><?php echo __( 'Delete', 'woocommerce-gateway-ezdefi' ); ?></a>
                                        </div>
                                    </div>
                                    <div class="edit">
                                        <select name="<?php echo $field_key . '[1][select]'; ?>" class="select-select2">
                                            <option value="ntf" selected="selected">ntf</option>
                                        </select>
                                        <div class="actions">
                                            <a href="" class="cancelBtn"><?php echo __( 'Cancel', 'woocommerce-gateway-ezdefi' ); ?></a>
                                        </div>
                                    </div>
                                </td>
                                <td class="discount">
                                    <div class="view">
                                    </div>
                                    <div class="edit">
                                        <input type="number" name="<?php echo $field_key . '[1][discount]'; ?>" value=""><span> %</span>
                                    </div>
                                </td>
                                <td class="lifetime">
                                    <div class="view">
                                    </div>
                                    <div class="edit">
                                        <input type="number" name="<?php echo $field_key . '[1][lifetime]'; ?>" value="">
                                    </div>
                                </td>
                                <td class="wallet">
                                    <div class="view">
                                    </div>
                                    <div class="edit">
                                        <input type="text" class="currency-wallet" name="<?php echo $field_key . '[1][wallet]'; ?>" value="">
                                    </div>
                                </td>
                                <td class="block_confirm">
                                    <div class="view">
                                    </div>
                                    <div class="edit">
                                        <input type="number" name="<?php echo $field_key . '[1][block_confirm]'; ?>" value="">
                                    </div>
                                </td>
                                <td class="decimal">
                                    <div class="view">
                                    </div>
                                    <div class="edit">
                                        <input type="number" name="<?php echo $field_key . '[1][decimal]'; ?>" value="">
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="8">
                                <a href="" class="addBtn button button-secondary">
                                    <?php echo __( 'Add Currency', 'woocommerce-gateway-ezdefi' ); ?>
                                </a>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

	/**
	 * Validate currency setting field
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return array|string
	 */
    public function validate_currency_settings_field( $key, $value )
    {
        if( ! is_array( $value ) ) {
            return '';
        }

        foreach( $value as $i => $v ) {
            $value[$i] = array_map( 'sanitize_text_field', $v );
        }

        return $value;
    }

	/**
	 * Add needed scripts for payment process
	 */
    public function payment_scripts()
    {
	    if ( 'no' === $this->enabled ) {
		    return;
	    }

	    wp_register_style( 'wc_ezdefi_checkout', plugins_url( 'assets/css/ezdefi-checkout.css', WC_EZDEFI_MAIN_FILE ), array(), WC_EZDEFI_VERSION );
	    wp_enqueue_style( 'wc_ezdefi_checkout' );

	    wp_register_script( 'wc_ezdefi_blockui', plugins_url( 'assets/js/jquery.blockUI.js', WC_EZDEFI_MAIN_FILE ), array( 'jquery' ), WC_EZDEFI_VERSION );
	    wp_register_style( 'wc_ezdefi_qrcode', plugins_url( 'assets/css/ezdefi-qrcode.css', WC_EZDEFI_MAIN_FILE ), array(), WC_EZDEFI_VERSION );
	    wp_register_script( 'wc_ezdefi_qrcode', plugins_url( 'assets/js/ezdefi-qrcode.js', WC_EZDEFI_MAIN_FILE ), array( 'jquery', 'jquery-ui-tabs' ), WC_EZDEFI_VERSION );
	    wp_localize_script( 'wc_ezdefi_qrcode', 'wc_ezdefi_data',
		    array(
			    'ajax_url' => admin_url( 'admin-ajax.php' ),
                'checkout_url' => wc_get_checkout_url()
		    )
	    );
    }

	/**
	 * Add payment field on checkout page
	 */
	public function payment_fields() {
        $description = $this->get_description();

        ob_start(); ?>
        <div id="wc-ezdefi-checkout">
            <?php echo wpautop( wp_kses_post( $description ) ); ?>
            <fieldset id="wc-ezdefi-currency-select" class="wc-payment-form">
                <?php foreach( $this->currency as $c ) : ?>
                    <div class="form-row form-row-wide">
                        <div class="wc-ezdefi-currency">
                        <input required type="radio" name="wc_ezdefi_currency" id="<?php echo $c['symbol']; ?>" value="<?php echo $c['symbol']; ?>">
                        <label for="<?php echo $c['symbol']; ?>">
                            <div class="left">
                                <img class="logo" src="<?php echo $c['logo']; ?>" alt="">
                                <span class="symbol"><?php echo $c['symbol']; ?></span>
                            </div>
                            <div class="right">
                                <span class="name"><?php echo $c['name']; ?></span>
                                <span class="discount"><?php echo __( 'Discount', 'woocommerce-gateway-ezdefi' ); ?>: <?php echo ( intval($c['discount']) > 0) ? $c['discount'] : 0; ?>%</span>
                                <span class="more">
                                    <?php if( isset($c['desc']) && $c['desc'] != '') : ?>
                                        <span class="tooltip"><?php echo $c['desc']; ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </label>
                    </div>
                    </div>
                <?php endforeach; ?>
            </fieldset>
        </div>
	    <?php echo ob_get_clean();
    }

	/**
     * Validate field before process payment
     *
	 * @return bool
	 */
	public function validate_fields() {
		if( ! isset( $_POST['wc_ezdefi_currency'] ) || empty( $_POST['wc_ezdefi_currency'] ) ) {
		    wc_add_notice( '<strong>' . __( 'Please select currency', 'woocommerce-gateway-ezdefi' ) . '</strong>', 'error' );
		    return false;
        }

		return true;
    }

	/**
     * Handle creating payment
     *
	 * @param int $order_id
	 *
	 * @return array
	 */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

	    $order->update_status('on-hold', __( 'Awaiting ezdefi payment', 'woocommerce-gateway-ezdefi' ) );

	    $symbol = $_POST['wc_ezdefi_currency'];

	    $currency_data = $this->db->get_currency_option( $symbol );

	    if( ! $currency_data ) {
		    wc_add_notice( 'Fail. Please try again or contact shop owner', 'error' );

            $order->update_status( 'failed' );

            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }

	    $order->add_meta_data( 'ezdefi_currency', $symbol );
	    $order->save_meta_data();

	    return array(
		    'result' => 'success',
		    'redirect' => $this->get_return_url( $order )
	    );
    }

	/**
     * Preprend QRcode section to woocommerce_checkout_shortcode (older version)
     *
	 * @param $output
	 * @param $tag
	 *
	 * @return string
	 */
    public function prepend_woocommerce_checkout_shortcode( $output, $tag )
    {
        global $wp;

	    if ( $tag != 'woocommerce_checkout' ) {
		    return $output;
	    }

	    $order_id = $wp->query_vars['order-received'];

	    if( ! $order_id ) {
	        return $output;
        }

	    $prepend = $this->qrcode_section( $order_id );

	    $output = $prepend . $output;

	    return $output;
    }

	/**
     * Add QRcode section to thankyou page
     *
	 * @param $order_id
	 */
    public function qrcode_section( $order_id )
    {
        $order = wc_get_order( $order_id );

	    if( ( $order->get_payment_method() != $this->id ) || ( $order->get_status() === 'completed' ) || ( $order->get_status() === 'failed' ) ) {
		    return;
	    }

	    $symbol = $order->get_meta( 'ezdefi_currency' );

	    $selected_currency = $this->db->get_currency_option( $symbol );

	    if( ! $selected_currency ) {
	        return;
        }

	    $payment_data = array(
		    'uoid' => $order_id,
		    'total' => $order->get_total(),
		    'ezdefi_payment' => ( $order->get_meta( 'ezdefi_payment' ) ) ? $order->get_meta( 'ezdefi_payment' ) : ''
	    );

	    wp_enqueue_style( 'wc_ezdefi_qrcode' );
	    wp_enqueue_script( 'wc_ezdefi_qrcode' );

        ob_start();?>
            <div id="wc_ezdefi_qrcode">
                <script type="application/json" id="payment-data"><?php echo json_encode( $payment_data ); ?></script>
                <div class="selected-currency">
                    <div class="left">
                        <div class="logo">
                            <img class="logo" src="<?php echo $selected_currency['logo']; ?>" alt="">
                        </div>
                        <div class="text">
                            <span class="symbol"><?php echo $selected_currency['symbol']; ?></span>/<span class="name"><?php echo $selected_currency['name']; ?></span><br/>
                            <span class="desc"><?php echo $selected_currency['desc']; ?></span>
                        </div>
                    </div>
                    <div>
                        <a href="" class="changeBtn"><?php _e( 'Change', 'woocommerce-gateway-ezdefi' ); ?></a>
                    </div>
                </div>
                <div class="currency-select">
	                <?php foreach( $this->currency as $c ) : ?>
                        <div class="currency-item">
                            <input <?php echo ($c['symbol'] === $selected_currency['symbol']) ? 'checked' : ''; ?> type="radio" name="currency" id="<?php echo $c['symbol']; ?>">
                            <label for="<?php echo $c['symbol']; ?>">
                                <div class="left">
                                    <img class="logo" src="<?php echo $c['logo']; ?>" alt="">
                                    <span class="symbol"><?php echo $c['symbol']; ?></span>
                                </div>
                                <div class="right">
                                    <span class="name"><?php echo $c['name']; ?></span>
                                    <span class="discount"><?php echo __( 'Discount', 'woocommerce-gateway-ezdefi' ); ?>: <?php echo ( intval($c['discount']) > 0) ? $c['discount'] : 0; ?>%</span>
                                    <span class="more">
                                    <?php if( isset($c['desc']) && $c['desc'] != '') : ?>
                                        <span class="tooltip desc"><?php echo $c['desc']; ?></span>
                                    <?php endif; ?>
                                </span>
                                </div>
                            </label>
                        </div>
		            <?php endforeach; ?>
                </div>
                <div class="ezdefi-payment-tabs">
                    <ul>
                        <?php
                            foreach( $this->payment_method as $key => $value ) {
                                echo '<li><a href="#'.$key.'" id="tab-'.$key.'">';
                                switch ($key) {
                                    case 'amount_id' :
                                        echo '<span>' . __( 'Simple method', 'woocommerce-gateway-ezdefi' ) . '</span>';
                                        break;
                                    case 'ezdefi_wallet' :
                                        echo '<img width="18" src="'.plugins_url( 'assets/images/ezdefi-icon.png', WC_EZDEFI_MAIN_FILE ).'"> <span> ' . __( 'Pay with ezDeFi wallet', 'woocommerce-gateway-ezdefi' ) . '</span></a></li>';
                                        break;
                                }
                                echo '</a></li>';
                            }
                        ?>
                    </ul>
	                <?php foreach( $this->payment_method as $key => $value ) : ?>
                        <div id="<?php echo $key;?>" class="ezdefi-payment-panel"></div>
	                <?php endforeach; ?>
                </div>
                <button class="submitBtn" style="display: none"><?php _e( 'Confirm', 'woocommerce-gateway-ezdefi' ); ?></button>
            </div>
        <?php echo ob_get_clean();
    }

	/**
	 * Handle callback from gateway when payment DONE
	 */
	public function gateway_callback_handle()
	{
		global $woocommerce;

		if( ! isset( $_GET['uoid'] ) || ! isset( $_GET['paymentid'] ) ) {
		    wp_die();
        }

		$order_id = $_GET['uoid'];
		$order_id = substr( $order_id, 0, strpos( $order_id,'-' ) );
		$order = wc_get_order( $order_id );

		if( ! $order ) {
		    wp_die();
        }

		$paymentid = $_GET['paymentid'];

		$response = $this->api->get_ezdefi_payment( $paymentid );

		if( is_wp_error( $response ) ) {
			wp_die();
		}

		$payment = json_decode( $response['body'], true );

		if( $payment['code'] < 0 ) {
		    wp_die();
        }

		$payment = $payment['data'];

		$status = $payment['status'];

		if( $status === 'DONE' ) {
			$order->update_status( 'completed' );
			$woocommerce->cart->empty_cart();
		}

		wp_die();
	}
}