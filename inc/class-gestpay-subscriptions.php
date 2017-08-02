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

class Gestpay_Subscriptions {

    public function __construct( $gestpay ) {

        // Get a pointer to the main class and to the helper.
        $this->Gestpay    = $gestpay;
        $this->Helper     = $this->Gestpay->Helper;
        $this->textdomain = $this->Gestpay->textdomain;

        // Used when scheduling a payment: some stuff must not be executed.
        $this->is_scheduled_payment = FALSE;

        $this->Cards = new Gestpay_Cards( $gestpay );
        $this->saved_cards = $this->Cards->get_cards();

        if ( $this->Helper->is_subscriptions_active() && ! $this->Gestpay->is_3ds_enabled ) {
            /*
                NOTES

                Recurring payments can only be processed if the account has the 3D Secure disables
                because the user can't insert the secure code when payments runs on background.
             */

            // process scheduled subscription payments
            add_action( 'woocommerce_scheduled_subscription_payment_wc_gateway_gestpay',
                array( $this, 'process_subscription_renewal_payment' ), 10, 2 );

            // display the current payment method used for a subscription in the "My Subscriptions" table
            add_filter( 'woocommerce_my_subscriptions_payment_method',
                array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );
        }

        add_filter( 'woocommerce_api_order_response',
            array( $this, 'add_token_data' ), 10, 2 );

    }


    /**
     * Do the payment through S2S
     */
    public function s2s_payment( $order, $args = array() ) {

        $transaction_id = '';
        $order_id = $this->Helper->order_get( $order, 'id' );

        if ( ! $client = $this->Helper->get_soap_client( $this->Gestpay->ws_S2S_url ) ) return FALSE;

         // Maybe overwrite amount (for subscription)
        $override_amount = FALSE;
        if ( ! empty( $args['amount'] ) ) {
            $override_amount = $args['amount'];
        }

        // Set required parameters and add the Token to them
        $params = $this->Gestpay->get_base_params( $order, $override_amount, FALSE );

        if ( ! empty( $args['token'] ) ) {
            // S2S Payment Phase 1 with Token
            $params->tokenValue = $args['token'];
        }
        elseif ( ! empty( $args['pares'] ) ) {
            // S2S Payment Phase 3
            $params->transKey = get_post_meta( $order_id, GESTPAY_ORDER_META_TRANS_KEY, TRUE );
            $params->PARes = $args['pares'];
        }
        else {
            // S2S Payment Phase 1 without Token
            $params->cardNumber  = $this->Helper->get_post( 'gestpay-cc-number' );
            $params->expiryMonth = $this->Helper->get_post( 'gestpay-cc-exp-month' );
            $params->expiryYear  = $this->Helper->get_post( 'gestpay-cc-exp-year' );

            if ( $this->Gestpay->is_cvv_required ) {
                $params->cvv     = $this->Helper->get_post( 'gestpay-cc-cvv' );
            }
        }

        // Maybe overwrite shopTransactionId (for subscription)
        if ( ! empty( $args['shopTransactionId'] ) ) {
            $params->shopTransactionId = $args['shopTransactionId'];
        }

        $log_params = clone $params;
        if ( ! empty( $log_params->cardNumber ) ) {
            $log_params->cardNumber = substr_replace( $log_params->cardNumber, '**********', 2, -4 );
        }
        $this->Helper->log_add( '[s2s_payment]: Parameters:', $log_params );

        // Do the request to retrieve the token
        try {
            $response = $client->callPagamS2S( $params );
        }
        catch ( Exception $e ) {
            $err = sprintf( $this->Gestpay->strings['soap_req_error'], $e->getMessage() );
            if ( ! $this->is_scheduled_payment ) {
                $this->Helper->wc_add_error( $err );
            }
            $this->Helper->log_add( '[ERROR]: ' . $err );

            return FALSE;
        }

        $this->Helper->log_add( '[S2S RESPONSE]:', $response );

        $xml_response = simplexml_load_string( $response->callPagamS2SResult->any );

        // Store Transaction Key for being used on Phase III.
        if ( ! empty( $xml_response->TransactionKey ) ) {
            $transaction_id = (string)$xml_response->TransactionKey;
            update_post_meta( $order_id, GESTPAY_ORDER_META_TRANS_KEY, $transaction_id );
        }

        // Store Bank TID for being used on Refund.
        if ( ! empty( $xml_response->BankTransactionID ) ) {
            $bank_tid = (string)$xml_response->BankTransactionID;
            update_post_meta( $order_id, GESTPAY_ORDER_META_BANK_TID, $bank_tid );
        }

        if ( $xml_response->TransactionType == "PAGAM" && $xml_response->TransactionResult == "OK" ) {

            // --- Transactions made with non 3D-Secure cards

            if ( ! $this->is_scheduled_payment ) {

                $msg = sprintf( $this->Gestpay->strings['transaction_ok'], $transaction_id );
                $this->Helper->wc_order_completed( $order, $msg, $transaction_id );

                add_action( 'the_content', array( &$this, 'show_message' ) );
            }
            elseif ( ! empty( $bank_tid ) && ! empty( $transaction_id ) ) {
                // Store these infos to the scheduled order.
                $order->add_order_note( "Bank TID: $bank_tid / Trans. key: $transaction_id" );
            }

            return array(
                'pay_result' => 'OK'
            );
        }
        elseif ( $xml_response->TransactionType == "PAGAM" && $xml_response->TransactionResult == "KO" ) {
         
            // --- Transactions made with 3D-Secure cards

            if ( $xml_response->ErrorCode == '8006' ) {

                // -- Phase I: authorization request OK

                return array(
                    'VbVRisp' => (string)$xml_response->VbV->VbVRisp,
                );
            }
            else {

                // -- Error

                $err = sprintf( $this->Gestpay->strings['payment_error'], $xml_response->ErrorCode, $xml_response->ErrorDescription );
                if ( ! $this->is_scheduled_payment ) {
                    $this->Helper->wc_add_error( $err );
                }

                $this->Helper->log_add( '[ERROR]: ' . $err );

                return array(
                    'pay_result' => 'KO',
                    'error'      => TRUE,
                    'error_code' => $xml_response->ErrorCode,
                    'error_desc' => $xml_response->ErrorDescription
                );
            }

        }

    }

