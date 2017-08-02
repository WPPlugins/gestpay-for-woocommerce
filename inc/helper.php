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


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'WC_Gateway_GestPay_Helper' ) ) :

class WC_Gateway_GestPay_Helper {

    public $plugin_url;
    public $plugin_path;
    public $plugin_slug;
    public $plugin_textdomain;
    public $plugin_logfile;

    function __construct() {
        $this->plugin_url            = trailingslashit( plugins_url( '', $plugin = GESTPAY_MAIN_FILE ) );
        $this->plugin_dir_path       = plugin_dir_path( GESTPAY_MAIN_FILE );
        $this->plugin_path           = dirname( plugin_basename( GESTPAY_MAIN_FILE ) );
        $this->plugin_slug           = basename( GESTPAY_MAIN_FILE, '.php' );
        $this->plugin_slug_dashed    = str_replace( "-", "_", $this->plugin_slug );
        $this->plugin_textdomain     = $this->plugin_slug;
        $this->plugin_logfile_name   = $this->get_plugin_logfile_name();
    }

    /**
     * Localize, script and init the gateway
     */
    function init_gateway( &$this_gw ) {
        $this->gw = $this_gw;

        // Localize
        load_plugin_textdomain( 'gestpay-for-woocommerce', false, $this->plugin_path . "/languages" );

        // Style
        wp_enqueue_style( 'gestpay-for-woocommerce-css', $this->plugin_url . '/gestpay-for-woocommerce.css' );

        // Maybe load the strings used on this plugin
        if ( method_exists( $this_gw, 'init_strings' ) ) {
            $this_gw->init_strings();
        }

        // Load form fields and settings
        $this_gw->form_fields = require dirname( GESTPAY_MAIN_FILE ) . '/inc/init_form_fields.php';
        $this_gw->init_settings();
        $this->load_card_icons();
    }

    function get_cards_settings() {
        return array(
            'cards' => array(
                'title' => $this->gw->strings['gateway_overwrite_cards'],
                'type' => 'title',
                'description' => $this->gw->strings['gateway_overwrite_cards_label'],
                'class' => 'mmnomargin',
            ),
            'card_visa' => array(
                'title' => '',
                'type' => 'checkbox',
                'label' => 'Visa Electron',
                'default' => 'no'
            ),
            'card_mastercard' => array(
                'title' => '',
                'type' => 'checkbox',
                'label' => 'Mastercard',
                'default' => 'no'
            ),
            'card_maestro' => array(
                'title' => '',
                'type' => 'checkbox',
                'label' => 'Maestro',
                'default' => 'no'
            ),
            'card_ae' => array(
                'title' => '',
                'type' => 'checkbox',
                'label' => 'American Express',
                'default' => 'no'
            ),
            'card_dci' => array(
                'title' => '',
                'type' => 'checkbox',
                'label' => 'Diners Club International',
                'default' => 'no'
            ),
            'card_paypal' => array(
                'title' => '',
                'type' => 'checkbox',
                'label' => 'PayPal',
                'default' => 'no'
            ),
            'card_jcb' => array(
                'title' => '',
                'type' => 'checkbox',
                'label' => 'JCB Cards',
                'default' => 'no'
            ),
            'card_postepay' => array(
                'title' => '',
                'type' => 'checkbox',
                'label' => 'PostePay',
                'default' => 'no'
            ),
        );
    }

