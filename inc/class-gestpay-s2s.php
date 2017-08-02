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

class Gestpay_S2S {

    public function __construct( $gestpay ) {

        // Get a pointer to the main class and to the helper.
        $this->Gestpay = $gestpay;
        $this->Helper = $gestpay->Helper;
        $this->can_have_cards = TRUE;

        include_once 'class-gestpay-subscriptions.php';
        $this->Subscr = new Gestpay_Subscriptions( $gestpay );
        
    }

    /**
     * Output a payment box containing your direct payment form
     */
    public function payment_fields() {
        include_once 'checkout-payment-fields.php';
    }

    /**
     * Process the payment and return the result.
     */
    public function process_payment( $order ) {

        if ( $this->Gestpay->save_token ) {
            $token = $this->get_token( $order );

            if ( ! $token ) {
                return FALSE;
            }

            $s2s_payment_params = array(
                'token' => $token
            );
        }
        else {
            $s2s_payment_params = array(
                'cardNumber'  => $this->Helper->get_post( 'gestpay-cc-number' ),
                'expiryMonth' => $this->Helper->get_post( 'gestpay-cc-exp-month' ),
                'expiryYear'  => $this->Helper->get_post( 'gestpay-cc-exp-year' )
            );
        }

        $this->Helper->log_add( '======= S2S Payment Phase 1 =======' );

        $s2s_response = $this->Subscr->s2s_payment( $order, $s2s_payment_params );

        if ( ! empty( $s2s_response['pay_result'] ) && $s2s_response['pay_result'] == "OK" ) {

            /*
            | ----------------------------------------------------------------------------------------------------------
            | == Transactions made with non 3D-Secure cards ==
            |
            | From a functional perspective the transaction is processed as a normal authorization request,
            | because these does not requires cardholder authentication.
            | ----------------------------------------------------------------------------------------------------------
            */

            /*
                If the request returns OK the order can be set as completed.
             */

            return array(
                'result'   => 'success',
                'redirect' => $this->Helper->wc_url( 'order_received', $order ),
            );

        }
        elseif ( ! empty( $s2s_response['VbVRisp'] ) ) {

            /*
            | ----------------------------------------------------------------------------------------------------------
            | == Transactions made with 3D-Secure cards ==
            | = Phase I: authorization request =
            | 
            | A standard authorization request is made. If the card is recognised as 3D, the outcome of the
            | request is a specific error code (8006) which is readable by means of the ErrorCode
            | method. The error description (Verified By Visa) will be readable by means of the
            | ErrorDescription method.
            | In this phase the following additional information is also shown. This information is required
            | during the payment process and is specific to Verified by Visa transactions. In particular it is
            | necessary to acquire the transaction code, which can be read by means of the TransKey
            | method and an encrypted string to be used during the subsequent phase and which is
            | readable by means of the VbVRisp value, which is as well in the XML return.
            | ----------------------------------------------------------------------------------------------------------
            */

            /*
                On the receipt page there is already the main form and for the Phase II we need to
                send the response which comes from Phase I through POST, and then we have to
                redirect the user to the 3D Secure page.
                For that reasons we use the wc-action to find out from which request comes from.
            */

            return array(
                'result'   => 'success',
                'redirect' => add_query_arg(
                    array(
                        'wc-action' => '3DSauth',
                        'VbVRisp'   => $s2s_response['VbVRisp']
                    ),
                    $this->Helper->wc_url( 'pay', $order )
                )
            );

        }

        if ( ! empty( $s2s_response['error_code'] ) && ! empty( $s2s_response['error_desc'] ) ) {
            $order->update_status( 'failed', 'Payment Error: ' . $s2s_response['error_code'] . ' ' . $s2s_response['error_desc'] );
        }

        return FALSE;

    }