    /**
     * Get the ID of the parent order for a subscription renewal order.
     *
     * @param WC_Order The WC_Order object
     *
     * @return int
     */
    private function get_renewal_parent_order_id( $renewal_order ) {

        if ( ! is_object( $renewal_order ) ) {
            $renewal_order = new WC_Order( $renewal_order );
        }

        $subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order );
        $subscription  = array_pop( $subscriptions );

        if ( version_compare( WC_VERSION, '2.6.15', '<' ) ) {
            $parent_order = $subscription->order;
        }
        else {
            $parent_order = $subscription->get_parent();
        }

        if ( false === $parent_order ) { // There is no original order
            throw new Exception( 'Gestpay S2S Exception: Can\'t find the main order.' );
        }
        else {
            return wc_gp_get_order_id( $parent_order );
        }

    }

    /**
     * Process subscription renewal
     *
     * Gestpay Pro with Tokenization have no support for recurring payments, but it allows to charge
     * a stored credit card (using a masked token) if and only if 3D-Secure is NOT enabled.
     * 
     * For each subscription, a `woocommerce_scheduled_subscription_payment_{payment_gateway_id}`
     * hook is fired whenever a payment is due, so we can hook on it to charge the next payment.
     *
     * @see
     * - http://docs.woothemes.com/document/subscriptions/develop/payment-gateway-integration/
     * - https://docs.woocommerce.com/document/testing-subscription-renewal-payments/
     *
     * @param float $amount_to_charge
     *      subscription amount to charge: could include multiple renewals if they've previously failed and the admin has enabled it
     * @param \WC_Order $renewal_order
     *      original order containing the subscription
     */
    public function process_subscription_renewal_payment( $amount_to_charge, $renewal_order ) {

        $renewal_order_id = $this->Helper->order_get( $renewal_order, 'id' );

        // Be sure to process a renewal order.
        if ( ! wcs_order_contains_renewal( $renewal_order_id ) ) {
            $err = 'Not a renewal order.';
            $this->renewal_payment_failure( $renewal_order, $err );
        }

        // NEVER process an already paid order!
        $order = new WC_Order( $renewal_order_id );
        if ( ! ( $order->needs_payment() || $order->has_status( array( 'on-hold', 'failed', 'cancelled' ) ) ) ) {
            return FALSE;
        }

        if ( ! $this->Gestpay->save_token ) {
            $err = 'Tokens are disabled but they must be enabled to process subscriptions.';
            $this->renewal_payment_failure( $renewal_order, $err );
        }

        $parent_order_id = $this->get_renewal_parent_order_id( $renewal_order );

        $token = get_post_meta( $parent_order_id, GESTPAY_META_TOKEN, TRUE );

        if ( empty( $token ) ) {
            $err = 'Token not provided.';
            $this->renewal_payment_failure( $renewal_order, $err );
        }

        $this->is_scheduled_payment = TRUE;

        // Maybe refund the amount used on the first trial order.
        if ( $gestpay_fix_amount_zero = get_post_meta( $parent_order_id, GESTPAY_ORDER_META_AMOUNT, TRUE ) ) {

            $refund_res = $this->Gestpay->Order_Actions->refund( $parent_order_id, $gestpay_fix_amount_zero, 'Write-Off' );

            if ( ! $refund_res ) {
                // If the order can't be refunded, probabily the merchant is using MOTO with
                // separation, so we can try to settle and then refund.
                $settle_res = $this->Gestpay->Order_Actions->settle( $parent_order_id, $gestpay_fix_amount_zero );
                if ( $settle_res === TRUE ) {
                    $refund_res = $this->Gestpay->Order_Actions->refund( $parent_order_id, $gestpay_fix_amount_zero, 'Write-Off' );
                }
            }
            
            // Remove order meta so it will not be processed anymore.
            delete_post_meta( $parent_order_id, GESTPAY_ORDER_META_AMOUNT );

            // Log the write off
            $this->Helper->log_add( $this->Gestpay->strings['fix_0_writeoff'] . " - Order {$renewal_order_id}" );

            $parent_order = wc_get_order( $parent_order_id );

            if ( $refund_res !== TRUE ) {
                $refund_err = "Rimborso di 1 centesimo fallito: è necessario effettuarlo manualmente dal backoffice Gestpay.";
                $parent_order->add_order_note( $refund_err );
                $this->Helper->log_add( $refund_err );
            }
            else {
                // Set parent order as refunded.
                $parent_order->update_status( 'refunded' );
            }
        }

        $this->Helper->log_add( '=========== processing subscription renewal payment' );

        // Do the payment through S2S
        $response = $this->s2s_payment( $renewal_order,
            array(
                'token'             => $token,
                'amount'            => number_format( (float)$amount_to_charge, 2, '.', '' ),
                'shopTransactionId' => $renewal_order_id
            )
        );

        if ( ! empty( $response['pay_result'] ) && $response['pay_result'] == "OK" ) {
            // Add order note and update the subscription.
            $renewal_order->add_order_note( $this->Gestpay->strings['subscr_approved'] );
            $this->Helper->log_add( $this->Gestpay->strings['subscr_approved'] );
            WC_Subscriptions_Manager::process_subscription_payments_on_order( $parent_order_id );

            return TRUE;
        }
        else {
            $err = 'An error occours on s2s_payment.';

            if ( ! empty( $response['error_code'] ) && ! empty( $response['error_desc'] ) ) {
                $err.= ' '. $response['error_code'].': '.$response['error_desc'];
            }
            elseif ( ! empty( $response['VbVRisp'] ) ) {
                $err.= " You are trying to force a recurring payment but the 3D Secure protocol is enabled.";
            }

            $this->renewal_payment_failure( $renewal_order, $err );
        }

    }

    /**
     * Mark the given order as failed, add an order note and throw an exception.
     *
     * @param object $order the \WC_Order object
     * @param string $message a message to display inside the "Payment Failed" order note
     */
    public function renewal_payment_failure( $order, $message = '' ) {

        $order_err = 'Gestpay S2S Error: ' . __( $message, $this->textdomain );

        WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order );
        $this->Helper->log_add( $order_err );
        $order->add_order_note( $order_err );

        throw new Exception( $order_err );

    }

    /**
     * Render the payment method used for a subscription in the "My Subscriptions" table
     *
     * @param string $payment_method_to_display the default payment method text to display
     * @param array $subscription_details the subscription details
     *
     * @return string the subscription payment method
     */
    public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {

        foreach ( $this->saved_cards as $card ) {
            $show_card = substr_replace( $card['token'], '**********', 2, -4 );
            $payment_method_to_display = sprintf( __( 'Via %s %s/%s', $this->textdomain ),
                $show_card,
                $card['month'],
                $card['year']
            );
        }

        return $payment_method_to_display;

    }

    /**
     * Add token data
     *
     * @param array $order_data
     * @param object $order the \WC_Order object
     *
     * @return array
     */
    public function add_token_data( $order_data, $order ) {

        if ( ! $this->Gestpay->save_token ) {
            throw new Exception( 'Gestpay S2S Exception: Saving token is disabled' );
        }

        $token = get_post_meta( $this->Helper->order_get( $order, 'id' ), GESTPAY_META_TOKEN, TRUE );
        if ( ! empty( $token ) ) {
            $order_data['gestpay_token'] = $token;
        }

        return $order_data;

    }

}