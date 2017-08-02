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

/**
 * Handles S2S actions on orders.
 */
class Gestpay_Order_Actions {

    /**
     * Plugin actions.
     */
    public function __construct( $gestpay ) {

        $this->Gestpay = $gestpay;
        $this->Helper = $this->Gestpay->Helper;
        $this->soapClient = $this->Helper->get_soap_client( $this->Gestpay->ws_S2S_url );

    }

    /**
     * Perform a partial or complete transaction amount refund.
     *
     * @see http://api.gestpay.it/#callrefunds2s
     *
     * @param  int    $order_id
     * @param  float  $amount
     * @param  string $reason
     *
     * @return bool True or false based on success, or a WP_Error object
     */
    public function refund( $order_id, $amount = null, $reason = '' ) {

        if ( empty( $amount ) ) {
            return FALSE;
        }

        $order = wc_get_order( $order_id );

        $banktid = get_post_meta( $order_id, GESTPAY_ORDER_META_BANK_TID, TRUE );

        if ( ! $order || empty( $banktid ) ) {
            $this->log( $order, $this->Gestpay->strings['refund_err_1'] );
            return FALSE;
        }

        if ( ! $this->soapClient ) {
            $this->log( $order, $this->Gestpay->strings['refund_err_2'] );
            return FALSE;
        }

        // Define parameters for Refund
        $params = new stdClass();

        $params->shopLogin         = $this->Gestpay->shopLogin;
        $params->bankTransactionId = trim( $banktid );
        $params->shopTransactionId = $order_id;
        $params->amount            = number_format( (float)$amount, 2, '.', '' );
        $params->uicCode           = $this->Helper->get_order_currency( $order );
        $params->RefundReason      = substr( $reason, 0, 50 );
        $params->chargeBackFraud   = 'N'; // can also be 'Y' but for now can't be specified on UI

        $this->Helper->log_add( '[CallRefundS2S REQUEST]: ', $params );

        // Do the request to refund the order
        try {
            $response = $this->soapClient->CallRefundS2S( $params );

            $xml = simplexml_load_string( $response->callRefundS2SResult->any );

            do_action( 'gestpay_before_order_refund', $order, $xml );

            $this->Helper->log_add( '[CallRefundS2S RESPONSE]: ', $response );

            if ( (string)$xml->TransactionResult == "OK" ) {

                do_action( 'gestpay_order_refund_success', $order, $xml );

                $this->log( $order, sprintf( $this->Gestpay->strings['refund_ok'], $amount, $xml->BankTransactionID ) );
                return TRUE;
            }

            do_action( 'gestpay_order_refund_fail', $order, $xml );

            $resp_err = '[Error ' . $xml->ErrorCode . '] ' . $xml->ErrorDescription;
            $this->log( $order, $resp_err );
            return FALSE;
        }
        catch ( Exception $e ) {
            $this->log( $order, '[REFUND ERROR]: ' . $e->getMessage() );
            return FALSE;
        }
    }

    private function log( $order, $mess ) {
        $this->Helper->log_add( $mess );
        $order->add_order_note( $mess );
    }

    /**
     * Ajax Settle
     */
    public function ajax_settle() {

        ob_start();

        check_ajax_referer( 'order-item', 'security' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die();
        }

        $order_id = absint( $_POST['order_id'] );

        $res = $this->settle( $order_id );

        if ( $res === TRUE ) {
            wp_send_json_success( array( 'status' => 'OK' ) );
        }
        else {
            wp_send_json_error( array( 'error' => $res ) );
        }

        // Clear transients
        wc_delete_shop_order_transients( $order_id );
    }

    /**
     * Financial confirmation of an authorized transaction.
     *
     * @see http://api.gestpay.it/#callsettles2s
     */
    public function settle( $order_id, $amount = false ) {

        $order = wc_get_order( $order_id );

        $this->Helper->log_add( '[S2S Settle order_id]: ' . $order_id );

        try {
            $banktid = get_post_meta( $order_id, GESTPAY_ORDER_META_BANK_TID, TRUE );

            // Define parameters for Settle
            $params = new stdClass();

            if ( ! $amount ) {
                $amount = wc_format_decimal( $order->get_total(), wc_get_price_decimals() );
            }

            $params->shopLogin   = $this->Gestpay->shopLogin;
            $params->amount      = $amount;
            $params->uicCode     = $this->Helper->get_order_currency( $order );
            $params->bankTransID = (int)trim( $banktid );
            $params->shopTransID = $order_id;
            //$params->FullFillment = ''; // Not used.

            $this->Helper->log_add( '[CallSettleS2S REQUEST]: ', $params );

            $response = $this->soapClient->CallSettleS2S( $params );

            $xml = simplexml_load_string( $response->callSettleS2SResult->any );

            $this->Helper->log_add( '[CallSettleS2S RESPONSE]: ', $xml );

            do_action( 'gestpay_before_order_settle', $order, $xml );

            if ( (string)$xml->TransactionResult == "OK" ) {
                $this->log( $order, 'Settle OK [BankTransactionID: ' . $xml->BankTransactionID . ']' );

                do_action( 'gestpay_order_settle_success', $order, $xml );

                return TRUE;
            }
            else {
                $resp_err = '[Error ' . $xml->ErrorCode . '] ' . $xml->ErrorDescription;

                do_action( 'gestpay_order_settle_fail', $order, $xml );

                return $resp_err;
            }
        }
        catch ( Exception $e ) {
            return $e->getMessage();
        }
    }