    /**
     * Check if the order was paid with Gestpay.
     */
    function is_gestpaid( $order_id ) {
        if ( 'wc_gateway_gestpay' == get_post_meta( $order_id, '_payment_method', TRUE ) ) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Load card icons.
     */
    function load_card_icons() {
        $cards = array();
        $card_path = $this->plugin_url . '/images/cards/';
        $gws = $this->gw->settings;

        if (isset($gws['card_visa'])       && $gws['card_visa'] == "yes")       $cards[] = $card_path . 'card_visa.jpg';
        if (isset($gws['card_mastercard']) && $gws['card_mastercard'] == "yes") $cards[] = $card_path . 'card_mastercard.jpg';
        if (isset($gws['card_maestro'])    && $gws['card_maestro'] == "yes")    $cards[] = $card_path . 'card_maestro.jpg';
        if (isset($gws['card_ae'])         && $gws['card_ae'] == "yes")         $cards[] = $card_path . 'card_ae.jpg';
        if (isset($gws['card_dci'])        && $gws['card_dci'] == "yes")        $cards[] = $card_path . 'card_dci.jpg';
        if (isset($gws['card_paypal'])     && $gws['card_paypal'] == "yes")     $cards[] = $card_path . 'card_paypal.jpg';
        if (isset($gws['card_jcb'])        && $gws['card_jcb'] == "yes")        $cards[] = $card_path . 'card_jcb.jpg';
        if (isset($gws['card_postepay'])   && $gws['card_postepay'] == "yes")   $cards[] = $card_path . 'card_postepay.jpg';

        if ( empty( $cards ) ) return;

        // workaround for get_icon() of WC_Payment_Gateway
        // @see abstract-wc-payment-gateway.php
        $cards_string = '';
        foreach ( $cards as $card ) {
            $cards_string .= $card . ( end( $cards ) == $card ? '' : '" /><img src="' );
        }

        $this->gw->icon = $cards_string;
    }

    function get_plugin_logfile_name() {
        return ( defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : '' ) . $this->plugin_slug."-".sanitize_file_name( wp_hash( $this->plugin_slug ) ).'.log';
    }

    function log_add( $message, $arr = array() ) {
        if ( $this->gw->debug ) {
            if ( ! isset( $this->log ) || empty( $this->log ) ) {
                $this->log = new WC_Logger();
            }

            $message.= $this->var_export( $arr );

            $this->log->add( $this->plugin_slug, $message );
        }
    }

   /**
    * This prevent to change the floating point numbers precisions with var_export
    * @see also http://stackoverflow.com/a/32149358/1992799
    *
    * @thanks to Luca Cantoreggi
    *
    * @param $expression mixed Same as var_export
    *
    * @return mixed
    */
   private function var_export( $expression ) {
        if ( empty( $expression ) ) {
            return '';
        }

       // Store the current precision
       $ini_value = ini_get( 'serialize_precision' );

       // Set the new precision and export the variable
       ini_set( 'serialize_precision', 2 );
       $value = var_export( $expression, TRUE );

       // Restore the previous value
       ini_set( 'serialize_precision', $ini_value );

       return ' ' . $value;
   }

    /**
     * Clean and validate order's prefix
     */
    function get_order_prefix( &$settings ) {
        if ( isset( $settings['order_prefix'] ) && ! empty( $settings['order_prefix'] ) ) {
            // allows only alphanumeric charactes
            $prefix = preg_replace( "/[^A-Za-z0-9]/", '', $settings['order_prefix'] );

            // max 15 char
            $prefix = substr( $prefix, 0, 15 );

            // Update the order prefix value
            $settings['order_prefix'] = $prefix;

            return $prefix;
        }

        return '';
    }

    /**
     * Construct the custom info string
     *
     * @return string
     */
    function get_custominfo() {
        $custom_info = array();

        // Split the textarea content by each row
        $custominfos = explode( "\n", trim( $this->param_custominfo ) );

        // Remove any extra \r characters left behind
        $custominfos = array_filter( $custominfos, 'trim' );

        foreach ( $custominfos as $custominfo ) {
            // max field lenght is 300 characters and unallowed chars must me removed
            $custominfo    = substr( $custominfo, 0, 300 );
            $custom_info[] = $this->get_clean_param( $custominfo );
        }

        return implode( "*P1*", $custom_info );
    }

    /**
     * Clean up the string removing unallowed parameters.
     *
     * @param string $in_string
     *
     * @return string
     */
    function get_clean_param( $in_string ) {
        return str_replace( array(
          "&"," ","§","(",")","*","<",">",",",";",":","*P1*","/","/*","[","]","?","%"
        ), "", $in_string );
    }

    /**
     * Get current language between ones available on GestPay Pro.
     * Default English
     *
     * @return int
     */
    function get_language() {
        switch ( $this->get_current_language_2dgtlwr() ) {
            case 'it' :
                return 1;
            case 'es' :
                return 3;
            case 'fr' :
                return 4;
            case 'de' :
                return 5;
        }

        return 2; // en
    }

    /**
     * Mapper for the Gestpay currency codes.
     */
    function get_order_currency( $order ) {
        $gestpay_allowed_currency_codes = array(
            'USD' =>   '1',  // United States Dollar
            'GBP' =>   '2',  // United Kingdom Pound
            'CHF' =>   '3',  // Switzerland Franc
            'DKK' =>   '7',  // Denmark Krone
            'NOK' =>   '8',  // Norway Krone
            'SEK' =>   '9',  // Sweden Krona
            'CAD' =>  '12',  // Canada Dollar
            'ITL' =>  '18',  // Italian Lira
            'JPY' =>  '71',  // Japan Yen
            'HKD' => '103',  // Hong Kong Dollar
            'AUD' => '109',  // Australia Dollar
            'SGD' => '124',  // Singapore Dollar
            'CNY' => '144',  // China Yuan Renminbi
            'HUF' => '153',  // Hungary Forint
            'CZK' => '223',  // Czech Republic Koruna
            'BRL' => '234',  // Brazil Real
            'PLN' => '237',  // Poland Zloty
            'EUR' => '242',  // Euro Member Countries
            'RUB' => '244',  // Russian Ruble
        );

        $the_currency = $this->get_currency( $order );

        if ( in_array( $the_currency, array_keys( $gestpay_allowed_currency_codes ) ) ) {
            $gp_currency = $gestpay_allowed_currency_codes[$the_currency];
        }
        else {
            $gp_currency = '242'; // Set EUR as default currency code
        }

        return $gp_currency;
    }

    function get_currency( $order ) {

        if ( method_exists( $order, 'get_currency' ) ) { // wc>=3
            $the_currency = $order->get_currency();
        }
        elseif ( method_exists( $order, 'get_order_currency' ) ) { // wc<3
            $the_currency = $order->get_order_currency();
        }
        else {
            $the_currency = get_post_meta( $this->order_get( $order, 'id' ), '_order_currency', true );
        }

        if ( empty( $the_currency ) ) {
            $the_currency = get_option( 'woocommerce_currency' );
        }

        return $the_currency;
    }

    /**
     * Backward compatibility function to get a property of the order.
     * On WC 2.x there was a direct access, while on 3.x it uses the getter.
     */
    function order_get( $order, $get ) {
        if ( version_compare( WC_VERSION, '2.6.15', '<' ) ) {
            switch ( $get ) {
                case 'total':
                $get = 'order_total';
                break;
            }
            return $order->{$get};
        }

        $get = 'get_' . $get;
        return $order->$get();
    }

    /**
     * Returns the WooCommerce version number, backwards compatible to WC 1.x
     *
     * @return null|string
     */
    function get_wc_version() {
        if ( defined( 'WC_VERSION' ) && WC_VERSION ) return WC_VERSION;
        if ( defined( 'WOOCOMMERCE_VERSION' ) && WOOCOMMERCE_VERSION ) return WOOCOMMERCE_VERSION;
        return null;
    }

    function is_wc_gte( $v ) {
        return version_compare( $this->get_wc_version(), $v, '>=' );
    }

    /* short checks */
    function is_wc_gte_20() { return $this->is_wc_gte( '2.0.0' ); }
    function is_wc_gte_21() { return $this->is_wc_gte( '2.1.0' ); }
    function is_wc_gte_22() { return $this->is_wc_gte( '2.2.0' ); }
    function is_wc_gte_23() { return $this->is_wc_gte( '2.3.0' ); }
    function is_wc_gte_24() { return $this->is_wc_gte( '2.4.0' ); }
    function is_wc_gte_25() { return $this->is_wc_gte( '2.5.0' ); }
    function is_wc_gte_26() { return $this->is_wc_gte( '2.6.0' ); }
    function is_wc_gte_27() { return $this->is_wc_gte( '2.7.0' ); }
    function is_wc_gte_30() { return $this->is_wc_gte( '3.0.0' ); }

    /**
     * Backwards compatible get URL
     */
    function wc_url( $path, $order ) {
        switch ( $path ) {
            case 'view_order':
                return $order->get_view_order_url();
          
            case 'order_received':
                return $this->gw->get_return_url( $order );

            case 'order_failed':
                return wc_get_checkout_url();

            case 'pay':
                return $order->get_checkout_payment_url( true );
        }

        return '';
    }

    function wc_empty_cart() {
        WC()->cart->empty_cart();
    }

    /**
     * Update order status, add admin order note and empty the cart
     */
    function wc_order_completed( $order, $message, $tx_id = '' ) {
        $order->payment_complete( $tx_id );
        $order->add_order_note( $message );
        $this->wc_empty_cart();
        $this->log_add( 'ORDER COMPLETED: ' . $message );

        // FIX: under some circustances emails seems to not be fired. This force them to be sent.
        if ( defined( 'WC_GATEWAY_FORCE_SEND_EMAIL' ) && WC_GATEWAY_FORCE_SEND_EMAIL ) {
            $mailer = WC()->mailer();
            $mails = $mailer->get_emails();
            if ( ! empty( $mails ) ) {
                foreach ( $mails as $mail ) {
                    if ( ( $order->has_status( 'completed' ) && ($mail->id == 'customer_completed_order' || $mail->id == 'new_order') )
                        || ( $order->has_status( 'processing' ) && ($mail->id == 'customer_processing_order' || $mail->id == 'new_order') ) ) {
                        $mail->trigger( $this->order_get( $order, 'id' ) );
                  }
                }
            }
        }
        // \FIX
    }

    function wc_enqueue_autosubmit() {
        $code = $this->get_autosubmit_js();
        wc_enqueue_js( $code );
    }

    function get_autosubmit_js() {
        $assets_path = str_replace( array( 'http:', 'https:' ), '', WC()->plugin_url() ) . '/assets/';
        $imgloader = $assets_path . 'images/ajax-loader@2x.gif';
        $js = <<<JS
          jQuery('html').block({
            message: '<img src="$imgloader" alt="Redirecting&hellip;" style="float:left;margin-right:10px;"/>Thank you! We are redirecting you to make payment.',
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            },
            css: {
                padding: 20,
                textAlign: 'center',
                color: '#555',
                border: '3px solid #aaa',
                backgroundColor: '#fff',
                cursor: 'wait',
                lineHeight: '32px'
            }
          });
          jQuery('#submit__{$this->plugin_slug_dashed}').click();
JS;
        return $js;
    }

