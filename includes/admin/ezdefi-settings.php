<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return apply_filters(
    'wc_ezdefi_settings',
    array(
        'enabled' => array(
            'title' => __( 'Enable/Disable', 'woocommerce-gateway-ezdefi' ),
            'label' => __( 'Enable ezDeFi', 'woocommerce-gateway-ezdefi' ),
            'type' => 'checkbox',
            'description' => '',
            'default' => 'no',
        ),
        'title' => array(
	        'title' => __( 'Title', 'woocommerce-gateway-ezdefi' ),
	        'type' => 'text',
	        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
	        'default' => __( 'ezDeFi', 'woocommerce-gateway-stripe' ),
	        'desc_tip' => true,
        ),
        'description' => array(
	        'title' => __( 'Description', 'woocommerce-gateway-ezdefi' ),
	        'type' => 'textarea',
	        'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-stripe' ),
	        'default' => __( 'Pay with ezDeFi', 'woocommerce-gateway-stripe' ),
	        'desc_tip' => true,
        ),
        'api_url' => array(
            'title' => __( 'Gateway API Url', 'woocommerce-gateway-ezdefi' ),
            'type' => 'text',
            'description' => __( 'Description' ),
            'desc_tip' => true,
        ),
        'api_key' => array(
            'title' => __( 'Gateway API Key', 'woocommerce-gateway-ezdefi' ),
            'type' => 'text',
            'description' => __( 'Description' ),
            'desc_tip' => true,
        ),
        'payment_method' => array(
            'title' => __( 'Payment Method', 'woocommerce-gateway-ezdefi' ),
	        'type' => 'method_settings',
	        'description' => __( 'Description' ),
	        'desc_tip' => true,
        ),
        'acceptable_variation' => array(
	        'title' => __( 'Acceptable price variation', 'woocommerce-gateway-ezdefi' ),
	        'type' => 'number',
	        'description' => __( 'Description' ),
	        'desc_tip' => true,
	        'default' => 0.01
        ),
        'currency' => array(
            'title' => __( 'Accepted Currency', 'woocommerce-gateway-ezdefi' ),
            'type' => 'currency_settings',
            'description' => __( 'Description', 'woocommerce-gateway-ezdefi' ),
            'desc_tip' => true,
        )
    )
);