    /**
     * Deletes an authorized transaction.
     *
     * @see http://api.gestpay.it/#calldeletes2s
     */
    public function ajax_delete() {

        ob_start();

        check_ajax_referer( 'order-item', 'security' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die();
        }

        $order_id = absint( $_POST['order_id'] );

        $this->Helper->log_add( '[S2S Delete order_id]: ' . $order_id );

        try {
            // Validate that the settle can occur
            $order   = wc_get_order( $order_id );
            $banktid = get_post_meta( $order_id, GESTPAY_ORDER_META_BANK_TID, TRUE );

            // Define parameters for Delete
            $params = new stdClass();

            $params->shopLogin         = $this->Gestpay->shopLogin;
            $params->bankTransactionId = (int)trim( $banktid );
            $params->shopTransactionId = $order_id;
            $params->CancelReason      = 'Transaction withdrawn manually.';

            $this->Helper->log_add( '[CallDeleteS2S REQUEST]: ', $params );

            $response = $this->soapClient->CallDeleteS2S( $params );

            $xml = simplexml_load_string( $response->callDeleteS2SResult->any );

            $this->Helper->log_add( '[CallDeleteS2S RESPONSE]: ', $xml );

            do_action( 'gestpay_before_order_delete', $order, $xml );

            if ( (string)$xml->TransactionResult == "OK" ) {

                $this->log( $order, sprintf( $this->Gestpay->strings['delete_ok'], $xml->BankTransactionID ) );

                do_action( 'gestpay_order_delete_success', $order, $xml );

                $order->update_status( 'cancelled', '' );

                wp_send_json_success( array( 'status' => 'OK' ) );
            }
            else {
                $resp_err = '[Error ' . $xml->ErrorCode . '] ' . $xml->ErrorDescription;

                do_action( 'gestpay_order_delete_fail', $order, $xml );

                wp_send_json_error( array( 'error' => $resp_err ) );
            }

            // Clear transients
            wc_delete_shop_order_transients( $order_id );
        }
        catch ( Exception $e ) {
            wp_send_json_error( array( 'error' => $e->getMessage() ) );
        }
    }

}


/**
 * Adds the Gestpay buttons in the order actions secions, after the Refund button.
 * Also adds the javascript necessary to invoke the ajax actions.
 */
function gestpay_order_actions_add_action_buttons( $order ) {

    // Check if the order is paid and is paid with Gestpay, otherwise we don't need these buttons.
    if ( 'wc_gateway_gestpay' != get_post_meta( wc_gp_get_order_id( $order ), '_payment_method', TRUE ) || ! $order->is_paid() ) {
        return;
    }

    $gp_strings = include 'translatable-strings.php';
    ?>

    <button type="button" class="button gestpay-settle-items"><?php echo $gp_strings['button_settle']; ?>
        <?php echo wc_help_tip( $gp_strings['tip_settle'] ); ?>
    </button>

    <button type="button" class="button gestpay-delete-items"><?php echo $gp_strings['button_delete']; ?>
        <?php echo wc_help_tip( $gp_strings['tip_delete'] ); ?>
    </button>

    <script>
    (function($) {

        function gestpay_ajax_call( action, data ) {

            var data = {
                action:   action,
                order_id: woocommerce_admin_meta_boxes.post_id,
                security: woocommerce_admin_meta_boxes.order_item_nonce
            };

            $.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {

                if ( typeof response.data == 'undefined' ) {
                    window.alert( 'An error occours' );
                }
                else if ( true === response.success && 'OK' === response.data.status ) {
                    // Redirect to same page for show the refunded status
                    window.location.href = window.location.href;
                    return;
                }
                else {
                    window.alert( response.data.error );
                }
            });

        }

        $( '#woocommerce-order-items' )
            .on( 'click', 'button.gestpay-settle-items', function() {
                if ( window.confirm( "<?php echo $gp_strings['confirm_settle']; ?>" ) ) {
                    gestpay_ajax_call( 'gestpay_settle_s2s' );
                }
            })
            .on( 'click', 'button.gestpay-delete-items', function() {
                if ( window.confirm( "<?php echo $gp_strings['confirm_delete']; ?>" ) ) {
                    gestpay_ajax_call( 'gestpay_delete_s2s' );
                }
            });

    })(jQuery)
    </script>

    <?php
}
// Add externally to the class to prevent multiple loadings.
add_action( 'woocommerce_order_item_add_action_buttons', 'gestpay_order_actions_add_action_buttons' );