    /**
     * Create the gateway form, loading the autosubmit javascript.
     */
    function get_gw_form( $action_url, $method, $input_params, $order ) {
        $action_url        = esc_url_raw( $action_url );
        $cancel_url        = esc_url_raw( $order->get_cancel_order_url() );
        $pay_order_str     = 'Pay via '.$this->gw->method_title;
        $cancel_order_str  = 'Cancel order &amp; restore cart';
    
        $this->wc_enqueue_autosubmit();
    
        $input_fields = "";
        foreach ( $input_params as $key => $value ) {
            $input_fields.= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
        }
    
        return <<<HTML
            <form action="{$action_url}" method="{$method}" id="form__{$this->plugin_slug_dashed}" target="_top">
                $input_fields
                <input type="submit" class="button-alt" id="submit__{$this->plugin_slug_dashed}" value="{$pay_order_str}" />
                <a class="button cancel" href="$cancel_url">{$cancel_order_str}</a>
            </form>
HTML;
    }

    /**
     * Backwards compatible add error
     */
    function wc_add_error( $error ) {
        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( $error, 'error' );
        }
    }

    /**
     * Check if qTranslate-X or mqTranslate is enabled.
     *
     * @return bool true if one of them is active, false otherwise.
     */
    function is_qtranslate_enabled() {
        return ( defined('QTX_VERSION') ||
            in_array( 'qtranslate/qtranslate.php', (array) get_option( 'active_plugins', array() ) ) ||
                in_array( 'mqtranslate/mqtranslate.php', (array) get_option( 'active_plugins', array() ) ) );
    }

    /**
     * Checks if WooCommerce Subscriptions is active
     *
     * @return bool true if WCS is active, false otherwise.
     */
    function is_subscriptions_active() {
        return in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', (array) get_option( 'active_plugins', array() ) );
    }

    /**
     * Returns current language checking for qTranslate-X or WPML
     * Fallback on get_locale() if nothing found.
     *
     * @return string
     */
    function get_current_language() {
        if ( $this->is_qtranslate_enabled() ) {
            if ( function_exists( 'qtranxf_getLanguage' ) ) {
                return qtranxf_getLanguage(); // -- qTranslate X
            }
            else if ( function_exists( 'qtrans_getLanguage' ) ) {
                return qtrans_getLanguage(); // -- qTranslate / mqTranslate
            }
        }
        elseif ( defined( 'ICL_LANGUAGE_CODE' ) ) { // --- Wpml
            return ICL_LANGUAGE_CODE;
        }

        return get_locale();
    }

    /**
     * Returns the two characters of the language in lowercase
     *
     * @return string
     */
    function get_current_language_2dgtlwr() {
        return substr( strtolower( $this->get_current_language() ), 0, 2 );
    }

    /**
     * Returns the OK URL.
     *
     * @param WC_Order $order
     *
     * @return string
     */
    function get_url_ok( $order ) {
        return add_query_arg( 'utm_nooverride', '1',
            $this->adjust_url_lang( $this->wc_url( 'order_received', $order ) )
        );
    }

    /**
     * Returns the KO URL.
     *
     * @param WC_Order $order
     *
     * @return string
     */
    function get_url_ko( $order ) {
        return add_query_arg( 'utm_nooverride', '1',
            $this->adjust_url_lang( $this->wc_url( 'order_failed', $order ) )
        );
    }

    /**
     * Adjust the URL (pre-path, pre-domain or query)
     * ATM only qTranslate is supported
     *
     * @param string $url
     *
     * @return string
     */
    function adjust_url_lang( $url ) {
        if ( $this->is_qtranslate_enabled() && function_exists( 'qtrans_convertURL' ) ) {
            return qtrans_convertURL( $url, $this->get_current_language_2dgtlwr() );
        }

        return $url;
    }

    /**
     * Generate the option list
     */
    function get_page_list_as_option() {
        $opt_pages = array( 0 => " -- Select -- " );
        foreach ( get_pages() as $page ) {
            $opt_pages[ $page->ID ] = __( $page->post_title );
        }

        return $opt_pages;
    }

    /**
     * Show an error message.
     */
    function show_error( $msg ) {
        echo '<div id="woocommerce_errors" class="error fade"><p>ERRORE: ' . $msg . '</p></div>';
    }

    /**
     * Create a SOAP client using the specified URL
     */
    function get_soap_client( $url ) {
        try {
            $client = new SoapClient( $url );
        }
        catch ( Exception $e ) {
            $err = sprintf( __( 'Soap Client Request Exception with error %s' ), $e->getMessage() );
            $this->wc_add_error( $err );
            $this->log_add( '[FATAL ERROR]: ' . $err );

            return false;
        }

        return $client;
    }

    /**
     * Check if the SOAP extension is enabled.
     *
     * @return false if SOAP is not enabled.
     */
    function check_fatal_soap( $plugin_name ) {
        if ( ! extension_loaded( 'soap' ) ) {
            $this->show_error( 'Per poter utilizzare <strong>' . $plugin_name . '</strong> la libreria SOAP client di PHP deve essere abilitata!' );
            return false;
        }

        return true;
    }

    /**
     * Check if suhosin is enabled and the get.max_value_length value.
     *
     * @return false if suhosin is not well configured.
     */
    function check_fatal_suhosin( $plugin_name, $print = TRUE ) {
        if ( is_numeric( @ini_get( 'suhosin.get.max_value_length' ) ) && ( @ini_get( 'suhosin.get.max_value_length' ) < 1024 ) ) {

            if ( $print ) {
                $this->show_error( $this->get_suhosin_error_msg( $plugin_name ) );
            }

            return false;
        }

        return true;
    }

    function get_suhosin_error_msg( $plugin_name ) {
        $err_suhosin = 'Sul tuo server è presente <a href="http://www.hardened-php.net/suhosin/index.html" target="_blank">PHP Suhosin</a>.<br>Devi aumentare il valore di <a href="http://suhosin.org/stories/configuration.html#suhosin-get-max-value-length" target="_blank">suhosin.get.max_value_length</a> almeno a 1024, perché <strong>' . $plugin_name . '</strong> utilizza delle query string molto lunghe.<br>';
        $err_suhosin.= '<strong>' . $plugin_name . '</strong> non potrà essere utilizzato finché non si aumenta tale valore!';

        return $err_suhosin;
    }

    /**
     * Safely get and trim data from $_POST
     */
    function get_post( $key ) {
        return isset( $_POST[$key] ) ? trim( $_POST[$key] ) : '';
    }
}

endif; // ! class_exists( 'WC_Gateway_GestPay_Helper' )


// Backward compatibility to get the order id outside without the helper.
function wc_gp_get_order_id( $order ) {
    return version_compare( WC_VERSION, '2.6.15', '<' ) ? $order->id : $order->get_id();
}