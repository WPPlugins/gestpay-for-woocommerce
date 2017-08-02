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
 * Render the payment fields on checkout
 *
 * @see also https://developer.mozilla.org/en-US/docs/Web/Security/Securing_your_site/Turning_off_form_autocompletion
 * for using "nope" instead of "off" as a value of the attribute "autocomplete".
 */

$cards = $this->Subscr->saved_cards;

if ( $this->Gestpay->is_sandbox ) : ?>

    <p id="gestpay-s2s-sandbox">
        <small>
            <strong>Test Mode</strong><br>
            <a href="http://docs.gestpay.it/test/test-credit-cards.html" target="_blank">gestpay test cards</a>
        </small>
    </p>

<?php endif;

    $endpoint = get_permalink( wc_get_page_id( 'myaccount' ) ) . GESTPAY_ACCOUNT_TOKENS_ENDPOINT;
    $cc_is_checked = false;
    $has_cards = ! empty( $cards ) ? true : false;
?>

<style type="text/css">
    #gestpay-s2s-sandbox { float:left; width: 100%; padding: 10px 20px; background: #ececec }
    #gestpay-s2s-sandbox * { float: left; line-height: 1.5; }
    #payment ul.payment_methods li label[for='payment_method_gestpay-s2s'] img:nth-child(n+2) { margin-left:1px; }
</style>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('input.gestpay-s2s-card-selection:radio').change(
            function(){
                if ( $(this).val() != 'new-card' ) {
                    $('#gestpay-s2s-new-card').hide();
                }
                else {
                    $('#gestpay-s2s-new-card').show();
                }
            }
        );
    });
</script>

<fieldset id="user-saved-cards">

<?php if ( $has_cards && $this->can_have_cards ) : ?>

    <p class="form-row form-row-wide">

        <a class="button"
            style="width: 100%; text-align: center; margin: 0px 0 10px;"
            href="<?php echo $endpoint; ?>"
            target="_blank"><?php echo $this->Gestpay->strings['s2s_manage_cards']; ?></a>

        <div class="clear"></div>
        <?php
            $wc_gestpay_cc_default = get_user_meta( get_current_user_id(), '_wc_gestpay_cc_default', true );
            foreach ( $cards as $card ) :

                // Add asterisks to the token, because the alphabetic maskedpan is not useful for the user.
                $show_card = substr_replace( $card['token'], '**********', 2, -4 );
                if (!empty($wc_gestpay_cc_default) && $wc_gestpay_cc_default == $card['token']) {
                    $cc_is_checked = true;
                    $this_cc_is_checked = true;
                }
                else {
                    $this_cc_is_checked = false;
                }

                $expir_str = sprintf( $this->Gestpay->strings['s2s_card_expire'],
                    esc_html( $show_card ),
                    esc_html( $card['month'] ),
                    esc_html( $card['year'] )
                );
            ?>
            <input type="radio"
                id="gestpay-s2s-cc-token-<?php echo esc_attr( $card['token'] ); ?>"
                class="gestpay-s2s-card-selection"
                name="gestpay-s2s-cc-token"
                style="width:auto;display:inline-block;"
                value="<?php echo esc_attr( $card['token']); ?>" <?php checked( $this_cc_is_checked ); ?> />

            <label style="display:inline;"
                for="gestpay-s2s-cc-token-<?php echo esc_attr( $card['token'] ); ?>"><?php echo $expir_str; ?></label>

            <br />

        <?php endforeach; ?>
        <input type="radio"
            id="gestpay-s2s-use-new-card"
            class="gestpay-s2s-card-selection"
            name="gestpay-s2s-cc-token" <?php checked( ! $cc_is_checked ); ?>
            style="width:auto;display:inline-block;"
            value="new-card" />

        <label style="display:inline;" for="gestpay-s2s-use-new-card"><?php echo $this->Gestpay->strings['s2s_use_new_card']; ?></label>
    </p>

<?php endif; // end if $has_cards ?>

<?php $div_style = $cc_is_checked ? 'none' : 'block'; ?>

<?php if ( $this->Gestpay->is_iframe ) : ?>

    <div id="gestpay-freeze-pane" class="gestpay-off"></div>
    <div id="gestpay-inner-freeze-pane" class="gestpay-off">
        <div id="gestpay-inner-freeze-pane-text"></div>
    </div>
    <div id="gestpay-error-box" class="gestpay-off"><?php echo $this->Gestpay->strings['s2s_error']; ?></div>

    <form name="gestpay-cc-form" method="post" id="gestpay-cc-form" onsubmit="return gestpayCheckCC();" class="gestpay-off" autocomplete="off">

        <!-- fake fields are a workaround for chrome autofill getting the wrong fields -->
        <input style="display:none" type="text" name="fakeusernameremembered" />
        <input style="display:none" type="password" name="fakepasswordremembered" />

<?php endif; // end if $this->Gestpay->is_iframe ?>

    <div id="gestpay-s2s-new-card" style="display:<?php echo $div_style; ?>; margin-top: 10px;">

    <?php
    // --- credit card number
    woocommerce_form_field(
        'gestpay-cc-number',
        array(
            'type' => 'text',
            'label' => $this->Gestpay->strings['s2s_ccn'],
            'maxlength' => 20,
            'required' => true,
            'custom_attributes' => array(
                'autocomplete' => 'nope',
                'data-encrypted-name' => 'number',
                'style' => 'width:180px;'
            ),
        )
    );
    ?>

    <?php // --- expiration date ?>
    <p class="form-row validate-required">

        <label for="gestpay-cc-exp-date"><?php echo $this->Gestpay->strings['s2s_card_exp_date']; ?> <span class="required">*</span></label>

        <select name="gestpay-cc-exp-month" id="gestpay-cc-exp-month" class="woocommerce-select" style="width:auto;" data-encrypted-name="month">
            <option value=""><?php echo $this->Gestpay->strings['s2s_card_exp_month']; ?></option>
            <?php foreach ( range( 1, 12 ) as $month ) : ?>
                <option value="<?php printf( '%02d', $month ) ?>"><?php printf( '%02d', $month ) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="gestpay-cc-exp-year" id="gestpay-cc-exp-year" class="woocommerce-select" style="width:auto;" data-encrypted-name="year">
            <option value=""><?php echo $this->Gestpay->strings['s2s_card_exp_year']; ?></option>
            <?php foreach ( range( date( 'Y' ), date( 'Y' ) + 15 ) as $year ) : ?>
                <option value="<?php echo substr( $year, -2 ); ?>"><?php echo $year ?></option>
            <?php endforeach; ?>
        </select>

    </p>

    <?php

    if ( $this->Gestpay->is_cvv_required ) :

        // --- cvv
        woocommerce_form_field(
            'gestpay-cc-cvv',
            array(
                'type' => 'text',
                'label' => $this->Gestpay->strings['s2s_card_cvv'],
                'maxlength' => 4,
                'required' => true,
                'custom_attributes' => array(
                    'autocomplete' => 'nope',
                    'data-encrypted-name' => 'cvv',
                    'style' => 'width:60px;'
                ),
            )
        );

    endif;

    ?>

    </div>

<?php if ( $this->Gestpay->is_iframe ) : ?>

        <p class="form-row">
            <input type="submit" value="<?php echo $this->Gestpay->strings['s2s_proceed']; ?>" id="gestpay-submit" />
        </p>

    </form><!-- end #gestpay-cc-form -->

    <a href="javascript:window.location.reload(true)" id="iframe-reload-btn" class="btn" style="display: none;">Riprova</a>

<?php endif; // end if $this->Gestpay->is_iframe ?>

</fieldset>