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
 * Handle the iFrame version.
 */
class Gestpay_Iframe {

    public function __construct( $gestpay ) {

        // Get a pointer to the main class and to the helper.
        $this->Gestpay = $gestpay;
        $this->Helper  = $gestpay->Helper;
        $this->can_have_cards = FALSE;

        include_once 'class-gestpay-subscriptions.php';
        $this->Subscr = new Gestpay_Subscriptions( $gestpay );
        
    }

    /**
     * Load the encoded string.
     *
     * At the first page loading, the encoded string is generated calling the Gestpay WS
     * and saved into a Cookie.
     * Then, after the 3DSecure authentication, the page is loaded again, and to prevent
     * another generation of the encoded string, we load it from the previously created Cookie.
     * In the check_gateway_response() of gestpay-for-woocommerce.php we must be sure the Cookie
     * is cleaned up after the payment or there will be an error in the next order (if the cookie
     * is not already expired by itself).
     */
    public function retrieve_encoded_string( $order ) {

        if ( empty( $_COOKIE[GESTPAY_IFRAME_COOKIE_B_PAR] ) ) {
            // First call
            $input_params = $this->Gestpay->get_ab_params( $order );
            
            if ( empty( $input_params['b'] ) ) {
                return FALSE;
            }

            setcookie( GESTPAY_IFRAME_COOKIE_B_PAR, $input_params['b'], time()+1200, COOKIEPATH, COOKIE_DOMAIN );

            return $input_params['b'];
        }
        else {
            // Second call
            return $_COOKIE[GESTPAY_IFRAME_COOKIE_B_PAR];
        }

    }

