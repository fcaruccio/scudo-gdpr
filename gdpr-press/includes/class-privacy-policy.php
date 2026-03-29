<?php
/**
 * Wizard guidato per generare la pagina Privacy Policy conforme agli Artt. 13-14 GDPR.
 *
 * - Form wizard nell'admin con tutti i campi richiesti
 * - Genera la pagina WordPress con il contenuto completo
 * - Shortcode [gdpr_press_privacy_policy] per rendering dinamico
 */

defined( 'ABSPATH' ) || exit;

class GDPR_Press_Privacy_Policy {

    private const OPTION = 'gdpr_press_privacy_data';

    public static function init(): void {
        add_shortcode( 'gdpr_press_privacy_policy', [ __CLASS__, 'shortcode' ] );
        add_action( 'wp_ajax_gdpr_press_generate_privacy_policy', [ __CLASS__, 'ajax_generate' ] );
    }

    /* ── Default dei campi del wizard ────────────────────────────── */

    public static function defaults(): array {
        return [
            // Titolare
            'controller_name'       => '',
            'controller_address'    => '',
            'controller_email'      => get_option( 'admin_email', '' ),
            'controller_phone'      => '',
            'controller_pec'        => '',

            // DPO
            'dpo_enabled'           => false,
            'dpo_name'              => '',
            'dpo_email'             => '',

            // Finalità e basi giuridiche
            'purposes'              => [
                'site_functionality' => true,
                'contact_forms'     => true,
                'analytics'         => false,
                'marketing'         => false,
                'newsletter'        => false,
                'ecommerce'         => false,
                'user_accounts'     => false,
                'comments'          => false,
            ],

            // Servizi terze parti
            'services'              => [
                'google_analytics'  => false,
                'google_ads'        => false,
                'google_maps'       => false,
                'google_fonts'      => false,
                'youtube'           => false,
                'facebook_pixel'    => false,
                'mailchimp'         => false,
                'stripe'            => false,
                'paypal'            => false,
                'cloudflare'        => false,
                'hotjar'            => false,
            ],

            // Trasferimenti extra-UE
            'transfers_extra_eu'    => false,
            'transfers_basis'       => 'dpf', // dpf | scc | consent

            // Conservazione
            'retention_contact'     => '12 mesi',
            'retention_analytics'   => '26 mesi',
            'retention_marketing'   => 'fino a revoca del consenso',
            'retention_ecommerce'   => '10 anni (obbligo fiscale)',
            'retention_accounts'    => 'fino a cancellazione account',

            // Pagina generata
            'generated_page_id'     => 0,
        ];
    }

    /* ── Render del wizard nell'admin ─────────────────────────────── */