    /**
     * Generate the receipt page
     */
    public function receipt_page( $order ) {

        if ( isset( $_GET['wc-action'] ) && $_GET['wc-action'] == '3DSauth' && ! empty( $_GET['VbVRisp'] ) ) {

            /*
            | ----------------------------------------------------------------------------------------------------------
            | == Transactions made with 3D-Secure cards ==
            | = Phase II: cardholder authentication =
            | 
            | In this phase it is necessary to allow the buyer to authenticate him/herself to the credit card
            | issuer. The buyer's browser must be redirected to a specific page on the Gestpay
            | website which will act as an interface for authentication and to direct the buyer to the
            | issuer's site, providing him/her with all of the information required for authentication.
            | The page must be retrieved through the following 3 parameters:
            | - a => shopLogin
            | - b => an encrypted string acquired in the previous phase through GetVbVRisp
            | - c => the URL of the merchant’s web site to which the buyer must be redirected after the authentication procedure
            | Any additional parameters will not be returned in the response to the second call.
            | At the end of the authentication process the buyer will be redirected to the merchant's site to
            | the URL specified as redirection parameter c.
            | The merchant's page for welcoming back the buyer after authentication will be retrieved
            | by means of a PARES parameter (an encrypted string containing the result of authentication)
            | which must be acquired by the merchant and forwarded to Gestpay during the following phase.
            | ----------------------------------------------------------------------------------------------------------
            */

            $input_params = array(
                'a' => $this->Gestpay->shopLogin,
                'b' => $_GET['VbVRisp'],
                'c' => add_query_arg(
                    array(
                        'wc-action' => 'checkVbV',
                        'order_id'  => $this->Helper->order_get( $order, 'id' ),
                    ),
                    $this->Gestpay->ws_S2S_resp_url
                )
            );

            $this->Helper->log_add( '======= S2S Payment Phase 2 ======= Redirect to 3D Secure auth page.' );

            echo $this->Helper->get_gw_form( $this->Gestpay->pagam3d_url, "post", $input_params, $order );
        }

    }

    /**
     * Handle Tokenization Phase III
     */
    public function phase_III_3D_Secure() {

        if ( isset( $_GET['wc-action'] ) && $_GET['wc-action'] == 'checkVbV'
            && ! empty( $_GET['order_id'] ) && ! empty( $_REQUEST['PaRes'] ) ) {

            /*
            | ----------------------------------------------------------------------------------------------------------
            | == Transactions made with 3D-Secure cards ==
            | = Phase III: conclusion of transaction =
            | 
            | At this stage the merchant is in possession of all of the information required to conclude
            | the transaction. A new authorization request must be made (by using the CallPagamS2S method).
            | However, before using again such call, it is necessary to assign to WSs2s all of the
            | information required by providing the variables:
            | - shopLogin (merchant code)
            | - uicCode (currency code)
            | - amount (amount)
            | - shopTransactionID (transaction identification code)
            | - transKey (transaction ID acquired during Phase I)
            | - PARes (encrypted string containing the result of authentication acquired during Phase II)
            | The result of the transaction displayed by Gestpay will be interpreted as depicted in the
            | Authorization Request section.
            | ----------------------------------------------------------------------------------------------------------
            */

            $order = new WC_Order( $_GET['order_id'] );
            if ( $order ) {

                $this->Helper->log_add( '======= S2S Payment Phase 3 =======' );

                $response = $this->Subscr->s2s_payment( $order, array( 'pares' => $_REQUEST['PaRes'] ) );

                header( "Location: " . $this->Helper->wc_url( 'order_received', $order ) );
                die();
            }

        }

    }

    public function get_token( $order ) {
        
        // Use the selected token if any
        $token = $this->Helper->get_post( 'gestpay-s2s-cc-token' );
        $order_id = $this->Helper->order_get( $order, 'id' );

        if ( ! empty( $token ) && $token != 'new-card' ) {

            $this->Helper->log_add( '[reusing token]: ' . $token );

            // Store the selected token in the order
            update_post_meta( $order_id, GESTPAY_META_TOKEN, $token );

            return $token;
        }
        else {
            // Request a new token
            $response = $this->s2s_token_request( $order );

            if ( ! empty( $response['token'] ) ) {
          
                // Store the token in the order
                update_post_meta( $order_id, GESTPAY_META_TOKEN, $response['token'] );

                // Maybe store the card to the users cards
                $this->Subscr->Cards->save_card( $response );

                return $response['token'];
            }
            else {
                if ( ! empty( $response['error_code'] ) && ! empty( $response['error_desc'] ) ) {
                    $order->update_status( 'failed', 'Request Token Error: ' . $response['error_code'] . ' ' . $response['error_desc'] );
                }
            }
        }

        return FALSE;

    }