    /**
     * Generate the receipt page
     */
    public function receipt_page( $order ) {

        $encString = $this->retrieve_encoded_string( $order );

        // Maybe get the paRes parameter for 2nd call, due to 3D enrolled credit card
        $paRes = ! empty( $_REQUEST["PaRes"] ) ? $_REQUEST["PaRes"] : "";
        $transKey = ! empty( $_COOKIE[GESTPAY_IFRAME_COOKIE_T_KEY] ) ? $_COOKIE[GESTPAY_IFRAME_COOKIE_T_KEY] : "";

        // Output the HTML for the iFrame payment box.
        require_once 'checkout-payment-fields.php';
        ?>

        <script type="text/javascript" src="<?php echo $this->Gestpay->iframe_url; ?>"></script>
        <script type="text/javascript">
        var GestpayIframe = {}

        /**
         * Handle asynchronous security check result for the 1st and 2nd page load.
         */
        GestpayIframe.PaymentPageLoad = function( Result ) {
            // Check for errors: if the Result.ErroCode is 10 the iFrame
            // is created correctly and the security check are passed
            if ( Result.ErrorCode == 10 ) {

                // Handle 3D authentication 2nd call
                var paRes = '<?php echo $paRes; ?>';
                var transKey = '<?php echo $transKey; ?>';

                if ( paRes.length > 0 && transKey.length > 0 ) {
                    // The cardholder land for the 2nd page load, after 3D Secure authentication,
                    // so we can proceed to process the transaction without showing the form

                    document.getElementById( 'gestpay-inner-freeze-pane-text' ).innerHTML = '<?php echo $this->Gestpay->strings['iframe_pay_progress']; ?>';

                    var params = {
                        PARes: paRes,
                        TransKey: transKey
                    };

                    GestPay.SendPayment( params, GestpayIframe.PaymentCallBack );
                }
                else {
                    // 1st page load: show the form with the credit card fields
                    document.getElementById( 'gestpay-inner-freeze-pane' ).className = 'gestpay-off';
                    document.getElementById( 'gestpay-freeze-pane' ).className = 'gestpay-off';
                    document.getElementById( 'gestpay-cc-form' ).className = 'gestpay-on';
                }
            }
            else {
                GestpayIframe.OnError(Result);
            }
        };

        /**
         * Handle payment results.
         */
        GestpayIframe.PaymentCallBack = function ( Result ) {

            if ( Result.ErrorCode == 0 ) {
                // --- Transaction correctly processed

                var baseUrl = "<?php echo get_bloginfo( 'url' ); ?>/?wc-api=<?php echo GESTPAY_WC_API; ?>";

                // Decrypt the string to read the transaction results
                document.location.replace( baseUrl + '&a=<?php echo $this->Gestpay->shopLogin; ?>&b=' + Result.EncryptedString );
            }
            else {
                // --- An error has occurred: check for 3D authentication required

                if ( Result.ErrorCode == 8006 ) {
                    // The credit card is enrolled: we must send the card holder
                    // to the authentication page on the issuer website

                    var expDate = new Date();
                    expDate.setTime( expDate.getTime() + (1200000) );
                    expDate = expDate.toGMTString();

                    // Get the TransKey, IMPORTANT! this value must be stored for further use
                    var TransKey = Result.TransKey;
                    document.cookie = '<?php echo GESTPAY_IFRAME_COOKIE_T_KEY; ?>=' + TransKey.toString() + '; expires=' + expDate + ' ; path=/';

                    // Retrieve all parameters.
                    var a = '<?php echo $this->Gestpay->shopLogin; ?>'; 
                    var b = Result.VBVRisp;

                    // The landing page where the user will be redirected after the issuer authentication
                    var c = document.location.href;

                    // Redirect the user to the issuer authentication page
                    var AuthUrl = '<?php echo $this->Gestpay->pagam3d_url; ?>';

                    document.location.replace( AuthUrl + '?a=' + a + '&b=' + b + '&c=' + c );
                }
                else {
                    // Hide overlapping layer
                    document.getElementById( 'gestpay-inner-freeze-pane' ).className = 'gestpay-off';
                    document.getElementById( 'gestpay-freeze-pane' ).className = 'gestpay-off';  
                    document.getElementById( 'gestpay-submit' ).disabled = false;

                    // Check the ErrorCode and ErrorDescription
                    if ( Result.ErrorCode == 1119 || Result.ErrorCode == 1120 ) {
                        document.getElementById( 'gestpay-cc-number' ).focus();
                    }
                    else if ( Result.ErrorCode == 1124 || Result.ErrorCode == 1126 ) {         
                        document.getElementById( 'gestpay-cc-exp-month' ).focus();
                    }
                    else if ( Result.ErrorCode == 1125 ) {
                        document.getElementById( 'gestpay-cc-exp-year' ).focus();
                    }
                    else if ( Result.ErrorCode == 1149 ) {
                        <?php if ( $this->Gestpay->is_cvv_required ) : ?>
                        document.getElementById( 'gestpay-cc-cvv' ).focus();
                        <?php endif; ?>
                    }

                    GestpayIframe.OnError(Result);
                }
            }
        };

        GestpayIframe.OnError = function( Result ) {
            // Show the error box
            document.getElementById( 'gestpay-error-box' ).innerHTML = 'Error: ' + Result.ErrorCode +' - ' + Result.ErrorDescription;
            document.getElementById( 'gestpay-error-box' ).className = 'gestpay-on';
            document.getElementById( 'gestpay-inner-freeze-pane' ).className = 'gestpay-off';
            document.getElementById( 'gestpay-freeze-pane' ).className = 'gestpay-off';

            // Clean up cookies.
            document.cookie = '<?php echo GESTPAY_IFRAME_COOKIE_T_KEY; ?>' + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = '<?php echo GESTPAY_IFRAME_COOKIE_B_PAR; ?>' + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;';

            // Show the reload button
            document.getElementById( "iframe-reload-btn" ).style.display = 'inline-block';
        };

        /**
         * Send data to GestPay and process transaction.
         * @see gestpay-for-woocommerce/inc/checkout-payment-fields.php
         */
        function gestpayCheckCC() {

            document.getElementById( 'gestpay-submit' ).disabled = true;
            document.getElementById( 'gestpay-freeze-pane' ).className = 'gestpay-freeze-pane-on';
            document.getElementById( 'gestpay-inner-freeze-pane-text' ).innerHTML = '<?php echo $this->Gestpay->strings['iframe_pay_progress']; ?>';
            document.getElementById( 'gestpay-inner-freeze-pane' ).className = 'gestpay-on';

            var params = {
                CC    : document.getElementById( 'gestpay-cc-number' ).value
               ,EXPMM : document.getElementById( 'gestpay-cc-exp-month' ).value
               ,EXPYY : document.getElementById( 'gestpay-cc-exp-year' ).value
                <?php if ( $this->Gestpay->is_cvv_required ) : ?>
               ,CVV2  : document.getElementById( 'gestpay-cc-cvv' ).value
                <?php endif; ?>
            };

            GestPay.SendPayment( params, GestpayIframe.PaymentCallBack );

            return false;
        }

        // Check if the browser support HTML5 postmessage
        if ( BrowserEnabled ) {
            var a = '<?php echo $this->Gestpay->shopLogin; ?>';
            var b = '<?php echo $encString; ?>';

            // Create the iFrame
            GestPay.CreatePaymentPage( a, b, GestpayIframe.PaymentPageLoad );
            
            // Raise the Overlap layer and text
            document.getElementById( 'gestpay-freeze-pane' ).className = 'gestpay-freeze-pane-on';
            document.getElementById( 'gestpay-inner-freeze-pane-text' ).innerHTML = '<?php echo $this->Gestpay->strings['iframe_loading']; ?>';
            document.getElementById( 'gestpay-inner-freeze-pane' ).className = 'gestpay-on';
        }
        else {
            document.getElementById( 'gestpay-error-box' ).innerHTML = '<?php echo $this->Gestpay->strings['iframe_browser_err']; ?>';
            document.getElementById( 'gestpay-error-box' ).className = 'gestpay-on';
        }

        </script>

        <?php

    }

