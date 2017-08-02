<?php

/**
 * Gestpay for WooCommerce
 *
 * Copyright: © 2013-2016 MAURO MASCIA (info@mauromascia.com)
 * Copyright: © 2017 Easy Nolo s.p.a. - Gruppo Banca Sella (www.easynolo.it - info@easynolo.it)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

class WC_Gateway_Gestpay_BON extends WC_Gateway_Gestpay {
    public function __construct() {
        $this->set_this_gateway_params( 'Gestpay Pro BON' );
        $this->paymentType = 'BON';
        $this->Helper->init_gateway( $this );
        $this->set_this_gateway();
        $this->add_actions();
    }
}

class WC_Gateway_Gestpay_PAYPAL extends WC_Gateway_Gestpay {
    public function __construct() {
        $this->set_this_gateway_params( 'Gestpay Pro PAYPAL' );
        $this->paymentType = 'PAYPAL';
        $this->Helper->init_gateway( $this );
        $this->set_this_gateway();
        $this->add_actions();
    }
}

class WC_Gateway_Gestpay_MYBANK extends WC_Gateway_Gestpay {
    public function __construct() {
        $this->set_this_gateway_params( 'Gestpay Pro MYBANK' );
        $this->paymentType = 'MYBANK';
        $this->Helper->init_gateway( $this );
        $this->set_this_gateway();
        $this->add_actions();
    }
}

class WC_Gateway_Gestpay_CONSEL extends WC_Gateway_Gestpay {
    public function __construct() {
        $this->set_this_gateway_params( 'Gestpay Pro CONSEL' );
        $this->paymentType = 'CONSEL';
        $this->Helper->init_gateway( $this );
        $this->set_this_gateway();
        $this->add_actions();

        add_filter( 'gestpay_encrypt_parameters', array( $this, 'add_consel_encrypt_parameters' ) );
    }

    /**
     * Add parameters for CONSEL if enabled.
     */
    public function add_consel_encrypt_parameters( $params ) {
        if ( $this->enabled == 'yes'
            && ! empty( $params->paymentTypes['paymentType'] )
                && $params->paymentTypes['paymentType'] == $this->paymentType
        ) {
            $params->IdMerchant = $this->get_option( 'param_consel_id_merchant' );
            $params->Consel_MerchantPro = $this->get_option( 'param_consel_merchant_pro' );
        }

        return $params;
    }
}

class WC_Gateway_Gestpay_MASTERPASS extends WC_Gateway_Gestpay {
    public function __construct() {
        $this->set_this_gateway_params( 'Gestpay Pro MASTERPASS' );
        $this->paymentType = 'MASTERPASS';
        $this->Helper->init_gateway( $this );
        $this->set_this_gateway();
        $this->add_actions();
    }
}

class WC_Gateway_Gestpay_COMPASS extends WC_Gateway_Gestpay {
    public function __construct() {
        $this->set_this_gateway_params( 'Gestpay Pro COMPASS' );
        $this->paymentType = 'COMPASS';
        $this->Helper->init_gateway( $this );
        $this->set_this_gateway();
        $this->add_actions();
    }
}

add_filter( 'woocommerce_payment_gateways', 'woocommerce_payment_gateways_add_gestpay_pro_payment_types' );
function woocommerce_payment_gateways_add_gestpay_pro_payment_types( $methods ) {
    // Always add main class.
    $methods[] = 'WC_Gateway_Gestpay';

    if ( 'yes' == get_option( 'wc_gestpay_param_payment_types' ) ) {

        if ( 'yes' == get_option( 'wc_gestpaypro_paypal' ) )      $methods[] = 'WC_Gateway_Gestpay_PAYPAL';
        if ( 'yes' == get_option( 'wc_gestpaypro_mybank' ) )      $methods[] = 'WC_Gateway_Gestpay_MYBANK';
        if ( 'yes' == get_option( 'wc_gestpaypro_consel' ) )      $methods[] = 'WC_Gateway_Gestpay_CONSEL';
        if ( 'yes' == get_option( 'wc_gestpaypro_masterpass' ) )  $methods[] = 'WC_Gateway_Gestpay_MASTERPASS';
        if ( 'yes' == get_option( 'wc_gestpaypro_compass' ) )     $methods[] = 'WC_Gateway_Gestpay_COMPASS';

    }

    return $methods;
}