    /**
     * Request a new token
     */
    public function s2s_token_request( $order ) {

        if ( ! ( $client = $this->Helper->get_soap_client( $this->Gestpay->ws_S2S_url ) ) ) return FALSE;

        // Set required parameters
        $params = new stdClass();
        $params->shopLogin     = $this->Gestpay->shopLogin;
        $params->requestToken  = "MASKEDPAN";
        $params->cardNumber    = $this->Helper->get_post( 'gestpay-cc-number' );
        $params->expiryMonth   = $this->Helper->get_post( 'gestpay-cc-exp-month' );
        $params->expiryYear    = $this->Helper->get_post( 'gestpay-cc-exp-year' );  // 2 digits
        $params->withAuth      = 'Y';
    
        // Maybe send also the CVV field.
        if ( $this->Gestpay->is_cvv_required ) {
            $params->cvv = $this->Helper->get_post( 'gestpay-cc-cvv' );
        }

        $log_params = clone $params;
        if ( ! empty( $log_params->cardNumber ) ) {
            // Hide card numbers.
            $log_params->cardNumber = substr_replace( $log_params->cardNumber, '**********', 2, -4 );
        }

        if ( ! empty( $log_params->cvv ) ) {
            $log_params->cvv = '***';
        }

        $this->Helper->log_add( '[S2S REQUEST]: ', $log_params );

        /*
            With "Tokenization" a merchant will be able to remotely store credit card data on the
            Gestpay archives and receive back a Token in answer; the merchant will save the received
            Token on in his system instead of the credit card data.
            For the next purchases, the merchant will send to GestPay the Token instead of the credit
            card number and Gestpay will use it to process the payment.
            This operation provides the generation of a new Token during a transaction passing the
            "requestToken" field, so they will obtain a Token in response.
            The callRequestTokens2S method sends to GestPay all previously assigned data, if the flag
            withAuth is set to Y then GestPay uses these data to make a transaction request without
            affecting the account and return the result of the operation to WSs2s, otherwise only the
            information about the card will be returned back.
        */

        // Do the request to retrieve the token
        try {
            $response = $client->callRequestTokenS2S( $params );
        }
        catch ( Exception $e ) {
            $err = sprintf( $this->Gestpay->strings['soap_req_error'], $e->getMessage() );
            $this->Helper->wc_add_error( $err );
            $this->Helper->log_add( '[ERROR]: ' . $err );

            return FALSE;
        }

        $this->Helper->log_add( '[S2S RESPONSE]: ', $response );

        $xml_response = simplexml_load_string( $response->CallRequestTokenS2SResult->any );

        /*
            Check if the encryption call can be accepted it is possible to use the TransactionResult
            method which will return the string "OK" if the check has been performed or the string "KO" if not.
            In the fields TransactionErrorCode and  TransactionErrorDescription there are the detailed information in case of error.
        */

        if ( $xml_response->TransactionType == "REQUESTTOKEN" && $xml_response->TransactionResult == "OK" ) {
            $token = (string) $xml_response->Token;
            return array(
                'token'  => $token,
                'month'  => (int) $xml_response->TokenExpiryMonth,
                'year'   => (int) $xml_response->TokenExpiryYear
            );
        }
        else {
            $err = '[' . $xml_response->TransactionErrorCode . '] ' . $xml_response->TransactionErrorDescription;
            $this->Helper->log_add( '[ERROR]: ' . $err );

            return FALSE;
        }

    }

}