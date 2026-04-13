<?php
/**
 * Setup Wizard — Configurazione guidata alla prima attivazione.
 *
 * 5 step con auto-save AJAX ad ogni campo:
 * 1. Chi sei (dati titolare, DPO)
 * 2. Cosa fa il tuo sito (finalità — autodetect plugin)
 * 3. Quali servizi usi (terze parti — autodetect scansione)
 * 4. Scegli il tema (scuro/chiaro/custom)
 * 5. Tutto pronto (crea pagine, attiva tutto)
 */

defined( 'ABSPATH' ) || exit;

class Scudo_Wizard {

    private const OPTION_DONE = 'scudo_wizard_done';

    public static function init(): void {
        // Mostra il wizard solo se non è già stato completato
        if ( ! get_option( self::OPTION_DONE ) && current_user_can( 'manage_options' ) ) {
            add_action( 'admin_notices', [ __CLASS__, 'notice' ] );
        }

        // Pagina wizard (registrata ma non nel menu)
        add_action( 'admin_menu', [ __CLASS__, 'add_page' ] );

        // AJAX auto-save
        add_action( 'wp_ajax_scudo_wizard_save', [ __CLASS__, 'ajax_save' ] );
        // AJAX finalizzazione
        add_action( 'wp_ajax_scudo_wizard_finish', [ __CLASS__, 'ajax_finish' ] );
        // AJAX autodetect
        add_action( 'wp_ajax_scudo_wizard_detect', [ __CLASS__, 'ajax_detect' ] );
    }

    /* ── Notice nell'admin ───────────────────────────────────────── */