    public static function render_wizard(): void {
        $data = wp_parse_args( get_option( self::OPTION, [] ), self::defaults() );
        $nonce = wp_create_nonce( 'gdpr_press_nonce' );
        ?>
        <hr>
        <h2 class="title"><?php esc_html_e( 'Wizard Privacy Policy', 'gdpr-press' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Compila i campi e genera automaticamente una pagina Privacy Policy conforme agli Artt. 13-14 del GDPR. Tutti i campi con * sono obbligatori per legge.', 'gdpr-press' ); ?></p>

        <?php if ( ! empty( $data['generated_page_id'] ) && get_post( $data['generated_page_id'] ) ) : ?>
            <div class="notice notice-success inline" style="margin:16px 0;padding:12px;">
                <p>
                    <?php esc_html_e( 'Privacy policy generata:', 'gdpr-press' ); ?>
                    <a href="<?php echo esc_url( get_permalink( $data['generated_page_id'] ) ); ?>" target="_blank"><?php esc_html_e( 'Visualizza', 'gdpr-press' ); ?></a> |
                    <a href="<?php echo esc_url( get_edit_post_link( $data['generated_page_id'] ) ); ?>"><?php esc_html_e( 'Modifica', 'gdpr-press' ); ?></a>
                </p>
            </div>
        <?php endif; ?>

        <div id="gdpr-press-wizard" style="max-width:800px;">

            <!-- Step 1: Titolare -->
            <div class="gdpr-press-wizard-step" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin:16px 0;">
                <h3 style="margin-top:0;"><?php esc_html_e( '1. Titolare del trattamento *', 'gdpr-press' ); ?></h3>
                <table class="form-table" style="margin:0;">
                    <tr><th><label><?php esc_html_e( 'Ragione sociale / Nome *', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" id="pp_controller_name" value="<?php echo esc_attr( $data['controller_name'] ); ?>" class="regular-text" required></td></tr>
                    <tr><th><label><?php esc_html_e( 'Indirizzo sede legale *', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" id="pp_controller_address" value="<?php echo esc_attr( $data['controller_address'] ); ?>" class="large-text"></td></tr>
                    <tr><th><label><?php esc_html_e( 'Email *', 'gdpr-press' ); ?></label></th>
                        <td><input type="email" id="pp_controller_email" value="<?php echo esc_attr( $data['controller_email'] ); ?>" class="regular-text" required></td></tr>
                    <tr><th><label><?php esc_html_e( 'Telefono', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" id="pp_controller_phone" value="<?php echo esc_attr( $data['controller_phone'] ); ?>" class="regular-text"></td></tr>
                    <tr><th><label><?php esc_html_e( 'PEC', 'gdpr-press' ); ?></label></th>
                        <td><input type="email" id="pp_controller_pec" value="<?php echo esc_attr( $data['controller_pec'] ); ?>" class="regular-text"></td></tr>
                </table>
            </div>

            <!-- Step 2: DPO -->
            <div class="gdpr-press-wizard-step" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin:16px 0;">
                <h3 style="margin-top:0;"><?php esc_html_e( '2. Responsabile della Protezione dei Dati (DPO)', 'gdpr-press' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Obbligatorio per enti pubblici e organizzazioni che trattano dati su larga scala.', 'gdpr-press' ); ?></p>
                <label style="display:block;margin:12px 0;"><input type="checkbox" id="pp_dpo_enabled" <?php checked( $data['dpo_enabled'] ); ?>> <?php esc_html_e( 'È stato nominato un DPO', 'gdpr-press' ); ?></label>
                <div id="pp_dpo_fields" style="<?php echo $data['dpo_enabled'] ? '' : 'display:none;'; ?>">
                    <table class="form-table" style="margin:0;">
                        <tr><th><label><?php esc_html_e( 'Nome DPO', 'gdpr-press' ); ?></label></th>
                            <td><input type="text" id="pp_dpo_name" value="<?php echo esc_attr( $data['dpo_name'] ); ?>" class="regular-text"></td></tr>
                        <tr><th><label><?php esc_html_e( 'Email DPO', 'gdpr-press' ); ?></label></th>
                            <td><input type="email" id="pp_dpo_email" value="<?php echo esc_attr( $data['dpo_email'] ); ?>" class="regular-text"></td></tr>
                    </table>
                </div>
            </div>

            <!-- Step 3: Finalità -->
            <div class="gdpr-press-wizard-step" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin:16px 0;">
                <h3 style="margin-top:0;"><?php esc_html_e( '3. Finalità del trattamento *', 'gdpr-press' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Seleziona tutte le finalità per cui il tuo sito raccoglie dati personali.', 'gdpr-press' ); ?></p>
                <?php
                $purpose_labels = [
                    'site_functionality' => __( 'Funzionamento del sito web', 'gdpr-press' ),
                    'contact_forms'      => __( 'Gestione richieste di contatto', 'gdpr-press' ),
                    'analytics'          => __( 'Analisi statistiche (Google Analytics, ecc.)', 'gdpr-press' ),
                    'marketing'          => __( 'Marketing e pubblicità', 'gdpr-press' ),
                    'newsletter'         => __( 'Invio newsletter', 'gdpr-press' ),
                    'ecommerce'          => __( 'E-commerce / vendita online', 'gdpr-press' ),
                    'user_accounts'      => __( 'Gestione account utente', 'gdpr-press' ),
                    'comments'           => __( 'Commenti sul blog', 'gdpr-press' ),
                ];
                foreach ( $purpose_labels as $key => $label ) : ?>
                    <label style="display:block;margin:6px 0;"><input type="checkbox" class="pp_purpose" data-key="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $data['purposes'][ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?>
            </div>

            <!-- Step 4: Servizi terze parti -->
            <div class="gdpr-press-wizard-step" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin:16px 0;">
                <h3 style="margin-top:0;"><?php esc_html_e( '4. Servizi di terze parti *', 'gdpr-press' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Seleziona i servizi esterni utilizzati dal tuo sito.', 'gdpr-press' ); ?></p>
                <?php
                $service_labels = [
                    'google_analytics' => 'Google Analytics (Google LLC)',
                    'google_ads'       => 'Google Ads (Google LLC)',
                    'google_maps'      => 'Google Maps (Google LLC)',
                    'google_fonts'     => 'Google Fonts (Google LLC)',
                    'youtube'          => 'YouTube (Google LLC)',
                    'facebook_pixel'   => 'Facebook Pixel (Meta Platforms Inc.)',
                    'mailchimp'        => 'Mailchimp (Intuit Inc.)',
                    'stripe'           => 'Stripe (Stripe Inc.)',
                    'paypal'           => 'PayPal (PayPal Holdings Inc.)',
                    'cloudflare'       => 'Cloudflare (Cloudflare Inc.)',
                    'hotjar'           => 'Hotjar (Hotjar Ltd.)',
                ];
                foreach ( $service_labels as $key => $label ) : ?>
                    <label style="display:block;margin:6px 0;"><input type="checkbox" class="pp_service" data-key="<?php echo esc_attr( $key ); ?>" <?php checked( ! empty( $data['services'][ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?>
            </div>

            <!-- Step 5: Trasferimenti extra-UE -->
            <div class="gdpr-press-wizard-step" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin:16px 0;">
                <h3 style="margin-top:0;"><?php esc_html_e( '5. Trasferimenti dati extra-UE', 'gdpr-press' ); ?></h3>
                <label style="display:block;margin:12px 0;"><input type="checkbox" id="pp_transfers" <?php checked( $data['transfers_extra_eu'] ); ?>> <?php esc_html_e( 'I dati vengono trasferiti fuori dallo Spazio Economico Europeo', 'gdpr-press' ); ?></label>
                <div id="pp_transfers_fields" style="<?php echo $data['transfers_extra_eu'] ? '' : 'display:none;'; ?>">
                    <p><?php esc_html_e( 'Base giuridica del trasferimento:', 'gdpr-press' ); ?></p>
                    <label style="display:block;margin:4px 0;"><input type="radio" name="pp_transfers_basis" value="dpf" <?php checked( $data['transfers_basis'], 'dpf' ); ?>> <?php esc_html_e( 'EU-US Data Privacy Framework (aziende certificate)', 'gdpr-press' ); ?></label>
                    <label style="display:block;margin:4px 0;"><input type="radio" name="pp_transfers_basis" value="scc" <?php checked( $data['transfers_basis'], 'scc' ); ?>> <?php esc_html_e( 'Clausole contrattuali standard (SCC)', 'gdpr-press' ); ?></label>
                    <label style="display:block;margin:4px 0;"><input type="radio" name="pp_transfers_basis" value="consent" <?php checked( $data['transfers_basis'], 'consent' ); ?>> <?php esc_html_e( 'Consenso esplicito dell\'interessato', 'gdpr-press' ); ?></label>
                </div>
            </div>

            <!-- Step 6: Conservazione -->
            <div class="gdpr-press-wizard-step" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px;margin:16px 0;">
                <h3 style="margin-top:0;"><?php esc_html_e( '6. Periodi di conservazione *', 'gdpr-press' ); ?></h3>
                <table class="form-table" style="margin:0;">
                    <tr><th><label><?php esc_html_e( 'Dati form di contatto', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" id="pp_ret_contact" value="<?php echo esc_attr( $data['retention_contact'] ); ?>" class="regular-text"></td></tr>
                    <tr><th><label><?php esc_html_e( 'Dati analitici', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" id="pp_ret_analytics" value="<?php echo esc_attr( $data['retention_analytics'] ); ?>" class="regular-text"></td></tr>
                    <tr><th><label><?php esc_html_e( 'Dati marketing/newsletter', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" id="pp_ret_marketing" value="<?php echo esc_attr( $data['retention_marketing'] ); ?>" class="regular-text"></td></tr>
                    <tr><th><label><?php esc_html_e( 'Dati e-commerce/fatturazione', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" id="pp_ret_ecommerce" value="<?php echo esc_attr( $data['retention_ecommerce'] ); ?>" class="regular-text"></td></tr>
                    <tr><th><label><?php esc_html_e( 'Dati account utente', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" id="pp_ret_accounts" value="<?php echo esc_attr( $data['retention_accounts'] ); ?>" class="regular-text"></td></tr>
                </table>
            </div>

            <p style="margin:20px 0;">
                <button type="button" class="button button-primary button-hero" id="gdpr-press-generate-pp">
                    <?php echo ! empty( $data['generated_page_id'] ) && get_post( $data['generated_page_id'] )
                        ? esc_html__( 'Aggiorna Privacy Policy', 'gdpr-press' )
                        : esc_html__( 'Genera Privacy Policy', 'gdpr-press' ); ?>
                </button>
                <span id="gdpr-press-pp-status" style="margin-left:12px;"></span>
            </p>
        </div>

        <script>
        (function() {
            document.getElementById('pp_dpo_enabled').addEventListener('change', function() {
                document.getElementById('pp_dpo_fields').style.display = this.checked ? '' : 'none';
            });
            document.getElementById('pp_transfers').addEventListener('change', function() {
                document.getElementById('pp_transfers_fields').style.display = this.checked ? '' : 'none';
            });

            document.getElementById('gdpr-press-generate-pp').addEventListener('click', function() {
                var btn = this;
                var status = document.getElementById('gdpr-press-pp-status');
                btn.disabled = true;
                status.textContent = '<?php echo esc_js( __( 'Generazione in corso...', 'gdpr-press' ) ); ?>';

                var purposes = {};
                document.querySelectorAll('.pp_purpose').forEach(function(cb) { purposes[cb.dataset.key] = cb.checked; });
                var services = {};
                document.querySelectorAll('.pp_service').forEach(function(cb) { services[cb.dataset.key] = cb.checked; });

                var fd = new FormData();
                fd.append('action', 'gdpr_press_generate_privacy_policy');
                fd.append('nonce', '<?php echo esc_js( $nonce ); ?>');
                fd.append('data', JSON.stringify({
                    controller_name: document.getElementById('pp_controller_name').value,
                    controller_address: document.getElementById('pp_controller_address').value,
                    controller_email: document.getElementById('pp_controller_email').value,
                    controller_phone: document.getElementById('pp_controller_phone').value,
                    controller_pec: document.getElementById('pp_controller_pec').value,
                    dpo_enabled: document.getElementById('pp_dpo_enabled').checked,
                    dpo_name: document.getElementById('pp_dpo_name').value,
                    dpo_email: document.getElementById('pp_dpo_email').value,
                    purposes: purposes,
                    services: services,
                    transfers_extra_eu: document.getElementById('pp_transfers').checked,
                    transfers_basis: (document.querySelector('input[name="pp_transfers_basis"]:checked') || {}).value || 'dpf',
                    retention_contact: document.getElementById('pp_ret_contact').value,
                    retention_analytics: document.getElementById('pp_ret_analytics').value,
                    retention_marketing: document.getElementById('pp_ret_marketing').value,
                    retention_ecommerce: document.getElementById('pp_ret_ecommerce').value,
                    retention_accounts: document.getElementById('pp_ret_accounts').value
                }));

                fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        btn.disabled = false;
                        if (res.success) {
                            status.innerHTML = '<?php echo esc_js( __( 'Privacy policy generata!', 'gdpr-press' ) ); ?> <a href="' + res.data.url + '" target="_blank"><?php echo esc_js( __( 'Visualizza', 'gdpr-press' ) ); ?></a>';
                            status.style.color = '#0f9b58';
                        } else {
                            status.textContent = res.data || 'Errore';
                            status.style.color = '#e94560';
                        }
                    })
                    .catch(function() { btn.disabled = false; status.textContent = 'Errore di rete'; status.style.color = '#e94560'; });
            });
        })();
        </script>
        <?php
    }

    /* ── AJAX: genera la pagina ──────────────────────────────────── */

    public static function ajax_generate(): void {
        check_ajax_referer( 'gdpr_press_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $raw = json_decode( wp_unslash( $_POST['data'] ?? '{}' ), true );
        if ( ! is_array( $raw ) ) {
            wp_send_json_error( __( 'Dati non validi.', 'gdpr-press' ) );
        }

        // Sanitizza
        $data = self::defaults();
        $text_fields = [ 'controller_name', 'controller_address', 'controller_email', 'controller_phone', 'controller_pec', 'dpo_name', 'dpo_email', 'retention_contact', 'retention_analytics', 'retention_marketing', 'retention_ecommerce', 'retention_accounts' ];
        foreach ( $text_fields as $f ) {
            $data[ $f ] = sanitize_text_field( $raw[ $f ] ?? '' );
        }
        $data['dpo_enabled']       = ! empty( $raw['dpo_enabled'] );
        $data['transfers_extra_eu'] = ! empty( $raw['transfers_extra_eu'] );
        $data['transfers_basis']    = in_array( $raw['transfers_basis'] ?? '', [ 'dpf', 'scc', 'consent' ], true ) ? $raw['transfers_basis'] : 'dpf';

        if ( is_array( $raw['purposes'] ?? null ) ) {
            foreach ( $data['purposes'] as $k => &$v ) {
                $v = ! empty( $raw['purposes'][ $k ] );
            }
        }
        if ( is_array( $raw['services'] ?? null ) ) {
            foreach ( $data['services'] as $k => &$v ) {
                $v = ! empty( $raw['services'][ $k ] );
            }
        }

        // Genera il contenuto
        $content = self::generate_content( $data );

        // Crea o aggiorna la pagina
        $existing_id = $data['generated_page_id'] ?? 0;
        $page_data = [
            'post_title'   => __( 'Privacy Policy', 'gdpr-press' ),
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ];

        if ( $existing_id && get_post( $existing_id ) ) {
            $page_data['ID'] = $existing_id;
            wp_update_post( $page_data );
            $page_id = $existing_id;
        } else {
            $page_id = wp_insert_post( $page_data );
        }

        if ( is_wp_error( $page_id ) ) {
            wp_send_json_error( $page_id->get_error_message() );
        }

        $data['generated_page_id'] = $page_id;
        update_option( self::OPTION, $data );

        // Imposta anche come pagina privacy di WordPress
        update_option( 'wp_page_for_privacy_policy', $page_id );

        wp_send_json_success( [
            'page_id' => $page_id,
            'url'     => get_permalink( $page_id ),
        ] );
    }

    /* ── Genera il contenuto della privacy policy ────────────────── */

    private static function generate_content( array $d ): string {
        $site = get_bloginfo( 'name' );
        $url  = home_url();
        $date = wp_date( 'j F Y' );

        $c = '';

        // Intro
        $c .= '<p>' . sprintf(
            __( 'La presente informativa sulla privacy descrive le modalità di raccolta e trattamento dei dati personali degli utenti che visitano il sito web %s (di seguito "il Sito"), ai sensi del Regolamento (UE) 2016/679 (GDPR) e del D.Lgs. 196/2003 (Codice Privacy).', 'gdpr-press' ),
            '<strong>' . esc_html( $url ) . '</strong>'
        ) . '</p>';

        // 1. Titolare
        $c .= '<h2>' . __( '1. Titolare del trattamento', 'gdpr-press' ) . '</h2>';
        $c .= '<p>' . __( 'Il titolare del trattamento dei dati personali è:', 'gdpr-press' ) . '</p>';
        $c .= '<p><strong>' . esc_html( $d['controller_name'] ) . '</strong><br>';
        if ( $d['controller_address'] ) $c .= esc_html( $d['controller_address'] ) . '<br>';
        $c .= 'Email: ' . esc_html( $d['controller_email'] );
        if ( $d['controller_phone'] ) $c .= '<br>' . __( 'Tel:', 'gdpr-press' ) . ' ' . esc_html( $d['controller_phone'] );
        if ( $d['controller_pec'] ) $c .= '<br>PEC: ' . esc_html( $d['controller_pec'] );
        $c .= '</p>';

        // 2. DPO
        if ( $d['dpo_enabled'] ) {
            $c .= '<h2>' . __( '2. Responsabile della Protezione dei Dati (DPO)', 'gdpr-press' ) . '</h2>';
            $c .= '<p>' . esc_html( $d['dpo_name'] );
            if ( $d['dpo_email'] ) $c .= ' — Email: ' . esc_html( $d['dpo_email'] );
            $c .= '</p>';
        }

        // 3. Finalità e basi giuridiche
        $c .= '<h2>' . __( '3. Finalità e basi giuridiche del trattamento', 'gdpr-press' ) . '</h2>';
        $c .= '<p>' . __( 'I tuoi dati personali vengono trattati per le seguenti finalità:', 'gdpr-press' ) . '</p>';

        $purpose_details = [
            'site_functionality' => [ __( 'Funzionamento del sito web', 'gdpr-press' ), __( 'Legittimo interesse (Art. 6.1.f GDPR)', 'gdpr-press' ), __( 'Garantire il corretto funzionamento tecnico del sito.', 'gdpr-press' ) ],
            'contact_forms'      => [ __( 'Gestione richieste di contatto', 'gdpr-press' ), __( 'Consenso (Art. 6.1.a GDPR) / Esecuzione di misure precontrattuali (Art. 6.1.b)', 'gdpr-press' ), __( 'Rispondere alle richieste inviate tramite i moduli di contatto.', 'gdpr-press' ) ],
            'analytics'          => [ __( 'Analisi statistiche', 'gdpr-press' ), __( 'Consenso (Art. 6.1.a GDPR)', 'gdpr-press' ), __( 'Raccogliere dati anonimi sull\'utilizzo del sito per migliorare i servizi offerti.', 'gdpr-press' ) ],
            'marketing'          => [ __( 'Marketing e pubblicità', 'gdpr-press' ), __( 'Consenso (Art. 6.1.a GDPR)', 'gdpr-press' ), __( 'Mostrare annunci personalizzati e misurare l\'efficacia delle campagne pubblicitarie.', 'gdpr-press' ) ],
            'newsletter'         => [ __( 'Invio newsletter', 'gdpr-press' ), __( 'Consenso (Art. 6.1.a GDPR)', 'gdpr-press' ), __( 'Inviare comunicazioni periodiche su novità, offerte e contenuti.', 'gdpr-press' ) ],
            'ecommerce'          => [ __( 'E-commerce', 'gdpr-press' ), __( 'Esecuzione del contratto (Art. 6.1.b GDPR) / Obbligo legale (Art. 6.1.c)', 'gdpr-press' ), __( 'Gestire ordini, pagamenti, spedizioni e obblighi fiscali.', 'gdpr-press' ) ],
            'user_accounts'      => [ __( 'Gestione account utente', 'gdpr-press' ), __( 'Esecuzione del contratto (Art. 6.1.b GDPR)', 'gdpr-press' ), __( 'Consentire la registrazione e la gestione dell\'area personale.', 'gdpr-press' ) ],
            'comments'           => [ __( 'Commenti', 'gdpr-press' ), __( 'Consenso (Art. 6.1.a GDPR)', 'gdpr-press' ), __( 'Consentire la pubblicazione di commenti sul blog.', 'gdpr-press' ) ],
        ];

        $c .= '<table style="width:100%;border-collapse:collapse;"><thead><tr><th style="border:1px solid #ddd;padding:8px;text-align:left;">' . __( 'Finalità', 'gdpr-press' ) . '</th><th style="border:1px solid #ddd;padding:8px;text-align:left;">' . __( 'Base giuridica', 'gdpr-press' ) . '</th><th style="border:1px solid #ddd;padding:8px;text-align:left;">' . __( 'Descrizione', 'gdpr-press' ) . '</th></tr></thead><tbody>';
        foreach ( $d['purposes'] as $key => $enabled ) {
            if ( $enabled && isset( $purpose_details[ $key ] ) ) {
                $pd = $purpose_details[ $key ];
                $c .= '<tr><td style="border:1px solid #ddd;padding:8px;">' . $pd[0] . '</td><td style="border:1px solid #ddd;padding:8px;">' . $pd[1] . '</td><td style="border:1px solid #ddd;padding:8px;">' . $pd[2] . '</td></tr>';
            }
        }
        $c .= '</tbody></table>';

        // 4. Dati raccolti
        $c .= '<h2>' . __( '4. Tipologie di dati raccolti', 'gdpr-press' ) . '</h2>';
        $c .= '<p>' . __( 'Il Sito può raccogliere le seguenti tipologie di dati personali:', 'gdpr-press' ) . '</p>';
        $c .= '<ul>';
        $c .= '<li>' . __( '<strong>Dati di navigazione:</strong> indirizzo IP, tipo di browser, sistema operativo, pagine visitate, data e ora di accesso. Questi dati sono raccolti automaticamente dai sistemi informatici del Sito.', 'gdpr-press' ) . '</li>';
        if ( ! empty( $d['purposes']['contact_forms'] ) ) $c .= '<li>' . __( '<strong>Dati forniti volontariamente:</strong> nome, email, telefono e altri dati inseriti nei moduli di contatto.', 'gdpr-press' ) . '</li>';
        if ( ! empty( $d['purposes']['ecommerce'] ) ) $c .= '<li>' . __( '<strong>Dati di acquisto:</strong> indirizzo di fatturazione e spedizione, dati di pagamento (gestiti da fornitori terzi certificati PCI-DSS).', 'gdpr-press' ) . '</li>';
        if ( ! empty( $d['purposes']['user_accounts'] ) ) $c .= '<li>' . __( '<strong>Dati di registrazione:</strong> nome utente, email, password (criptata).', 'gdpr-press' ) . '</li>';
        $c .= '<li>' . __( '<strong>Cookie e tecnologie di tracciamento:</strong> per maggiori informazioni, consulta la nostra Cookie Policy.', 'gdpr-press' ) . '</li>';
        $c .= '</ul>';

        // 5. Servizi terze parti
        $active_services = array_filter( $d['services'] );
        if ( ! empty( $active_services ) ) {
            $c .= '<h2>' . __( '5. Servizi di terze parti', 'gdpr-press' ) . '</h2>';
            $c .= '<p>' . __( 'Il Sito utilizza i seguenti servizi di terze parti che possono raccogliere dati personali:', 'gdpr-press' ) . '</p>';

            $service_details = [
                'google_analytics' => [ 'Google Analytics', 'Google LLC', __( 'Servizio di analisi statistica. Cookie utilizzati: _ga, _gid, _gat.', 'gdpr-press' ), 'https://policies.google.com/privacy' ],
                'google_ads'       => [ 'Google Ads', 'Google LLC', __( 'Servizio di pubblicità. Può installare cookie di remarketing e conversione.', 'gdpr-press' ), 'https://policies.google.com/privacy' ],
                'google_maps'      => [ 'Google Maps', 'Google LLC', __( 'Servizio di mappe interattive.', 'gdpr-press' ), 'https://policies.google.com/privacy' ],
                'google_fonts'     => [ 'Google Fonts', 'Google LLC', __( 'Servizio di hosting font tipografici.', 'gdpr-press' ), 'https://policies.google.com/privacy' ],
                'youtube'          => [ 'YouTube', 'Google LLC', __( 'Piattaforma di hosting video. Può installare cookie di profilazione.', 'gdpr-press' ), 'https://policies.google.com/privacy' ],
                'facebook_pixel'   => [ 'Facebook Pixel', 'Meta Platforms Inc.', __( 'Strumento di tracciamento per pubblicità su Facebook/Instagram.', 'gdpr-press' ), 'https://www.facebook.com/privacy/policy/' ],
                'mailchimp'        => [ 'Mailchimp', 'Intuit Inc.', __( 'Servizio di email marketing per l\'invio di newsletter.', 'gdpr-press' ), 'https://www.intuit.com/privacy/statement/' ],
                'stripe'           => [ 'Stripe', 'Stripe Inc.', __( 'Servizio di elaborazione pagamenti.', 'gdpr-press' ), 'https://stripe.com/privacy' ],
                'paypal'           => [ 'PayPal', 'PayPal Holdings Inc.', __( 'Servizio di elaborazione pagamenti.', 'gdpr-press' ), 'https://www.paypal.com/privacy' ],
                'cloudflare'       => [ 'Cloudflare', 'Cloudflare Inc.', __( 'Servizio CDN e di sicurezza web.', 'gdpr-press' ), 'https://www.cloudflare.com/privacypolicy/' ],
                'hotjar'           => [ 'Hotjar', 'Hotjar Ltd.', __( 'Servizio di analisi comportamentale (heatmap, registrazioni sessioni).', 'gdpr-press' ), 'https://www.hotjar.com/privacy/' ],
            ];

            $c .= '<table style="width:100%;border-collapse:collapse;"><thead><tr><th style="border:1px solid #ddd;padding:8px;">' . __( 'Servizio', 'gdpr-press' ) . '</th><th style="border:1px solid #ddd;padding:8px;">' . __( 'Fornitore', 'gdpr-press' ) . '</th><th style="border:1px solid #ddd;padding:8px;">' . __( 'Descrizione', 'gdpr-press' ) . '</th><th style="border:1px solid #ddd;padding:8px;">' . __( 'Privacy Policy', 'gdpr-press' ) . '</th></tr></thead><tbody>';
            foreach ( $active_services as $key => $v ) {
                if ( isset( $service_details[ $key ] ) ) {
                    $sd = $service_details[ $key ];
                    $c .= '<tr><td style="border:1px solid #ddd;padding:8px;">' . $sd[0] . '</td><td style="border:1px solid #ddd;padding:8px;">' . $sd[1] . '</td><td style="border:1px solid #ddd;padding:8px;">' . $sd[2] . '</td><td style="border:1px solid #ddd;padding:8px;"><a href="' . esc_url( $sd[3] ) . '" target="_blank" rel="noopener">Link</a></td></tr>';
                }
            }
            $c .= '</tbody></table>';
        }

        // 6. Trasferimenti extra-UE
        if ( $d['transfers_extra_eu'] ) {
            $c .= '<h2>' . __( '6. Trasferimento dei dati fuori dall\'UE', 'gdpr-press' ) . '</h2>';
            $c .= '<p>' . __( 'Alcuni dei servizi di terze parti utilizzati comportano il trasferimento dei dati personali verso paesi situati al di fuori dello Spazio Economico Europeo (SEE).', 'gdpr-press' ) . '</p>';
            $basis_texts = [
                'dpf'     => __( 'Tali trasferimenti avvengono sulla base del EU-US Data Privacy Framework (DPF), decisione di adeguatezza della Commissione Europea del 10 luglio 2023, verso aziende certificate sotto il DPF.', 'gdpr-press' ),
                'scc'     => __( 'Tali trasferimenti avvengono sulla base delle Clausole Contrattuali Standard (SCC) approvate dalla Commissione Europea, che garantiscono un livello adeguato di protezione dei dati.', 'gdpr-press' ),
                'consent' => __( 'Tali trasferimenti avvengono sulla base del tuo consenso esplicito, prestato ai sensi dell\'Art. 49(1)(a) del GDPR.', 'gdpr-press' ),
            ];
            $c .= '<p>' . ( $basis_texts[ $d['transfers_basis'] ] ?? $basis_texts['dpf'] ) . '</p>';
        }

        // 7. Conservazione
        $c .= '<h2>' . __( '7. Periodo di conservazione dei dati', 'gdpr-press' ) . '</h2>';
        $c .= '<p>' . __( 'I dati personali sono conservati per il tempo strettamente necessario al raggiungimento delle finalità per cui sono stati raccolti:', 'gdpr-press' ) . '</p>';
        $c .= '<ul>';
        if ( ! empty( $d['purposes']['contact_forms'] ) ) $c .= '<li>' . __( 'Dati dei moduli di contatto:', 'gdpr-press' ) . ' ' . esc_html( $d['retention_contact'] ) . '</li>';
        if ( ! empty( $d['purposes']['analytics'] ) ) $c .= '<li>' . __( 'Dati analitici:', 'gdpr-press' ) . ' ' . esc_html( $d['retention_analytics'] ) . '</li>';
        if ( ! empty( $d['purposes']['marketing'] ) || ! empty( $d['purposes']['newsletter'] ) ) $c .= '<li>' . __( 'Dati marketing/newsletter:', 'gdpr-press' ) . ' ' . esc_html( $d['retention_marketing'] ) . '</li>';
        if ( ! empty( $d['purposes']['ecommerce'] ) ) $c .= '<li>' . __( 'Dati e-commerce/fatturazione:', 'gdpr-press' ) . ' ' . esc_html( $d['retention_ecommerce'] ) . '</li>';
        if ( ! empty( $d['purposes']['user_accounts'] ) ) $c .= '<li>' . __( 'Dati account utente:', 'gdpr-press' ) . ' ' . esc_html( $d['retention_accounts'] ) . '</li>';
        $c .= '</ul>';

        // 8. Diritti
        $c .= '<h2>' . __( '8. I tuoi diritti', 'gdpr-press' ) . '</h2>';
        $c .= '<p>' . __( 'Ai sensi degli Artt. 15-22 del GDPR, hai il diritto di:', 'gdpr-press' ) . '</p>';
        $c .= '<ul>';
        $c .= '<li>' . __( '<strong>Accesso</strong> (Art. 15): ottenere conferma del trattamento dei tuoi dati e una copia degli stessi.', 'gdpr-press' ) . '</li>';
        $c .= '<li>' . __( '<strong>Rettifica</strong> (Art. 16): ottenere la correzione dei dati inesatti o l\'integrazione di quelli incompleti.', 'gdpr-press' ) . '</li>';
        $c .= '<li>' . __( '<strong>Cancellazione</strong> (Art. 17): ottenere la cancellazione dei tuoi dati ("diritto all\'oblio").', 'gdpr-press' ) . '</li>';
        $c .= '<li>' . __( '<strong>Limitazione</strong> (Art. 18): ottenere la limitazione del trattamento in determinati casi.', 'gdpr-press' ) . '</li>';
        $c .= '<li>' . __( '<strong>Portabilità</strong> (Art. 20): ricevere i tuoi dati in un formato strutturato e di uso comune.', 'gdpr-press' ) . '</li>';
        $c .= '<li>' . __( '<strong>Opposizione</strong> (Art. 21): opporti in qualsiasi momento al trattamento dei tuoi dati basato sul legittimo interesse.', 'gdpr-press' ) . '</li>';
        $c .= '<li>' . __( '<strong>Revoca del consenso</strong> (Art. 7.3): revocare in qualsiasi momento il consenso prestato, senza che ciò pregiudichi la liceità del trattamento precedente.', 'gdpr-press' ) . '</li>';
        $c .= '</ul>';
        $c .= '<p>' . sprintf( __( 'Per esercitare i tuoi diritti, puoi contattarci all\'indirizzo email: <strong>%s</strong>', 'gdpr-press' ), esc_html( $d['controller_email'] ) ) . '</p>';

        // 9. Reclamo
        $c .= '<h2>' . __( '9. Diritto di reclamo', 'gdpr-press' ) . '</h2>';
        $c .= '<p>' . __( 'Hai il diritto di proporre reclamo all\'autorità di controllo competente. In Italia, l\'autorità è il Garante per la Protezione dei Dati Personali:', 'gdpr-press' ) . '</p>';
        $c .= '<p>Garante per la Protezione dei Dati Personali<br>';
        $c .= 'Piazza Venezia 11, 00187 Roma<br>';
        $c .= 'www.garanteprivacy.it<br>';
        $c .= 'Email: protocollo@gpdp.it — PEC: protocollo@pec.gpdp.it</p>';

        // 10. Aggiornamenti
        $c .= '<h2>' . __( '10. Aggiornamenti della presente informativa', 'gdpr-press' ) . '</h2>';
        $c .= '<p>' . __( 'La presente informativa può essere soggetta ad aggiornamenti. Ti invitiamo a consultare periodicamente questa pagina per verificare eventuali modifiche.', 'gdpr-press' ) . '</p>';
        $c .= '<p><em>' . sprintf( __( 'Ultimo aggiornamento: %s', 'gdpr-press' ), $date ) . '</em></p>';

        return $c;
    }

    /* ── Shortcode (rendering dinamico) ──────────────────────────── */

    public static function shortcode(): string {
        $data = wp_parse_args( get_option( self::OPTION, [] ), self::defaults() );
        if ( empty( $data['controller_name'] ) ) {
            return '<p>' . __( 'La privacy policy non è ancora stata configurata. Vai in Impostazioni → GDPR Press per generarla.', 'gdpr-press' ) . '</p>';
        }
        return self::generate_content( $data );
    }
}
