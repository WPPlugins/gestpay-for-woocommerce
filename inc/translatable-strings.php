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

$t = 'gestpay-for-woocommerce';

// this will be assigned to WC_Gateway_Gestpay->strings[]
return array(

    "gateway_enabled" =>
        __( "Enable/Disable", $t ),

    "gateway_enabled_label" =>
        __( "Enable Gestpay when selected.", $t ),

    "gateway_title" =>
        __( "Title", $t ),

    "gateway_title_label" =>
        __( "The title of the payment method which the buyer sees at checkout.", $t ),

    "gateway_desc" =>
        __( "Description", $t ),

    "gateway_desc_label" =>
        __( "The description of the payment method which the buyer sees at checkout.", $t ),

    "gateway_consel_id" =>
        __( "Consel Merchant ID", $t ),

    "gateway_consel_code" =>
        __( "Cosel Merchant Code Convention", $t ),

    "gateway_overwrite_cards" =>
        __( "Overwrite card icons", $t ),

    "gateway_overwrite_cards_label" =>
        __( "Select the cards you want to display as an icon (note: the fact that they are really active or not depends on the Gestpay settings)", $t ),

    "crypted_string" =>
        __( "Crypted string", $t ),

    "crypted_string_info" =>
        __( "You are forcing the re-encryption process: this may cause multiple calls to the GestPay webservice.", $t ),

    "transaction_error" =>
        __( "Transaction for order %s failed with error %s", $t ),

    "transaction_thankyou" =>
        __( "Thank you for shopping with us. Your transaction %s has been processed correctly. We will be shipping your order to you soon.", $t ),

    "transaction_ok" =>
        __( "Transaction for order %s has been completed successfully.", $t ),

    "soap_req_error" =>
        __( "Fatal Error: Soap Client Request Exception with error %s", $t ),

    "payment_error" =>
        __( "Gestpay Error #%s on Payment phase: %s", $t ),

    "request_error" =>
        __( "There was an error with your request, please try again.", $t ),

    "iframe_pay_progress" =>
        __( "Payment in progress...", $t ),

    "iframe_loading" =>
        __( "Loading...", $t ),

    "iframe_browser_err" =>
        __( "Error: Browser not supported", $t ),

    "s2s_error" =>
        __( "Error", $t ),

    "s2s_card" =>
        __( "Card", $t ),

    "s2s_remove" =>
        __( "Remove", $t ),

    "s2s_default" =>
        __( "Default", $t ),

    "s2s_expire" =>
        __( "Expires", $t ),

    "s2s_token_add_default" =>
        __( "Set as default", $t ),

    "s2s_token_remove_default" =>
        __( "Remove from default", $t ),

    "s2s_token_delete" =>
        __( "Delete", $t ),

    "s2s_no_cards" =>
        __( "There is not yet any credit card saved.", $t ),

    "s2s_confirm_token_delete" =>
        __( "Are you sure you want to delete this card?", $t ),

    "s2s_card_expire" =>
        __( "%s (expires %s/%s)", $t ),

    "s2s_card_exp_date" =>
        __( "Expiration Date", $t ),

    "s2s_card_exp_month" =>
        __( "Month", $t ),

    "s2s_card_exp_year" =>
        __( "Year", $t ),

    "s2s_card_cvv" =>
        __( "Card Security Code", $t ),

    "s2s_proceed" =>
        __( "Proceed", $t ),

    "s2s_manage_cards" =>
        __( "Manage Your Cards", $t ),

    "s2s_use_new_card" =>
        __( "Use a new credit card", $t ),

    "s2s_ccn" =>
        __( "Credit Card Number", $t ),

    "refund_err_1" =>
        __( "Order can't be refunded: Bank Transaction ID not found.", $t ),

    "refund_err_2" =>
        __( "Order can't be refunded: Failed to get the SOAP client.", $t ),

    "refund_ok" =>
        __( "REFUND OK: Amount refunded %s", $t ),

    "delete_ok" =>
        __( "Authorized transaction deleted successfully [BankTransactionID: %s]", $t ),

    "button_settle" =>
        __( "Settle", $t ),

    "tip_settle" =>
        __( "You can do a financial confirmation of this authorized transaction if using the M.O.T.O. configuration with the separation between the authorization and the settlement phase.", $t ),

    "confirm_settle" =>
        __( "Are you sure you want to settle this authorized transaction?", $t ),

    "button_delete" =>
        __( "Delete", $t ),

    "confirm_delete" =>
        __( "Are you sure you want to delete this authorized transaction?", $t ),

    "tip_delete" =>
        __( "You can delete this authorized transaction if using the M.O.T.O. configuration with the separation between the authorization and the settlement phase.", $t ),

    "subscr_approved" =>
        __( "GestPay Subscription Renewal Payment Approved", $t ),

    "fix_0_writeoff" =>
        __( "Write-off €0.01", $t ),

);