    public static function notice(): void {
        $url = admin_url( 'admin.php?page=scudo-wizard' );
        ?>
        <div class="notice notice-info is-dismissible" style="padding:12px 16px;border-left-color:#2271b1;">
            <p style="font-size:14px;">
                <strong>Scudo</strong> — <?php esc_html_e( 'Benvenuto! Configura il tuo sito in 2 minuti con il wizard guidato.', 'scudo-cookie-privacy' ); ?>
                <a href="<?php echo esc_url( $url ); ?>" class="button button-primary" style="margin-left:12px;"><?php esc_html_e( 'Avvia configurazione', 'scudo-cookie-privacy' ); ?></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=scudo' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Configura manualmente', 'scudo-cookie-privacy' ); ?></a>
            </p>
        </div>
        <?php
    }

    /* ── Pagina wizard (hidden) ──────────────────────────────────── */

    public static function add_page(): void {
        add_submenu_page( null, 'Scudo Setup', 'Scudo Setup', 'manage_options', 'scudo-wizard', [ __CLASS__, 'render' ] );
    }

    /* ── AJAX: salva un campo ────────────────────────────────────── */

    public static function ajax_save(): void {
        check_ajax_referer( 'scudo_wizard_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $key   = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
        $value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

        if ( empty( $key ) ) {
            wp_send_json_error( 'missing_key' );
        }

        // Decodifica JSON per oggetti (purposes, services)
        $json = json_decode( $value, true );
        if ( is_array( $json ) ) {
            $value = $json;
        }

        // Salva nel contesto giusto
        $privacy_keys = [ 'controller_name', 'controller_address', 'controller_email', 'controller_phone', 'controller_pec', 'dpo_enabled', 'dpo_name', 'dpo_email', 'purposes', 'services', 'transfers_extra_eu', 'transfers_basis' ];
        $option_keys  = [ 'banner_position', 'banner_title', 'banner_text', 'accept_text', 'reject_text', 'customize_text', 'save_text', 'color_theme', 'color_bg', 'color_text', 'color_accent', 'color_accept', 'color_reject', 'consent_expiry', 'consent_logging', 'gcm_enabled', 'show_reopen_widget' ];

        if ( in_array( $key, $privacy_keys, true ) ) {
            $data = get_option( 'scudo_privacy_data', [] );
            if ( is_array( $value ) ) {
                $data[ $key ] = array_map( function( $v ) { return (bool) $v; }, $value );
            } elseif ( $value === 'true' || $value === 'false' ) {
                $data[ $key ] = $value === 'true';
            } else {
                $data[ $key ] = sanitize_text_field( $value );
            }
            update_option( 'scudo_privacy_data', $data );
        } elseif ( in_array( $key, $option_keys, true ) ) {
            $options = get_option( 'scudo_options', [] );
            if ( $value === 'true' ) {
                $options[ $key ] = true;
            } elseif ( $value === 'false' ) {
                $options[ $key ] = false;
            } else {
                $options[ $key ] = sanitize_text_field( $value );
            }
            update_option( 'scudo_options', $options );
        }

        wp_send_json_success();
    }

    /* ── AJAX: autodetect plugin e servizi ────────────────────────── */

    public static function ajax_detect(): void {
        check_ajax_referer( 'scudo_wizard_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $detected = [
            'purposes' => [],
            'services' => [],
        ];

        // Autodetect plugin attivi
        if ( class_exists( 'WooCommerce' ) || is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            $detected['purposes']['ecommerce'] = true;
        }
        if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) || is_plugin_active( 'wpforms-lite/wpforms.php' ) || is_plugin_active( 'wpforms/wpforms.php' ) || is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
            $detected['purposes']['contact_forms'] = true;
        }
        if ( is_plugin_active( 'mailchimp-for-wp/mailchimp-for-wp.php' ) || is_plugin_active( 'mailpoet/mailpoet.php' ) ) {
            $detected['purposes']['newsletter'] = true;
            $detected['services']['mailchimp'] = true;
        }
        if ( get_option( 'users_can_register' ) ) {
            $detected['purposes']['user_accounts'] = true;
        }
        if ( get_option( 'default_comment_status' ) === 'open' ) {
            $detected['purposes']['comments'] = true;
        }
        if ( is_plugin_active( 'woocommerce-payments/woocommerce-payments.php' ) || is_plugin_active( 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' ) ) {
            $detected['services']['stripe'] = true;
        }
        if ( is_plugin_active( 'woocommerce-paypal-payments/woocommerce-paypal-payments.php' ) ) {
            $detected['services']['paypal'] = true;
        }

        // Scansione HTML homepage per servizi terze parti
        $response = wp_remote_get( home_url( '/' ), [
            'timeout' => 10, 'sslverify' => false,
            'user-agent' => 'Scudo-Wizard/1.0',
        ] );

        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );

            $service_patterns = [
                'google_analytics' => '/google-analytics\.com|gtag\/js|googletagmanager\.com/i',
                'google_ads'       => '/googleadservices\.com|googlesyndication\.com/i',
                'google_maps'      => '/maps\.google|google\.com\/maps/i',
                'google_fonts'     => '/fonts\.googleapis\.com/i',
                'youtube'          => '/youtube\.com|youtube-nocookie\.com/i',
                'facebook_pixel'   => '/facebook\.net|fbevents\.js|connect\.facebook/i',
                'hotjar'           => '/hotjar\.com/i',
                'cloudflare'       => '/cdnjs\.cloudflare\.com|cdn-cgi/i',
            ];

            foreach ( $service_patterns as $key => $pattern ) {
                if ( preg_match( $pattern, $body ) ) {
                    $detected['services'][ $key ] = true;
                    if ( in_array( $key, [ 'google_analytics', 'hotjar' ], true ) ) {
                        $detected['purposes']['analytics'] = true;
                    }
                    if ( in_array( $key, [ 'google_ads', 'facebook_pixel' ], true ) ) {
                        $detected['purposes']['marketing'] = true;
                    }
                }
            }
        }

        // site_functionality è sempre attivo
        $detected['purposes']['site_functionality'] = true;

        // Salva subito i risultati del detect nei privacy_data
        $data = get_option( 'scudo_privacy_data', [] );
        $data['purposes'] = array_merge( $data['purposes'] ?? [], $detected['purposes'] );
        $data['services'] = array_merge( $data['services'] ?? [], $detected['services'] );
        update_option( 'scudo_privacy_data', $data );

        // Salva scansione cookie per il plugin
        if ( class_exists( 'Scudo_Scanner' ) ) {
            Scudo_Scanner::ajax_scan_silent();
        }

        wp_send_json_success( $detected );
    }

    /* ── AJAX: finalizzazione — crea pagine e attiva tutto ────────── */

    public static function ajax_finish(): void {
        check_ajax_referer( 'scudo_wizard_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $privacy_data = wp_parse_args( get_option( 'scudo_privacy_data', [] ), Scudo_Privacy_Policy::defaults() );
        $options      = scudo_options();
        $created      = [];

        // Assicura che site_functionality sia sempre attivo
        if ( ! isset( $privacy_data['purposes'] ) || ! is_array( $privacy_data['purposes'] ) ) {
            $privacy_data['purposes'] = [];
        }
        $privacy_data['purposes']['site_functionality'] = true;

        // 1. Crea pagina Cookie Policy
        $cookie_page_id = wp_insert_post( [
            'post_title'   => __( 'Cookie Policy', 'scudo-cookie-privacy' ),
            'post_content' => '[scudo_cookie_table]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );
        if ( ! is_wp_error( $cookie_page_id ) ) {
            $options['policy_page'] = $cookie_page_id;
            $created[] = [ 'title' => __( 'Cookie Policy', 'scudo-cookie-privacy' ), 'url' => get_permalink( $cookie_page_id ) ];
        }

        // 2. Crea pagina Privacy Policy
        $privacy_content = '';
        if ( class_exists( 'Scudo_Privacy_Policy' ) ) {
            // Genera il contenuto dalla classe
            $privacy_page_id = wp_insert_post( [
                'post_title'   => __( 'Privacy Policy', 'scudo-cookie-privacy' ),
                'post_content' => '[scudo_privacy_policy]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ] );
            if ( ! is_wp_error( $privacy_page_id ) ) {
                $privacy_data['generated_page_id'] = $privacy_page_id;
                update_option( 'wp_page_for_privacy_policy', $privacy_page_id );
                $created[] = [ 'title' => __( 'Privacy Policy', 'scudo-cookie-privacy' ), 'url' => get_permalink( $privacy_page_id ) ];
            }
        }

        // 3. Crea pagina Esercizio Diritti
        $rights_page_id = wp_insert_post( [
            'post_title'   => __( 'Esercita i tuoi diritti', 'scudo-cookie-privacy' ),
            'post_content' => '<p>' . __( 'Ai sensi degli Artt. 15-22 del GDPR, puoi esercitare i tuoi diritti compilando il modulo seguente. Risponderemo entro 30 giorni.', 'scudo-cookie-privacy' ) . '</p>' . "\n\n" . '[scudo_rights_form]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );
        if ( ! is_wp_error( $rights_page_id ) ) {
            $created[] = [ 'title' => __( 'Esercita i tuoi diritti', 'scudo-cookie-privacy' ), 'url' => get_permalink( $rights_page_id ) ];
        }

        // 4. Attiva le impostazioni essenziali
        $options['consent_logging']    = true;
        $options['show_reopen_widget'] = true;
        $options['consent_expiry']     = 180;

        // Se servizi Google rilevati → attiva GCM v2
        $services = $privacy_data['services'] ?? [];
        if ( ! empty( $services['google_analytics'] ) || ! empty( $services['google_ads'] ) ) {
            $options['gcm_enabled'] = true;
        }

        // Salva tutto
        update_option( 'scudo_options', $options );
        update_option( 'scudo_privacy_data', $privacy_data );
        update_option( self::OPTION_DONE, true );

        // Aggiorna versione policy
        Scudo_Consent::bump_policy_version();

        wp_send_json_success( [
            'pages'   => $created,
            'gcm'     => ! empty( $options['gcm_enabled'] ),
            'message' => __( 'Scudo è attivo! Il tuo sito è ora conforme al GDPR.', 'scudo-cookie-privacy' ),
        ] );
    }

    /* ── Render del wizard ───────────────────────────────────────── */

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $nonce        = wp_create_nonce( 'scudo_wizard_nonce' );
        $privacy_data = wp_parse_args( get_option( 'scudo_privacy_data', [] ), Scudo_Privacy_Policy::defaults() );
        $options      = scudo_options();
        $admin_email  = get_option( 'admin_email', '' );
        $site_name    = get_bloginfo( 'name' );

        // Precompila se vuoto
        if ( empty( $privacy_data['controller_name'] ) ) {
            $privacy_data['controller_name'] = $site_name;
        }
        if ( empty( $privacy_data['controller_email'] ) ) {
            $privacy_data['controller_email'] = $admin_email;
        }

        $wizard_css = '.scudo-wiz{max-width:680px;margin:40px auto;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}'
            . '.scudo-wiz h1{font-size:28px;font-weight:700;margin:0 0 12px 0 !important;padding:0 !important;line-height:1.2}'
            . '.scudo-wiz .subtitle{color:#1d2327 !important;font-size:15px !important;margin:0 0 32px 0 !important;padding:0 !important;line-height:1.4}'
            . '.scudo-wiz-steps{display:flex;gap:4px;margin-bottom:32px}'
            . '.scudo-wiz-steps span{flex:1;height:4px;border-radius:2px;background:#dcdcde;transition:background .3s}'
            . '.scudo-wiz-steps span.active{background:#2271b1}'
            . '.scudo-wiz-steps span.done{background:#0f9b58}'
            . '.scudo-wiz-step{display:none}'
            . '.scudo-wiz-step.active{display:block}'
            . '.scudo-wiz-card{background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:24px 28px;margin-bottom:20px}'
            . '.scudo-wiz-card h2{font-size:18px;font-weight:700;margin:0 0 4px}'
            . '.scudo-wiz-card .desc{color:#646970;font-size:13px;margin-bottom:20px}'
            . '.scudo-wiz-field{margin-bottom:16px}'
            . '.scudo-wiz-field label{display:block;font-weight:600;font-size:13px;margin-bottom:5px;color:#1d2327}'
            . '.scudo-wiz-field input[type="text"],.scudo-wiz-field input[type="email"],.scudo-wiz-field input[type="tel"]{width:100%;padding:8px 12px;border:1px solid #8c8f94;border-radius:4px;font-size:14px;transition:border-color .2s,box-shadow .2s}'
            . '@keyframes scudoShake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-6px)}40%,80%{transform:translateX(6px)}}'
            . '.scudo-wiz-field input:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:none}'
            . '.scudo-wiz-check{display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #dcdcde;border-radius:6px;margin-bottom:8px;cursor:pointer;transition:border-color .15s,background .15s}'
            . '.scudo-wiz-check:hover{border-color:#2271b1;background:#f0f6fc}'
            . '.scudo-wiz-check.checked{border-color:#0f9b58;background:#f0faf4}'
            . '.scudo-wiz-check input{display:none}'
            . '.scudo-wiz-check .box{width:22px;height:22px;border:2px solid #8c8f94;border-radius:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s}'
            . '.scudo-wiz-check.checked .box{background:#0f9b58;border-color:#0f9b58}'
            . '.scudo-wiz-check .box svg{opacity:0;transition:opacity .15s}'
            . '.scudo-wiz-check.checked .box svg{opacity:1}'
            . '.scudo-wiz-check .info{flex:1}'
            . '.scudo-wiz-check .info strong{font-size:13px;display:block}'
            . '.scudo-wiz-check .info span{font-size:12px;color:#646970}'
            . '.scudo-wiz-check .badge{font-size:10px;font-weight:600;text-transform:uppercase;padding:2px 8px;border-radius:10px;background:#e8f5e9;color:#0f9b58;white-space:nowrap}'
            . '.scudo-wiz-nav{display:flex;justify-content:space-between;align-items:center;margin-top:24px}'
            . '.scudo-wiz-nav .btn{padding:10px 24px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:opacity .15s}'
            . '.scudo-wiz-nav .btn:hover{opacity:0.9}'
            . '.scudo-wiz-nav .btn-back{background:#f0f0f1;color:#1d2327}'
            . '.scudo-wiz-nav .btn-next{background:#2271b1;color:#fff}'
            . '.scudo-wiz-nav .btn-finish,.scudo-wiz-result .btn-finish{background:linear-gradient(135deg,#0f9b58 0%,#0a7f47 100%);color:#fff;font-size:17px;padding:16px 48px;border-radius:10px;border:none;cursor:pointer;font-weight:700;letter-spacing:0.3px;box-shadow:0 4px 14px rgba(15,155,88,0.4);transition:transform .15s,box-shadow .15s}'
            . '.scudo-wiz-result .btn-finish:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(15,155,88,0.5)}'
            . '.scudo-wiz-result .btn-finish:active{transform:translateY(0)}'
            . '.scudo-wiz-saved{font-size:12px;color:#0f9b58;opacity:0;transition:opacity .3s}'
            . '.scudo-wiz-saved.show{opacity:1}'
            . '.scudo-wiz-themes{display:flex;gap:12px;flex-wrap:wrap}'
            . '.scudo-wiz-theme{cursor:pointer;width:200px;border:3px solid #dcdcde;border-radius:8px;overflow:hidden;transition:border-color .2s}'
            . '.scudo-wiz-theme.selected{border-color:#2271b1}'
            . '.scudo-wiz-theme input{display:none}'
            . '.scudo-wiz-result{text-align:center;padding:20px}'
            . '.scudo-wiz-result h2{font-size:24px;color:#0f9b58;margin-bottom:8px}'
            . '.scudo-wiz-result .pages{text-align:left;margin:20px 0}'
            . '.scudo-wiz-result .pages a{display:block;padding:8px 0;color:#2271b1;font-size:14px}'
            . '@media(max-width:600px){.scudo-wiz{margin:20px 10px}.scudo-wiz-card{padding:16px}.scudo-wiz-themes{flex-direction:column}.scudo-wiz-theme{width:100%}}';
        wp_register_style( 'scudo-wizard', false, array(), SCUDO_VERSION );
        wp_enqueue_style( 'scudo-wizard' );
        wp_add_inline_style( 'scudo-wizard', $wizard_css );
        ?>

        <div class="scudo-wiz">
            <h1><?php esc_html_e( 'Configura Scudo', 'scudo-cookie-privacy' ); ?></h1>
            <p class="subtitle"><?php esc_html_e( 'Metti il tuo sito a norma GDPR in 2 minuti. I dati si salvano automaticamente.', 'scudo-cookie-privacy' ); ?></p>

            <!-- Progress bar -->
            <div class="scudo-wiz-steps">
                <span class="active"></span><span></span><span></span><span></span><span></span>
            </div>

            <!-- ═══ STEP 1: Chi sei ═══ -->
            <div class="scudo-wiz-step active" data-step="1">
                <div class="scudo-wiz-card">
                    <h2><?php esc_html_e( 'Chi sei?', 'scudo-cookie-privacy' ); ?></h2>
                    <p class="desc"><?php esc_html_e( 'I dati del titolare del trattamento. Appariranno nella Privacy Policy come richiesto dall\'Art. 13 del GDPR.', 'scudo-cookie-privacy' ); ?></p>

                    <div class="scudo-wiz-field">
                        <label><?php esc_html_e( 'Ragione sociale o nome completo *', 'scudo-cookie-privacy' ); ?></label>
                        <input type="text" data-key="controller_name" value="<?php echo esc_attr( $privacy_data['controller_name'] ); ?>" placeholder="<?php esc_attr_e( 'Es: Mario Rossi S.r.l.', 'scudo-cookie-privacy' ); ?>">
                    </div>
                    <div class="scudo-wiz-field">
                        <label><?php esc_html_e( 'Indirizzo sede legale *', 'scudo-cookie-privacy' ); ?></label>
                        <input type="text" data-key="controller_address" value="<?php echo esc_attr( $privacy_data['controller_address'] ); ?>" placeholder="<?php esc_attr_e( 'Es: Via Roma 1, 20100 Milano (MI)', 'scudo-cookie-privacy' ); ?>">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="scudo-wiz-field">
                            <label><?php esc_html_e( 'Email *', 'scudo-cookie-privacy' ); ?></label>
                            <input type="email" data-key="controller_email" value="<?php echo esc_attr( $privacy_data['controller_email'] ); ?>">
                        </div>
                        <div class="scudo-wiz-field">
                            <label><?php esc_html_e( 'Telefono', 'scudo-cookie-privacy' ); ?></label>
                            <input type="tel" data-key="controller_phone" value="<?php echo esc_attr( $privacy_data['controller_phone'] ); ?>">
                        </div>
                    </div>
                    <div class="scudo-wiz-field">
                        <label><?php esc_html_e( 'PEC (opzionale)', 'scudo-cookie-privacy' ); ?></label>
                        <input type="email" data-key="controller_pec" value="<?php echo esc_attr( $privacy_data['controller_pec'] ); ?>">
                    </div>
                </div>

                <div class="scudo-wiz-card">
                    <h2><?php esc_html_e( 'Hai un DPO?', 'scudo-cookie-privacy' ); ?></h2>
                    <p class="desc"><?php esc_html_e( 'Il Responsabile della Protezione dei Dati è obbligatorio per enti pubblici e organizzazioni che trattano dati su larga scala. Per la maggior parte dei siti WordPress non serve.', 'scudo-cookie-privacy' ); ?></p>
                    <label class="scudo-wiz-check" data-key="dpo_enabled" data-value="toggle">
                        <input type="checkbox" <?php checked( $privacy_data['dpo_enabled'] ); ?>>
                        <span class="box"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7l3.5 3.5L12 4" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="info"><strong><?php esc_html_e( 'Ho nominato un DPO', 'scudo-cookie-privacy' ); ?></strong></span>
                    </label>
                    <div id="wiz-dpo-fields" style="<?php echo $privacy_data['dpo_enabled'] ? '' : 'display:none;'; ?>margin-top:12px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="scudo-wiz-field"><label><?php esc_html_e( 'Nome DPO', 'scudo-cookie-privacy' ); ?></label><input type="text" data-key="dpo_name" value="<?php echo esc_attr( $privacy_data['dpo_name'] ); ?>"></div>
                            <div class="scudo-wiz-field"><label><?php esc_html_e( 'Email DPO', 'scudo-cookie-privacy' ); ?></label><input type="email" data-key="dpo_email" value="<?php echo esc_attr( $privacy_data['dpo_email'] ); ?>"></div>
                        </div>
                    </div>
                </div>
                <span class="scudo-wiz-saved"><?php esc_html_e( 'Salvato', 'scudo-cookie-privacy' ); ?></span>
            </div>

            <!-- ═══ STEP 2: Cosa fa il tuo sito ═══ -->
            <div class="scudo-wiz-step" data-step="2">
                <div class="scudo-wiz-card">
                    <h2><?php esc_html_e( 'Cosa fa il tuo sito?', 'scudo-cookie-privacy' ); ?></h2>
                    <p class="desc"><?php esc_html_e( 'Seleziona le attività che il tuo sito svolge. Scudo ha già rilevato alcune funzionalità.', 'scudo-cookie-privacy' ); ?></p>
                    <div id="wiz-purposes">
                    <?php
                    $purposes = [
                        'site_functionality' => [ __( 'Sito web funzionante', 'scudo-cookie-privacy' ), __( 'Cookie tecnici per il funzionamento base', 'scudo-cookie-privacy' ), true ],
                        'contact_forms'      => [ __( 'Moduli di contatto', 'scudo-cookie-privacy' ), __( 'Raccoglie nome, email e messaggi dai visitatori', 'scudo-cookie-privacy' ), false ],
                        'analytics'          => [ __( 'Statistiche e analisi', 'scudo-cookie-privacy' ), __( 'Google Analytics, Hotjar, Clarity o simili', 'scudo-cookie-privacy' ), false ],
                        'marketing'          => [ __( 'Marketing e pubblicità', 'scudo-cookie-privacy' ), __( 'Google Ads, Facebook Pixel, remarketing', 'scudo-cookie-privacy' ), false ],
                        'newsletter'         => [ __( 'Newsletter', 'scudo-cookie-privacy' ), __( 'Mailchimp, MailPoet o altro servizio di email marketing', 'scudo-cookie-privacy' ), false ],
                        'ecommerce'          => [ __( 'E-commerce', 'scudo-cookie-privacy' ), __( 'Vendita online con WooCommerce o simili', 'scudo-cookie-privacy' ), false ],
                        'user_accounts'      => [ __( 'Account utente', 'scudo-cookie-privacy' ), __( 'Registrazione e login degli utenti', 'scudo-cookie-privacy' ), false ],
                        'comments'           => [ __( 'Commenti', 'scudo-cookie-privacy' ), __( 'I visitatori possono commentare gli articoli', 'scudo-cookie-privacy' ), false ],
                    ];
                    foreach ( $purposes as $key => $p ) :
                        $checked = ! empty( $privacy_data['purposes'][ $key ] ) || $p[2];
                        $forced = $key === 'site_functionality';
                    ?>
                    <label class="scudo-wiz-check <?php echo $checked ? 'checked' : ''; ?>" data-purpose="<?php echo esc_attr( $key ); ?>">
                        <input type="checkbox" <?php echo $checked ? 'checked' : ''; ?> <?php echo $forced ? 'disabled' : ''; ?>>
                        <span class="box"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7l3.5 3.5L12 4" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="info"><strong><?php echo esc_html( $p[0] ); ?></strong><span><?php echo esc_html( $p[1] ); ?></span></span>
                        <?php if ( $forced ) : ?><span class="badge"><?php esc_html_e( 'sempre attivo', 'scudo-cookie-privacy' ); ?></span><?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                <span class="scudo-wiz-saved"><?php esc_html_e( 'Salvato', 'scudo-cookie-privacy' ); ?></span>
            </div>

            <!-- ═══ STEP 3: Servizi terze parti ═══ -->
            <div class="scudo-wiz-step" data-step="3">
                <div class="scudo-wiz-card">
                    <h2><?php esc_html_e( 'Quali servizi esterni usi?', 'scudo-cookie-privacy' ); ?></h2>
                    <p class="desc"><?php esc_html_e( 'Scudo ha scansionato il tuo sito e ha rilevato alcuni servizi. Verifica e aggiungi quelli mancanti.', 'scudo-cookie-privacy' ); ?></p>
                    <div id="wiz-services">
                    <?php
                    $services = [
                        'google_analytics' => [ 'Google Analytics', __( 'Statistiche di traffico e comportamento utenti', 'scudo-cookie-privacy' ) ],
                        'google_ads'       => [ 'Google Ads', __( 'Pubblicità e remarketing Google', 'scudo-cookie-privacy' ) ],
                        'google_maps'      => [ 'Google Maps', __( 'Mappe interattive nelle pagine', 'scudo-cookie-privacy' ) ],
                        'google_fonts'     => [ 'Google Fonts', __( 'Font tipografici caricati da Google', 'scudo-cookie-privacy' ) ],
                        'youtube'          => [ 'YouTube', __( 'Video incorporati nelle pagine', 'scudo-cookie-privacy' ) ],
                        'facebook_pixel'   => [ 'Facebook / Meta Pixel', __( 'Tracciamento per pubblicità Facebook e Instagram', 'scudo-cookie-privacy' ) ],
                        'mailchimp'        => [ 'Mailchimp', __( 'Servizio di email marketing e newsletter', 'scudo-cookie-privacy' ) ],
                        'stripe'           => [ 'Stripe', __( 'Elaborazione pagamenti online', 'scudo-cookie-privacy' ) ],
                        'paypal'           => [ 'PayPal', __( 'Elaborazione pagamenti online', 'scudo-cookie-privacy' ) ],
                        'cloudflare'       => [ 'Cloudflare', __( 'CDN e protezione del sito', 'scudo-cookie-privacy' ) ],
                        'hotjar'           => [ 'Hotjar', __( 'Heatmap e registrazioni sessioni utente', 'scudo-cookie-privacy' ) ],
                    ];
                    foreach ( $services as $key => $s ) :
                        $checked = ! empty( $privacy_data['services'][ $key ] );
                    ?>
                    <label class="scudo-wiz-check <?php echo $checked ? 'checked' : ''; ?>" data-service="<?php echo esc_attr( $key ); ?>">
                        <input type="checkbox" <?php echo $checked ? 'checked' : ''; ?>>
                        <span class="box"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 7l3.5 3.5L12 4" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <span class="info"><strong><?php echo esc_html( $s[0] ); ?></strong><span><?php echo esc_html( $s[1] ); ?></span></span>
                    </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                <span class="scudo-wiz-saved"><?php esc_html_e( 'Salvato', 'scudo-cookie-privacy' ); ?></span>
            </div>

            <!-- ═══ STEP 4: Tema ═══ -->
            <div class="scudo-wiz-step" data-step="4">
                <div class="scudo-wiz-card">
                    <h2><?php esc_html_e( 'Come vuoi che appaia il banner?', 'scudo-cookie-privacy' ); ?></h2>
                    <p class="desc"><?php esc_html_e( 'Scegli lo stile. Potrai cambiarlo in qualsiasi momento dalle impostazioni.', 'scudo-cookie-privacy' ); ?></p>
                    <div class="scudo-wiz-themes">
                        <?php
                        $theme_options = [
                            'dark'  => [ __( 'Scuro', 'scudo-cookie-privacy' ), '#1a1a2e', '#fff', '#fff', '#1a1a2e' ],
                            'light' => [ __( 'Chiaro', 'scudo-cookie-privacy' ), '#ffffff', '#1a1a2e', '#1a1a2e', '#fff' ],
                        ];
                        $current_theme = $options['color_theme'] ?? 'dark';
                        foreach ( $theme_options as $tk => $tv ) : ?>
                        <label class="scudo-wiz-theme <?php echo $current_theme === $tk ? 'selected' : ''; ?>">
                            <input type="radio" name="wiz_theme" value="<?php echo esc_attr( $tk ); ?>" <?php checked( $current_theme, $tk ); ?>>
                            <div style="background:<?php echo esc_attr( $tv[1] ); ?>;padding:16px;min-height:90px;display:flex;flex-direction:column;justify-content:space-between;">
                                <div style="color:<?php echo esc_attr( $tv[2] ); ?>;font-size:11px;font-weight:700;"><?php esc_html_e( 'Questo sito utilizza i cookie', 'scudo-cookie-privacy' ); ?></div>
                                <div style="display:flex;gap:6px;margin-top:12px;">
                                    <span style="background:<?php echo esc_attr( $tv[3] ); ?>;color:<?php echo esc_attr( $tv[4] ); ?>;font-size:9px;font-weight:600;padding:5px 12px;border-radius:4px;"><?php esc_html_e( 'Accetta', 'scudo-cookie-privacy' ); ?></span>
                                    <span style="background:<?php echo esc_attr( $tv[3] ); ?>;color:<?php echo esc_attr( $tv[4] ); ?>;font-size:9px;font-weight:600;padding:5px 12px;border-radius:4px;"><?php esc_html_e( 'Rifiuta', 'scudo-cookie-privacy' ); ?></span>
                                </div>
                            </div>
                            <div style="padding:8px;text-align:center;font-size:13px;font-weight:600;background:#f9fafb;"><?php echo esc_html( $tv[0] ); ?></div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <span class="scudo-wiz-saved"><?php esc_html_e( 'Salvato', 'scudo-cookie-privacy' ); ?></span>
            </div>

            <!-- ═══ STEP 5: Tutto pronto ═══ -->
            <div class="scudo-wiz-step" data-step="5">
                <div class="scudo-wiz-card">
                    <div class="scudo-wiz-result" id="wiz-result-pending">
                        <h2><?php esc_html_e( 'Tutto pronto!', 'scudo-cookie-privacy' ); ?></h2>
                        <p><?php esc_html_e( 'Scudo creerà automaticamente:', 'scudo-cookie-privacy' ); ?></p>
                        <ul style="text-align:left;max-width:400px;margin:16px auto;font-size:14px;line-height:2;">
                            <li><?php esc_html_e( 'Pagina Cookie Policy con la tabella dei cookie', 'scudo-cookie-privacy' ); ?></li>
                            <li><?php esc_html_e( 'Pagina Privacy Policy con i tuoi dati', 'scudo-cookie-privacy' ); ?></li>
                            <li><?php esc_html_e( 'Pagina per l\'esercizio dei diritti GDPR', 'scudo-cookie-privacy' ); ?></li>
                            <li><?php esc_html_e( 'Banner dei cookie con il tema scelto', 'scudo-cookie-privacy' ); ?></li>
                            <li><?php esc_html_e( 'Registro dei consensi attivo', 'scudo-cookie-privacy' ); ?></li>
                        </ul>
                        <button type="button" class="btn btn-finish" id="wiz-finish-btn"><?php esc_html_e( 'Attiva Scudo', 'scudo-cookie-privacy' ); ?></button>
                    </div>
                    <div class="scudo-wiz-result" id="wiz-result-done" style="display:none;">
                        <div style="font-size:48px;margin-bottom:12px;">&#x1f6e1;&#xfe0f;</div>
                        <h2 id="wiz-done-msg"></h2>
                        <div class="pages" id="wiz-done-pages"></div>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=scudo' ) ); ?>" class="btn btn-next" style="display:inline-block;margin-top:16px;text-decoration:none;"><?php esc_html_e( 'Vai alle impostazioni', 'scudo-cookie-privacy' ); ?></a>
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" class="btn btn-back" style="display:inline-block;margin-top:16px;text-decoration:none;margin-left:8px;"><?php esc_html_e( 'Vedi il sito', 'scudo-cookie-privacy' ); ?></a>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="scudo-wiz-nav">
                <button type="button" class="btn btn-back" id="wiz-back" style="visibility:hidden;"><?php esc_html_e( 'Indietro', 'scudo-cookie-privacy' ); ?></button>
                <button type="button" class="btn btn-next" id="wiz-next"><?php esc_html_e( 'Avanti', 'scudo-cookie-privacy' ); ?></button>
            </div>
        </div>

        <?php
        wp_register_script( 'scudo-wizard', '', array(), SCUDO_VERSION, true );
        wp_enqueue_script( 'scudo-wizard' );
        wp_localize_script( 'scudo-wizard', 'scudoWizard', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => $nonce,
            'i18n'    => array(
                'configuring' => __( 'Configurazione in corso...', 'scudo-cookie-privacy' ),
                'detected'    => __( 'rilevato', 'scudo-cookie-privacy' ),
            ),
        ) );
        $wizard_js = '(function(){' .
            'var ajaxUrl=scudoWizard.ajaxUrl;' .
            'var nonce=scudoWizard.nonce;' .
            'var step=1,totalSteps=5;' .
            'var steps=document.querySelectorAll(".scudo-wiz-step");' .
            'var bars=document.querySelectorAll(".scudo-wiz-steps span");' .
            'var btnNext=document.getElementById("wiz-next");' .
            'var btnBack=document.getElementById("wiz-back");' .
            'var saveTimer=null;' .
            'function showStep(n){' .
                'step=n;' .
                'steps.forEach(function(s){s.classList.remove("active");});' .
                'steps[n-1].classList.add("active");' .
                'bars.forEach(function(b,i){b.className=i<n?(i<n-1?"done":"active"):"";});' .
                'btnBack.style.visibility=n>1?"visible":"hidden";' .
                'btnNext.style.display=n>=totalSteps?"none":"";' .
            '}' .
            'function validateStep(n){' .
                'var currentStep=steps[n-1];' .
                'var required={1:["controller_name","controller_address","controller_email"]};' .
                'var fields=required[n]||[];' .
                'var firstInvalid=null;' .
                'fields.forEach(function(key){' .
                    'var input=currentStep.querySelector("[data-key=\""+key+"\"]");' .
                    'if(!input)return;' .
                    'var val=input.value.trim();' .
                    'if(!val){' .
                        'input.style.borderColor="#e94560";' .
                        'input.style.boxShadow="0 0 0 1px #e94560";' .
                        'if(!firstInvalid)firstInvalid=input;' .
                    '}else{' .
                        'input.style.borderColor="";' .
                        'input.style.boxShadow="";' .
                    '}' .
                '});' .
                'if(n===1){' .
                    'var emailInput=currentStep.querySelector("[data-key=\"controller_email\"]");' .
                    'if(emailInput&&emailInput.value&&!/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(emailInput.value)){' .
                        'emailInput.style.borderColor="#e94560";' .
                        'emailInput.style.boxShadow="0 0 0 1px #e94560";' .
                        'if(!firstInvalid)firstInvalid=emailInput;' .
                    '}' .
                '}' .
                'if(firstInvalid){' .
                    'firstInvalid.focus();' .
                    'firstInvalid.style.animation="none";' .
                    'firstInvalid.offsetHeight;' .
                    'firstInvalid.style.animation="scudoShake 0.4s ease";' .
                    'return false;' .
                '}' .
                'return true;' .
            '}' .
            'btnNext.addEventListener("click",function(){' .
                'if(!validateStep(step))return;' .
                'if(step<totalSteps)showStep(step+1);' .
                'if(step===2)autoDetect();' .
            '});' .
            'btnBack.addEventListener("click",function(){if(step>1)showStep(step-1);});' .
            'document.querySelectorAll(".scudo-wiz-field input").forEach(function(input){' .
                'input.addEventListener("input",function(){' .
                    'this.style.borderColor="";' .
                    'this.style.boxShadow="";' .
                    'this.style.animation="";' .
                    'var key=this.getAttribute("data-key");' .
                    'if(!key)return;' .
                    'var val=this.value;' .
                    'clearTimeout(saveTimer);' .
                    'saveTimer=setTimeout(function(){save(key,val);},500);' .
                '});' .
            '});' .
            'document.querySelectorAll("[data-purpose]").forEach(function(label){' .
                'label.addEventListener("click",function(e){' .
                    'if(this.querySelector("input").disabled)return;' .
                    'var cb=this.querySelector("input");' .
                    'var checked=!cb.checked;' .
                    'cb.checked=checked;' .
                    'this.classList.toggle("checked",checked);' .
                    'var purposes={};' .
                    'document.querySelectorAll("[data-purpose]").forEach(function(l){' .
                        'purposes[l.dataset.purpose]=l.querySelector("input").checked;' .
                    '});' .
                    'save("purposes",purposes);' .
                    'e.preventDefault();' .
                '});' .
            '});' .
            'document.querySelectorAll("[data-service]").forEach(function(label){' .
                'label.addEventListener("click",function(e){' .
                    'var cb=this.querySelector("input");' .
                    'var checked=!cb.checked;' .
                    'cb.checked=checked;' .
                    'this.classList.toggle("checked",checked);' .
                    'var services={};' .
                    'document.querySelectorAll("[data-service]").forEach(function(l){' .
                        'services[l.dataset.service]=l.querySelector("input").checked;' .
                    '});' .
                    'save("services",services);' .
                    'e.preventDefault();' .
                '});' .
            '});' .
            'document.querySelector("[data-key=\"dpo_enabled\"]").addEventListener("click",function(e){' .
                'var cb=this.querySelector("input");' .
                'var checked=!cb.checked;' .
                'cb.checked=checked;' .
                'this.classList.toggle("checked",checked);' .
                'document.getElementById("wiz-dpo-fields").style.display=checked?"":"none";' .
                'save("dpo_enabled",checked?"true":"false");' .
                'e.preventDefault();' .
            '});' .
            'document.querySelectorAll(".scudo-wiz-theme input").forEach(function(radio){' .
                'radio.addEventListener("change",function(){' .
                    'document.querySelectorAll(".scudo-wiz-theme").forEach(function(t){t.classList.remove("selected");});' .
                    'this.closest(".scudo-wiz-theme").classList.add("selected");' .
                    'save("color_theme",this.value);' .
                '});' .
            '});' .
            'document.getElementById("wiz-finish-btn").addEventListener("click",function(){' .
                'this.disabled=true;' .
                'this.textContent=scudoWizard.i18n.configuring;' .
                'var fd=new FormData();' .
                'fd.append("action","scudo_wizard_finish");' .
                'fd.append("nonce",nonce);' .
                'fetch(ajaxUrl,{method:"POST",body:fd,credentials:"same-origin"})' .
                    '.then(function(r){return r.json()})' .
                    '.then(function(data){' .
                        'if(data.success){' .
                            'document.getElementById("wiz-result-pending").style.display="none";' .
                            'var done=document.getElementById("wiz-result-done");' .
                            'done.style.display="";' .
                            'document.getElementById("wiz-done-msg").textContent=data.data.message;' .
                            'var pages=document.getElementById("wiz-done-pages");' .
                            'data.data.pages.forEach(function(p){' .
                                'pages.innerHTML+="<a href=\""+p.url+"\" target=\"_blank\">&#10003; "+p.title+"</a>";' .
                            '});' .
                            'document.querySelector(".scudo-wiz-nav").style.display="none";' .
                            'bars.forEach(function(b){b.className="done";});' .
                        '}' .
                    '});' .
            '});' .
            'function autoDetect(){' .
                'var fd=new FormData();' .
                'fd.append("action","scudo_wizard_detect");' .
                'fd.append("nonce",nonce);' .
                'fetch(ajaxUrl,{method:"POST",body:fd,credentials:"same-origin"})' .
                    '.then(function(r){return r.json()})' .
                    '.then(function(data){' .
                        'if(!data.success)return;' .
                        'var d=data.data;' .
                        'Object.keys(d.purposes||{}).forEach(function(k){' .
                            'var el=document.querySelector("[data-purpose=\""+k+"\"]");' .
                            'if(el&&d.purposes[k]&&!el.querySelector("input").checked){' .
                                'el.querySelector("input").checked=true;' .
                                'el.classList.add("checked");' .
                                'var badge=document.createElement("span");' .
                                'badge.className="badge";' .
                                'badge.textContent=scudoWizard.i18n.detected;' .
                                'el.appendChild(badge);' .
                            '}' .
                        '});' .
                        'Object.keys(d.services||{}).forEach(function(k){' .
                            'var el=document.querySelector("[data-service=\""+k+"\"]");' .
                            'if(el&&d.services[k]&&!el.querySelector("input").checked){' .
                                'el.querySelector("input").checked=true;' .
                                'el.classList.add("checked");' .
                                'var badge=document.createElement("span");' .
                                'badge.className="badge";' .
                                'badge.textContent=scudoWizard.i18n.detected;' .
                                'el.appendChild(badge);' .
                            '}' .
                        '});' .
                        'save("purposes",d.purposes||{});' .
                        'save("services",d.services||{});' .
                    '});' .
            '}' .
            'function save(key,value){' .
                'var fd=new FormData();' .
                'fd.append("action","scudo_wizard_save");' .
                'fd.append("nonce",nonce);' .
                'fd.append("key",key);' .
                'if(typeof value==="object"){' .
                    'fd.append("value",JSON.stringify(value));' .
                '}else{' .
                    'fd.append("value",value);' .
                '}' .
                'fetch(ajaxUrl,{method:"POST",body:fd,credentials:"same-origin"})' .
                    '.then(function(){' .
                        'var saved=steps[step-1].querySelector(".scudo-wiz-saved");' .
                        'if(saved){saved.classList.add("show");setTimeout(function(){saved.classList.remove("show");},1500);}' .
                    '});' .
            '}' .
            'autoDetect();' .
        '})();';
        wp_add_inline_script( 'scudo-wizard', $wizard_js );
        ?>
        <?php
    }
}
