<?php
/**
 * Gestione diritti degli interessati (Artt. 15-22 GDPR).
 *
 * - Shortcode [scudo_rights_form] per il form frontend
 * - Integrazione con WordPress export/erase data
 * - Notifica email al titolare
 */

defined( 'ABSPATH' ) || exit;

class Scudo_Rights {

    private const TABLE = 'scudo_rights_requests';

    public static function init(): void {
        add_shortcode( 'scudo_rights_form', [ __CLASS__, 'shortcode_form' ] );
        add_action( 'wp_ajax_scudo_submit_rights_request', [ __CLASS__, 'ajax_submit' ] );
        add_action( 'wp_ajax_nopriv_scudo_submit_rights_request', [ __CLASS__, 'ajax_submit' ] );

        // Integra con WordPress Privacy Tools
        add_filter( 'wp_privacy_personal_data_exporters', [ __CLASS__, 'register_exporter' ] );
        add_filter( 'wp_privacy_personal_data_erasers', [ __CLASS__, 'register_eraser' ] );
    }

    /* ── Attivazione: crea tabella ───────────────────────────────── */

    public static function create_table(): void {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_type VARCHAR(30) NOT NULL,
            name VARCHAR(200) NOT NULL,
            email VARCHAR(200) NOT NULL,
            message TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            resolved_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_email (email),
            KEY idx_status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ── Shortcode: form esercizio diritti ────────────────────────── */

    public static function shortcode_form(): string {
        // Token anti-CSRF semplice per form pubblici (non dipende dalla sessione utente)
        $token = hash_hmac( 'sha256', 'scudo_rights_' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) );

        $types = [
            'access'       => __( 'Accesso ai miei dati (Art. 15)', 'scudo-cookie-privacy' ),
            'rectification' => __( 'Rettifica dei miei dati (Art. 16)', 'scudo-cookie-privacy' ),
            'erasure'      => __( 'Cancellazione dei miei dati (Art. 17)', 'scudo-cookie-privacy' ),
            'restriction'  => __( 'Limitazione del trattamento (Art. 18)', 'scudo-cookie-privacy' ),
            'portability'  => __( 'Portabilità dei miei dati (Art. 20)', 'scudo-cookie-privacy' ),
            'objection'    => __( 'Opposizione al trattamento (Art. 21)', 'scudo-cookie-privacy' ),
        ];

        $html = '<div class="scudo-rights-form" id="scudo-rights-form">';
        $html .= '<form id="gdpr-rights-form">';

        // Tipo di richiesta
        $html .= '<div style="margin-bottom:16px;">';
        $html .= '<label for="gdpr_right_type" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Tipo di richiesta *', 'scudo-cookie-privacy' ) . '</label>';
        $html .= '<select name="request_type" id="gdpr_right_type" required style="width:100%;max-width:500px;padding:8px;">';
        $html .= '<option value="">' . esc_html__( '— Seleziona —', 'scudo-cookie-privacy' ) . '</option>';
        foreach ( $types as $val => $label ) {
            $html .= '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
        }
        $html .= '</select></div>';

