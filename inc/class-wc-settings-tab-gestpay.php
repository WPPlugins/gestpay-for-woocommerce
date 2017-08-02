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

class WC_Settings_Tab_Gestpay {

    private static $gestpay;

    /**
     * Bootstraps the class and hooks required actions & filters.
     */
    public static function init( $gestpay ) {
        self::$gestpay = $gestpay;

        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_settings_tab_gestpay', __CLASS__ . '::output' );
        add_action( 'woocommerce_update_options_settings_tab_gestpay', __CLASS__ . '::update_settings' );
    }
    
    /**
     * Add a new settings tab to the WooCommerce settings tabs array.
     *
     * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
     * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
     */
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['settings_tab_gestpay'] = 'Gestpay for WooCommerce';
        return $settings_tabs;
    }

    /**
     * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
     *
     * @uses woocommerce_update_options()
     * @uses self::get_settings()
     */
    public static function update_settings() {
        woocommerce_update_options( self::get_settings() );
    }

    /**
     * Get the real IP address of the current website so that it can be
     * used into the Gestpay backoffice.
     * It uses an external service to find out the IP address.
     */
    public static function get_IP_address() {
        $ip = wp_remote_retrieve_body( wp_remote_get( 'http://icanhazip.com/' ) );
        if ( preg_match( '/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $ip ) ) {
            return 'Indirizzo IP da utilizzare nel backoffice di Gestpay: <b style="font-size:18px">' . $ip . '</b>';
        }

        return "L'identificazione dell'indirizzo IP è fallita. Prova ad riaggiornare la pagina o contatta il tuo provider di hosting per conoscere l'IP.";
    }

    /**
     * Checks for some errors when WC Subscriptions is active and Gestpay is on tokenization mode.
     */
    private static function maybe_show_admin_errors() {

        if ( ! self::$gestpay->has_fields ) {
            // Not using tokenization.
            return;
        }
    	
        if ( self::$gestpay->Helper->is_subscriptions_active() && self::$gestpay->is_3ds_enabled ) : ?>

        <div class="error">
            <p>Attenzione! WooCommerce Subscriptions è attivo ma GestPay è abilitato per l'utilizzo del protocollo 3D Secure e per questo motivo i rinnovi automatici non verranno effettuati! Se si vuole utilizzare GestPay per effettuare i pagamenti ricorrenti è necessario che il protocollo 3D Secure sia disabilitato: per farlo è necessario rivolgersi al supporto tecnico di GestPay.</p>
        </div>

        <?php endif; ?>

        <?php if ( class_exists('WC_Subscriptions') && WC_Subscriptions::is_duplicate_site() ) :

            // @see https://docs.woocommerce.com/document/subscriptions-handles-staging-sites/
            ?>

        <div class="error">
            <p>Attenzione! WooCommerce Subscriptions viene considerato come sito duplicato: i pagamenti automatici verranno considerati come rinnovi manuali e quindi falliranno.</p>
        </div>

        <?php endif; ?>

        <?php if ( self::$gestpay->Helper->is_subscriptions_active() && ! self::$gestpay->save_token ) : ?>

        <div class="error">
            <p>Attenzione! WooCommerce Subscriptions è attivo ma GestPay è configurato per non memorizzare i Token: i pagamenti ricorrenti non potranno essere processati. Per poterli processare è necessario abilitare il salvataggio del Token.</p>
        </div>

        <?php endif;
    }

    /**
     * Output the settings and add some custom JS.
     */
    public static function output() {

    	self::maybe_show_admin_errors();

        WC_Admin_Settings::output_fields( self::get_settings() );

        ?>
        <script>(function($) {
            // Show/Hide the Pro section
            $( '#wc_gestpay_account_type' ).change(function() {
                var selAccount = $( '#wc_gestpay_account_type option:selected' ).val();
                var $pro = $( '#wc_gestpay_param_buyer_email, #wc_gestpay_param_payment_types, #wc_gestpay_param_tokenization_save_token' ).closest( 'table' );

                if ( selAccount == '0' ) {
                    $pro.hide(); // table
                    $pro.prev().hide(); // p
                    $pro.prev().prev().hide(); // h2
                }
                else {
                    $pro.show(); // table
                    $pro.prev().show(); // p
                    $pro.prev().prev().show(); // h2

                    // Show/hide On-Site/iFrame parameters.
                    var $saveTokenRow = $( '#wc_gestpay_param_tokenization_save_token' ).closest( 'tr' );
                    var $cvvRow = $( '#wc_gestpay_param_tokenization_send_cvv' ).closest( 'tr' );
                    var $use3dsRow = $( '#wc_gestpay_param_tokenization_use_3ds' ).closest( 'tr' );

                    if ( selAccount != '1' ) {
                        $saveTokenRow.show();
                        $cvvRow.show();
                        $use3dsRow.show();
                    }
                    else {
                        $saveTokenRow.hide();
                        $cvvRow.hide();
                        $use3dsRow.hide();
                    }
                }

                $( '#wc_gestpay_param_payment_types' ).trigger( 'change' );

            }).trigger( 'change' );

            // Payment types change
            $( '#wc_gestpay_param_payment_types' ).change(function() {
                var $payTypes = $( '#wc_gestpaypro_bon' ).closest( 'table' );
                var selAccount = $( '#wc_gestpay_account_type option:selected' ).val();

                if ( $(this).attr( 'checked' ) && selAccount != '0' ) {
                    $payTypes.prev().show();
                    $payTypes.show();
                }
                else {
                    $payTypes.prev().hide();
                    $payTypes.hide();
                }
            }).trigger( 'change' );

        })(jQuery);</script>
        <?php
    }

    /**
     * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
     *
     * @return array Array of settings for @see woocommerce_admin_fields() function.
     */
    public static function get_settings() {
        $settings = array(

            // ------------------------------------------------- Main options
            array(
                'title' => 'Opzioni Gestpay',
                'desc' => '',
                'type' => 'title',
                'id' => 'section0',
            ),
            array(
                'title' => 'Versione account',
                'desc' => '<br>Seleziona la versione del tuo account Gestpay.<br>La versione On-Site consente di effettuare i pagamenti nella pagina del checkout e richiede che siano abilitati i servizi "Tokenization" e "Authorization".<br>La versione iFrame consente di effettuare i pagamenti nella pagina di pagamento di WooCommerce, senza abbandonare il sito: richiede che sia abilitato il servizio iFrame ed opzionalmente il servizio "Tokenization" (per i pagamenti ricorrenti).<br>Per maggiori informazioni visitare <a href="https://www.gestpay.it/gestpay/pricing/index.html" target="_blank">https://www.gestpay.it/gestpay/pricing/index.html</a>.',
                'default' => GESTPAY_STARTER,
                'type' => 'select',
                'options' => array(
                    GESTPAY_STARTER => "Gestpay Starter",
                    GESTPAY_PROFESSIONAL => "Gestpay Professional",
                    GESTPAY_PRO_TOKEN_AUTH => "Gestpay Professional On-Site",
                    GESTPAY_PRO_TOKEN_IFRAME => "Gestpay Professional iFrame",
                ),
                'id' => 'wc_gestpay_account_type',
            ),
            array(
                'title' => 'Gestpay Shop Login:',
                'type' => 'text',
                'desc' => "<br>Inserisci il tuo Shop Login fornito da Gestpay. Lo Shop Login è nella forma GESPAY12345 oppure 9012345, rispettivamente per l'ambiente di test e per quello di produzione.",
                'default' => '',
                'id' => 'wc_gestpay_shop_login',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'section0',
            ),

            // ------------------------------------------------- IP Address
            array(
                'title' => 'Indirizzo IP',
                'desc' => self::get_IP_address(),
                'type' => 'title',
                'id' => 'section1',
            ),
            array(
                'title' => 'URL per risposta positiva e negativa',
                'desc' => home_url( '/' ) . '?wc-api=WC_Gateway_Gestpay',
                'type' => 'title',
                'id' => 'section1a',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'section1',
            ),

            // ------------------------------------------------- Pro parameters
            array(
                'title' => 'Parametri opzionali di Gestpay Professional',
                'type' => 'title',
                'desc' => 'Nota: per abilitare/valorizzare tali parametri è necessario che siano stati abilitati anche nel backoffice di Gestpay, nella sezione "Campi&Parametri"',
                'id' => 'wc_gateway_gestpay_pro_parameters'
            ),
            array(
                'title' => 'Buyer E-mail:',
                'type' => 'checkbox',
                'default' => 'no',
                'id' => 'wc_gestpay_param_buyer_email',
            ),
            array(
                'title' => 'Buyer Name:',
                'type' => 'checkbox',
                'default' => 'no',
                'id' => 'wc_gestpay_param_buyer_name',
            ),
            array(
                'title' => 'Language:',
                'type' => 'checkbox',
                'default' => 'no',
                'desc' => "Permette di impostare automaticamente la lingua della pagina di pagamento di Gestpay (richiede qTranslate-X o WPML)",
                'id' => 'wc_gestpay_param_language',
            ),
            array(
                'title' => 'Custom Info:',
                'type' => 'textarea',
                'desc' => "Inserisci le tue informazioni personalizzate come parametro=valore, uno per ogni riga. Lo spazio e i seguenti caratteri non sono ammessi: & § ( ) * < > , ; : *P1* / /* [ ] ? = %",
                'default' => '',
                'id' => 'wc_gestpay_param_custominfo',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_gateway_gestpay_pro_parameters',
            ),

            // ------------------------------------------------- More gateways
            array(
                'title' => 'Tipi di pagamento di Gestpay Professional',
                'type' => 'title',
                'desc' => 'È possibile aggiungere separatamente anche i pagamenti anche attraverso altri metodi di pagamento. Questi devo essere stati abilitati da Gestpay.',
                'id' => 'wc_gateway_gestpay_pro_parameters_payment_types'
            ),
            array(
                'title' => 'Payment Types:',
                'type' => 'checkbox',
                'label' => 'Abilita il parametro "Payment Types"',
                'default' => 'no',
                'desc' => 'Se si utilizza il multi-gateway questo campo deve essere selezionato',
                'id' => 'wc_gestpay_param_payment_types',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_gateway_gestpay_pro_parameters_payment_types',
            ),

            array(
                'title' => '',
                'desc' => 'Con Gestpay Professional è possibile aggiungere pagine di pagamento differenti per differenti metodi di pagamento.<br>Seleziona qui quali modilità di pagamento abilitare; poi salva e infine vai nel tab "Cassa" per vedere abilitati i tipi di pagamento selezionati.<br>Si faccia riferimento al manuale per maggiori informazioni. Nota: i metodi di pagamento selezionati devono essere abilitati anche nel Backoffice Gestpay.',
                'type' => 'title',
                'id' => 'wc_gestpaypro_moregateways_options',
            ),
            array(
                'desc' => 'Bonifico',
                'id' => 'wc_gestpaypro_bon',
                'class' => 'wc_gestpaypro_moregateways',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'desc' => 'PayPal',
                'id' => 'wc_gestpaypro_paypal',
                'class' => 'wc_gestpaypro_moregateways',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'desc' => 'MyBank',
                'id' => 'wc_gestpaypro_mybank',
                'class' => 'wc_gestpaypro_moregateways',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'desc' => 'Consel',
                'id' => 'wc_gestpaypro_consel',
                'class' => 'wc_gestpaypro_moregateways',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'desc' => 'MasterPass',
                'id' => 'wc_gestpaypro_masterpass',
                'class' => 'wc_gestpaypro_moregateways',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_gestpaypro_moregateways_options',
            ),

            // ------------------------------------------------- Auth/iFrame options
            array(
                'title' => 'Impostazioni extra',
                'type' => 'title',
                'desc' => '',
                'id' => 'wc_gateway_gestpay_pro_extra_options'
            ),

            array(
                'title' => 'Memorizza Token',
                'type' => 'checkbox',
                'desc' => 'Se selezionato memorizza il token e consente di effettuare i pagamenti ricorrenti tramite WooCommerce Subscriptions. Se non selezionato, nessun token verrà mai memorizzato nel sistema e di conseguenza se si sta utilizzando WooCommerce Subscriptions i pagamenti ricorrenti non potranno essere processati. Si consiglia di lasciare sempre attiva l\'opzione se si utilizza WooCommerce Subscriptions.',
                'id' => 'wc_gestpay_param_tokenization_save_token',
                'default' => 'no',
            ),
            array(
                'title' => 'CVV',
                'type' => 'checkbox',
                'desc' => 'Invia anche il campo CVV (Card Verification Value) quando viene effettuata la richiesta del token. ATTENZIONE: se il campo è impostato come <i>Input</i> nel Back Office di Gestpay, questa opzione deve essere selezionata altrimenti si otterrà un errore.',
                'id' => 'wc_gestpay_param_tokenization_send_cvv',
                'default' => 'no',
            ),
            array(
                'title' => '3D Secure',
                'type' => 'checkbox',
                'desc' => 'Se selezionato, il 3D Secure deve essere abilitato per questo negozio. ATTENZIONE! Se avete aderito ai servizi 3D-Secure, avete la garanzia della non ripudiabilità della transazione da parte del titolare di carta ma questa funzionalità impedisce di processare i pagamenti automatici (perché nel momento del pagamento automatico non si può richiedere il codice 3D secure); questo comporta che i pagamenti ricorrenti delle subscriptions non possano essere processati (o meglio verranno contrassegnati come falliti).',
                'id' => 'wc_gestpay_param_tokenization_use_3ds',
                'default' => 'yes',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_gateway_gestpay_pro_extra_options',
            ),

            // ------------------------------------------------- Test
            array(
                'title' => "Test del Gateway",
                'type' => 'title',
                'desc' => '',
                'id' => 'wc_gestpay_testing',
            ),
            array(
                'title' => "Modalità sandbox/test:",
                'type' => 'checkbox',
                'label' => "Abilita la modalità di test quando selezionato.",
                'desc' => "Se selezionato (default), il checkout verrà processato con l'indirizzo di test, altrimenti con quello reale.",
                'default' => 'yes',
                'id' => 'wc_gestpay_test_url',
            ),
            array(
                'title' => 'Debug Log:',
                'type' => 'checkbox',
                'label' => "Abilita la registrazione degli eventi",
                'default' => 'yes',
                'desc' => 'Memorizza alcuni eventi di Gestpay nel file di log.',
                'id' => 'wc_gestpay_debug',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_gestpay_testing',
            ),

            // ------------------------------------------------- Experimental
            array(
                'title' => "Funzionalità sperimentali",
                'type' => 'title',
                'desc' => '',
                'id' => 'wc_gestpay_experimental',
            ),
            array(
                'title' => 'Forza verifica risposta',
                'type' => 'checkbox',
                'label' => ' ',
                'desc' => 'Se selezionato, verrà forzata la verifica della risposta restituita da Gestpay. <strong>Si consiglia di utilizzare questa opzione solo in caso di problemi con l\'aggiornamento dello stato dell\'ordine</strong>.',
                'default' => 'no',
                'id' => 'wc_gestpay_force_check_gateway_response',
            ),
            array(
                'title' => "Forza ri-cifratura",
                'type' => 'checkbox',
                'label' => ' ',
                'default' => 'no',
                'desc' => "Se selezionato, verrà forzata la ri-cifratura: in alcuni casi può essere utile forzare la ri-cifratura della stringa inviata al server Gestpay. <strong>Attenzione: questa è una funzionalità sperimentale! Attivare questa funzione solo se si è consci di cosa si sta facendo.</strong>",
                'id' => 'wc_gestpay_force_recrypt',
            ),
            array(
                'type' => 'sectionend',
                'id' => 'wc_gestpay_experimental',
            ),
        );

        return apply_filters( 'gestpay_settings_tab', $settings );
    }

}