    /**
     * Clean up iframe cookies.
     */
    function delete_cookies() {
        
        setcookie( GESTPAY_IFRAME_COOKIE_B_PAR, "", 1, COOKIEPATH, COOKIE_DOMAIN );
        setcookie( GESTPAY_IFRAME_COOKIE_T_KEY, "", 1 );

    }

    /**
     * Maybe stores the token.
     */
    function maybe_save_token( $xml_response, $order ) {

        if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
            return;
        }

        if ( ! $this->Gestpay->save_token ) {
            $this->Gestpay->Helper->log_add( '[iFrame - maybe_save_token] TOKEN storage is disabled.' );
            return;
        }

        if ( empty( (string)$xml_response->TOKEN ) ) {
            $this->Gestpay->Helper->log_add( '[iFrame - maybe_save_token] xml_response does not contains the TOKEN' );
            return;
        }

        $order_id = $this->Helper->order_get( $order, 'id' );

        if ( ! wcs_order_contains_subscription( $order_id, 'any' ) ) {
            // With iFrame, there is no need to store the token if the order does not contains a subscription
            // because it will not be used to pay other orders as is possible with the On-Site version.
            $this->Gestpay->Helper->log_add( '[iFrame - maybe_save_token] Order does not contains a subscription: the TOKEN will not be saved.' );
            return;
        }

        $token = (string)$xml_response->TOKEN;

        // Store the token in the order
        update_post_meta( $order_id, GESTPAY_META_TOKEN, $token );

        // Do not save the token, as we don't process the first payment with the token (for now)
        /*
        $the_card = array(
            'token' => $token,
            'month' => (int)$xml_response->TokenExpiryMonth,
            'year'  => (int)$xml_response->TokenExpiryYear
        );

        // Maybe store the token to the users cards
        $this->Subscr->Cards->save_card( $the_card );
        */
    }

}