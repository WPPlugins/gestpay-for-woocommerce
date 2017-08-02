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

add_action( 'init', 'wc_gateway_gestpay_s2s_init' );
function wc_gateway_gestpay_s2s_init() {

    add_action( 'wp_ajax_gestpay_s2s_delete_card',
        array( 'Gestpay_Cards', 'ajax_delete_card' ) );

    add_action( 'wp_ajax_gestpay_s2s_set_default_card',
        array( 'Gestpay_Cards', 'ajax_set_default_card' ) );

    add_action( 'wp_ajax_gestpay_s2s_unset_default_card',
        array( 'Gestpay_Cards', 'ajax_unset_default_card' ) );

}

class Gestpay_Cards {

    /**
     * Plugin actions.
     */
    public function __construct( $gestpay ) {

        $this->Gestpay = $gestpay;
        $this->current_user_id = get_current_user_id();

        add_action( 'woocommerce_account_' . GESTPAY_ACCOUNT_TOKENS_ENDPOINT . '_endpoint',
            array( $this, 'endpoint_content' ) );

    }

    /**
     * Endpoint HTML content.
     */
    public function endpoint_content() {
        
        // Variables used inside the template "my-cards"

        $trans_str  = $this->Gestpay->strings;
        $plugin_url = $this->Gestpay->plugin_url;
        $cards      = $this->get_cards();

        // icons
        $delete_img     = esc_attr( $plugin_url . 'images/delete.png' );
        $checked_img    = esc_attr( $plugin_url . 'images/checked.png' );
        $unchecked_img  = esc_attr( $plugin_url . 'images/unchecked.png' );
        $loading_img    = esc_attr( $plugin_url . 'images/loader.gif' );

        $default_cc = get_user_meta( $this->current_user_id, '_wc_gestpay_cc_default', true );

        include_once apply_filters( 'gestpay_my_cards_template', 'my-cards.php' );

    }

    public function can_use_token() {
        return is_user_logged_in() ? $this->Gestpay->save_token : false;
    }

    public function get_cards() {
        return $this->can_use_token() ? get_user_meta( $this->current_user_id, GESTPAY_META_TOKEN, true ) : array();
    }

    /**
     * Adds a card to the user meta so it can be shown at checkout.
     */
    public function save_card( $card ) {

        if ( $this->can_use_token() ) {
            if ( $cards = get_user_meta( $this->current_user_id, GESTPAY_META_TOKEN, true ) ) {
                if ( ! isset( $cards[$card['token']] ) ) {
                    update_user_meta( $this->current_user_id, GESTPAY_META_TOKEN, ( $cards + array( $card['token'] => $card ) ) );
                }
            }
            else {
                update_user_meta( $this->current_user_id, GESTPAY_META_TOKEN, array( $card['token'] => $card ) );
            }
        }

    }

    public static function ajax_delete_card() {

        if ( isset( $_POST['token'] ) ) {
            $token = $_POST['token'];
            $uid = get_current_user_id();

            if ( $cards = get_user_meta( $uid, GESTPAY_META_TOKEN, true ) ) {
                if ( isset( $cards[$token] ) ) {
                    unset( $cards[$token] );
                    update_user_meta( $uid, GESTPAY_META_TOKEN, $cards );

                    // May we have to delete also the token from all order metas? For now not.
                }
            }
        }

        wp_die();

    }

    public static function ajax_set_default_card() {

        if ( isset( $_POST['token'] ) ) {
            update_user_meta( get_current_user_id(), '_wc_gestpay_cc_default', $_POST['token'] );
        }

        wp_die();

    }

    public static function ajax_unset_default_card() {

        if ( isset( $_POST['token'] ) ) {
            delete_user_meta( get_current_user_id(), '_wc_gestpay_cc_default' );
        }

        wp_die();
        
    }
}