        // Nome
        $html .= '<div style="margin-bottom:16px;">';
        $html .= '<label for="gdpr_right_name" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Nome e Cognome *', 'scudo-cookie-privacy' ) . '</label>';
        $html .= '<input type="text" name="name" id="gdpr_right_name" required style="width:100%;max-width:500px;padding:8px;">';
        $html .= '</div>';

        // Email
        $html .= '<div style="margin-bottom:16px;">';
        $html .= '<label for="gdpr_right_email" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Email *', 'scudo-cookie-privacy' ) . '</label>';
        $html .= '<input type="email" name="email" id="gdpr_right_email" required style="width:100%;max-width:500px;padding:8px;">';
        $html .= '<p style="font-size:0.85em;color:#666;margin-top:4px;">' . esc_html__( 'Utilizzeremo questo indirizzo per rispondere alla tua richiesta e per verificare la tua identità.', 'scudo-cookie-privacy' ) . '</p>';
        $html .= '</div>';

        // Messaggio
        $html .= '<div style="margin-bottom:16px;">';
        $html .= '<label for="gdpr_right_message" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Dettagli della richiesta', 'scudo-cookie-privacy' ) . '</label>';
        $html .= '<textarea name="message" id="gdpr_right_message" rows="4" style="width:100%;max-width:500px;padding:8px;"></textarea>';
        $html .= '</div>';

        // Honeypot (anti-spam)
        $html .= '<div style="opacity:0;position:absolute;top:0;left:0;height:0;width:0;z-index:-1;overflow:hidden;" aria-hidden="true"><input type="text" name="scudo_hp" tabindex="-1" autocomplete="new-password"></div>';

        $html .= '<input type="hidden" name="scudo_token" value="' . esc_attr( $token ) . '">';

        $html .= '<button type="submit" style="background:#1a1a2e;color:#fff;border:none;border-radius:6px;padding:12px 24px;font-size:14px;font-weight:600;cursor:pointer;min-height:44px;">';
        $html .= esc_html__( 'Invia richiesta', 'scudo-cookie-privacy' );
        $html .= '</button>';

        $html .= '<div id="gdpr-rights-status" style="margin-top:16px;"></div>';

        $html .= '</form></div>';

        // Enqueue inline JS for AJAX form submission.
        wp_register_script( 'scudo-rights', '', array(), SCUDO_VERSION, true );
        wp_enqueue_script( 'scudo-rights' );
        wp_localize_script( 'scudo-rights', 'scudoRights', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'i18n'     => array(
                'sending'        => __( 'Invio in corso...', 'scudo-cookie-privacy' ),
                'sessionExpired' => __( 'Sessione scaduta. Ricarica la pagina e riprova.', 'scudo-cookie-privacy' ),
                'unknownError'   => __( 'Errore sconosciuto. Ricarica la pagina e riprova.', 'scudo-cookie-privacy' ),
                'networkError'   => __( 'Errore di rete. Riprova.', 'scudo-cookie-privacy' ),
            ),
        ) );
        $rights_js = '(function(){' .
            'var form=document.getElementById("gdpr-rights-form");' .
            'if(!form)return;' .
            'form.addEventListener("submit",function(e){' .
                'e.preventDefault();' .
                'var status=document.getElementById("gdpr-rights-status");' .
                'var btn=form.querySelector("button[type=submit]");' .
                'btn.disabled=true;' .
                'status.textContent=scudoRights.i18n.sending;' .
                'var fd=new FormData(form);' .
                'fd.append("action","scudo_submit_rights_request");' .
                'fetch(scudoRights.ajaxUrl,{method:"POST",body:fd,credentials:"same-origin"})' .
                    '.then(function(r){' .
                        'var ct=r.headers.get("content-type")||"";' .
                        'if(ct.indexOf("application/json")!==-1)return r.json();' .
                        'return r.text().then(function(t){return{success:false,data:scudoRights.i18n.sessionExpired};});' .
                    '})' .
                    '.then(function(data){' .
                        'btn.disabled=false;' .
                        'if(data.success){' .
                            'status.innerHTML="<p style=\"color:#0f9b58;font-weight:600;\">"+data.data.message+"</p>";' .
                            'form.reset();' .
                        '}else{' .
                            'var msg=typeof data.data==="string"?data.data:(data.data&&data.data.message?data.data.message:scudoRights.i18n.unknownError);' .
                            'status.innerHTML="<p style=\"color:#e94560;\">"+msg+"</p>";' .
                        '}' .
                    '})' .
                    '.catch(function(){' .
                        'btn.disabled=false;' .
                        'status.innerHTML="<p style=\"color:#e94560;\">"+scudoRights.i18n.networkError+"</p>";' .
                    '});' .
            '});' .
        '})();';
        wp_add_inline_script( 'scudo-rights', $rights_js );

        return $html;
    }

    /* ── AJAX: submit richiesta ──────────────────────────────────── */

    public static function ajax_submit(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- uses HMAC token instead of nonce (public form, no user session)
        $token = sanitize_text_field( wp_unslash( $_POST['scudo_token'] ?? '' ) );
        $expected_today = hash_hmac( 'sha256', 'scudo_rights_' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) );
        $expected_yesterday = hash_hmac( 'sha256', 'scudo_rights_' . gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ), wp_salt( 'nonce' ) );

        if ( ! hash_equals( $expected_today, $token ) && ! hash_equals( $expected_yesterday, $token ) ) {
            wp_send_json_error( __( 'Token scaduto. Ricarica la pagina e riprova.', 'scudo-cookie-privacy' ), 403 );
        }

        // Honeypot check
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! empty( $_POST['scudo_hp'] ) ) {
            wp_send_json_error( __( 'Invio non riuscito. Riprova.', 'scudo-cookie-privacy' ), 400 );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- HMAC token verified above
        $type    = sanitize_text_field( wp_unslash( $_POST['request_type'] ?? '' ) );
        $name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $valid_types = [ 'access', 'rectification', 'erasure', 'restriction', 'portability', 'objection' ];

        if ( ! in_array( $type, $valid_types, true ) || empty( $name ) || ! is_email( $email ) ) {
            wp_send_json_error( __( 'Compila tutti i campi obbligatori.', 'scudo-cookie-privacy' ) );
        }

        // Salva nel DB
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            [
                'request_type' => $type,
                'name'         => $name,
                'email'        => $email,
                'message'      => $message,
                'status'       => 'pending',
                'created_at'   => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        // Crea anche la richiesta nativa di WordPress (per export/erasure)
        if ( in_array( $type, [ 'access', 'portability' ], true ) ) {
            wp_create_user_request( $email, 'export_personal_data' );
        } elseif ( $type === 'erasure' ) {
            wp_create_user_request( $email, 'remove_personal_data' );
        }

        // Notifica email al titolare
        self::notify_controller( $type, $name, $email, $message );

        wp_send_json_success( [
            'message' => __( 'La tua richiesta è stata inviata con successo. Ti risponderemo entro 30 giorni come previsto dal GDPR.', 'scudo-cookie-privacy' ),
        ] );
    }

    /* ── Notifica email ──────────────────────────────────────────── */

    private static function notify_controller( string $type, string $name, string $email, string $message ): void {
        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );

        $type_labels = [
            'access'        => __( 'Accesso ai dati', 'scudo-cookie-privacy' ),
            'rectification' => __( 'Rettifica dei dati', 'scudo-cookie-privacy' ),
            'erasure'       => __( 'Cancellazione dei dati', 'scudo-cookie-privacy' ),
            'restriction'   => __( 'Limitazione del trattamento', 'scudo-cookie-privacy' ),
            'portability'   => __( 'Portabilità dei dati', 'scudo-cookie-privacy' ),
            'objection'     => __( 'Opposizione al trattamento', 'scudo-cookie-privacy' ),
        ];

        $type_label = $type_labels[ $type ] ?? $type;

        $subject = sprintf( '[%s] %s — %s', $site_name, __( 'Richiesta GDPR', 'scudo-cookie-privacy' ), $type_label );

        // translators: %s is the website name.
        $body  = sprintf( __( 'Nuova richiesta di esercizio diritti GDPR su %s', 'scudo-cookie-privacy' ), $site_name ) . "\n\n";
        $body .= __( 'Tipo:', 'scudo-cookie-privacy' ) . ' ' . $type_label . "\n";
        $body .= __( 'Nome:', 'scudo-cookie-privacy' ) . ' ' . $name . "\n";
        $body .= __( 'Email:', 'scudo-cookie-privacy' ) . ' ' . $email . "\n";
        if ( $message ) {
            $body .= __( 'Messaggio:', 'scudo-cookie-privacy' ) . "\n" . $message . "\n";
        }
        $body .= "\n" . __( 'Ricorda: il GDPR prevede una risposta entro 30 giorni dalla richiesta.', 'scudo-cookie-privacy' );
        $body .= "\n\n" . admin_url( 'options-general.php?page=scudo' );

        wp_mail( $admin_email, $subject, $body );
    }

    /* ── WordPress Privacy Tools: Exporter ───────────────────────── */

    public static function register_exporter( array $exporters ): array {
        $exporters['scudo-consent'] = [
            'exporter_friendly_name' => __( 'Scudo — Consensi', 'scudo-cookie-privacy' ),
            'callback'               => [ __CLASS__, 'export_consent_data' ],
        ];
        return $exporters;
    }

    public static function export_consent_data( string $email, int $page = 1 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'scudo_consent_log';

        // Non possiamo cercare per email (salviamo solo IP hash),
        // ma possiamo cercare per consent_id se l'utente è loggato
        $data = [ 'data' => [], 'done' => true ];

        return $data;
    }

    /* ── WordPress Privacy Tools: Eraser ─────────────────────────── */

    public static function register_eraser( array $erasers ): array {
        $erasers['scudo-consent'] = [
            'eraser_friendly_name' => __( 'Scudo — Consensi', 'scudo-cookie-privacy' ),
            'callback'             => [ __CLASS__, 'erase_consent_data' ],
        ];
        return $erasers;
    }

    public static function erase_consent_data( string $email, int $page = 1 ): array {
        // I log del consenso usano IP hash, non email.
        // Non possiamo quindi cancellare per email in modo affidabile.
        // Tuttavia, cancelliamo le richieste diritti associate all'email.
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete( $table, [ 'email' => $email ], [ '%s' ] );

        return [
            'items_removed'  => (int) $deleted,
            'items_retained' => false,
            'messages'       => [],
            'done'           => true,
        ];
    }

    /* ── Lista richieste per l'admin ─────────────────────────────── */

    public static function get_requests( int $limit = 50 ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }
}
