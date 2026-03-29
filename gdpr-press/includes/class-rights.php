<?php
/**
 * Gestione diritti degli interessati (Artt. 15-22 GDPR).
 *
 * - Shortcode [gdpr_press_rights_form] per il form frontend
 * - Integrazione con WordPress export/erase data
 * - Notifica email al titolare
 */

defined( 'ABSPATH' ) || exit;

class GDPR_Press_Rights {

    private const TABLE = 'gdpr_press_rights_requests';

    public static function init(): void {
        add_shortcode( 'gdpr_press_rights_form', [ __CLASS__, 'shortcode_form' ] );
        add_action( 'wp_ajax_gdpr_press_submit_rights_request', [ __CLASS__, 'ajax_submit' ] );
        add_action( 'wp_ajax_nopriv_gdpr_press_submit_rights_request', [ __CLASS__, 'ajax_submit' ] );

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
        $nonce = wp_create_nonce( 'gdpr_press_rights_nonce' );

        $types = [
            'access'       => __( 'Accesso ai miei dati (Art. 15)', 'gdpr-press' ),
            'rectification' => __( 'Rettifica dei miei dati (Art. 16)', 'gdpr-press' ),
            'erasure'      => __( 'Cancellazione dei miei dati (Art. 17)', 'gdpr-press' ),
            'restriction'  => __( 'Limitazione del trattamento (Art. 18)', 'gdpr-press' ),
            'portability'  => __( 'Portabilità dei miei dati (Art. 20)', 'gdpr-press' ),
            'objection'    => __( 'Opposizione al trattamento (Art. 21)', 'gdpr-press' ),
        ];

        $html = '<div class="gdpr-press-rights-form" id="gdpr-press-rights-form">';
        $html .= '<form id="gdpr-rights-form">';

        // Tipo di richiesta
        $html .= '<div style="margin-bottom:16px;">';
        $html .= '<label for="gdpr_right_type" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Tipo di richiesta *', 'gdpr-press' ) . '</label>';
        $html .= '<select name="request_type" id="gdpr_right_type" required style="width:100%;max-width:500px;padding:8px;">';
        $html .= '<option value="">' . esc_html__( '— Seleziona —', 'gdpr-press' ) . '</option>';
        foreach ( $types as $val => $label ) {
            $html .= '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
        }
        $html .= '</select></div>';

        // Nome
        $html .= '<div style="margin-bottom:16px;">';
        $html .= '<label for="gdpr_right_name" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Nome e Cognome *', 'gdpr-press' ) . '</label>';
        $html .= '<input type="text" name="name" id="gdpr_right_name" required style="width:100%;max-width:500px;padding:8px;">';
        $html .= '</div>';

        // Email
        $html .= '<div style="margin-bottom:16px;">';
        $html .= '<label for="gdpr_right_email" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Email *', 'gdpr-press' ) . '</label>';
        $html .= '<input type="email" name="email" id="gdpr_right_email" required style="width:100%;max-width:500px;padding:8px;">';
        $html .= '<p style="font-size:0.85em;color:#666;margin-top:4px;">' . esc_html__( 'Utilizzeremo questo indirizzo per rispondere alla tua richiesta e per verificare la tua identità.', 'gdpr-press' ) . '</p>';
        $html .= '</div>';

        // Messaggio
        $html .= '<div style="margin-bottom:16px;">';
        $html .= '<label for="gdpr_right_message" style="display:block;font-weight:600;margin-bottom:6px;">' . esc_html__( 'Dettagli della richiesta', 'gdpr-press' ) . '</label>';
        $html .= '<textarea name="message" id="gdpr_right_message" rows="4" style="width:100%;max-width:500px;padding:8px;"></textarea>';
        $html .= '</div>';

        // Honeypot (anti-spam)
        $html .= '<div style="position:absolute;left:-9999px;" aria-hidden="true"><input type="text" name="gdpr_press_hp" tabindex="-1" autocomplete="off"></div>';

        $html .= '<input type="hidden" name="nonce" value="' . esc_attr( $nonce ) . '">';

        $html .= '<button type="submit" style="background:#1a1a2e;color:#fff;border:none;border-radius:6px;padding:12px 24px;font-size:14px;font-weight:600;cursor:pointer;min-height:44px;">';
        $html .= esc_html__( 'Invia richiesta', 'gdpr-press' );
        $html .= '</button>';

        $html .= '<div id="gdpr-rights-status" style="margin-top:16px;"></div>';

        $html .= '</form></div>';

        // JS inline per submit AJAX
        $html .= '<script>
        (function(){
            var form = document.getElementById("gdpr-rights-form");
            if(!form)return;
            form.addEventListener("submit",function(e){
                e.preventDefault();
                var status = document.getElementById("gdpr-rights-status");
                var btn = form.querySelector("button[type=submit]");
                btn.disabled = true;
                status.textContent = "' . esc_js( __( 'Invio in corso...', 'gdpr-press' ) ) . '";
                var fd = new FormData(form);
                fd.append("action","gdpr_press_submit_rights_request");
                fetch("' . esc_url( admin_url( 'admin-ajax.php' ) ) . '",{method:"POST",body:fd,credentials:"same-origin"})
                    .then(function(r){return r.json()})
                    .then(function(data){
                        btn.disabled = false;
                        if(data.success){
                            status.innerHTML = "<p style=\"color:#0f9b58;font-weight:600;\">" + data.data.message + "</p>";
                            form.reset();
                        } else {
                            status.innerHTML = "<p style=\"color:#e94560;\">" + (data.data || "Errore") + "</p>";
                        }
                    })
                    .catch(function(){
                        btn.disabled = false;
                        status.innerHTML = "<p style=\"color:#e94560;\">' . esc_js( __( 'Errore di rete. Riprova.', 'gdpr-press' ) ) . '</p>";
                    });
            });
        })();
        </script>';

        return $html;
    }

    /* ── AJAX: submit richiesta ──────────────────────────────────── */

    public static function ajax_submit(): void {
        check_ajax_referer( 'gdpr_press_rights_nonce', 'nonce' );

        // Honeypot check
        if ( ! empty( $_POST['gdpr_press_hp'] ) ) {
            wp_send_json_error( 'spam', 400 );
        }

        $type    = sanitize_text_field( wp_unslash( $_POST['request_type'] ?? '' ) );
        $name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

        $valid_types = [ 'access', 'rectification', 'erasure', 'restriction', 'portability', 'objection' ];

        if ( ! in_array( $type, $valid_types, true ) || empty( $name ) || ! is_email( $email ) ) {
            wp_send_json_error( __( 'Compila tutti i campi obbligatori.', 'gdpr-press' ) );
        }

        // Salva nel DB
        global $wpdb;
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
            'message' => __( 'La tua richiesta è stata inviata con successo. Ti risponderemo entro 30 giorni come previsto dal GDPR.', 'gdpr-press' ),
        ] );
    }

    /* ── Notifica email ──────────────────────────────────────────── */

    private static function notify_controller( string $type, string $name, string $email, string $message ): void {
        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );

        $type_labels = [
            'access'        => __( 'Accesso ai dati', 'gdpr-press' ),
            'rectification' => __( 'Rettifica dei dati', 'gdpr-press' ),
            'erasure'       => __( 'Cancellazione dei dati', 'gdpr-press' ),
            'restriction'   => __( 'Limitazione del trattamento', 'gdpr-press' ),
            'portability'   => __( 'Portabilità dei dati', 'gdpr-press' ),
            'objection'     => __( 'Opposizione al trattamento', 'gdpr-press' ),
        ];

        $type_label = $type_labels[ $type ] ?? $type;

        $subject = sprintf( '[%s] %s — %s', $site_name, __( 'Richiesta GDPR', 'gdpr-press' ), $type_label );

        $body  = sprintf( __( 'Nuova richiesta di esercizio diritti GDPR su %s', 'gdpr-press' ), $site_name ) . "\n\n";
        $body .= __( 'Tipo:', 'gdpr-press' ) . ' ' . $type_label . "\n";
        $body .= __( 'Nome:', 'gdpr-press' ) . ' ' . $name . "\n";
        $body .= __( 'Email:', 'gdpr-press' ) . ' ' . $email . "\n";
        if ( $message ) {
            $body .= __( 'Messaggio:', 'gdpr-press' ) . "\n" . $message . "\n";
        }
        $body .= "\n" . __( 'Ricorda: il GDPR prevede una risposta entro 30 giorni dalla richiesta.', 'gdpr-press' );
        $body .= "\n\n" . admin_url( 'options-general.php?page=gdpr-press' );

        wp_mail( $admin_email, $subject, $body );
    }

    /* ── WordPress Privacy Tools: Exporter ───────────────────────── */

    public static function register_exporter( array $exporters ): array {
        $exporters['gdpr-press-consent'] = [
            'exporter_friendly_name' => __( 'GDPR Press — Consensi', 'gdpr-press' ),
            'callback'               => [ __CLASS__, 'export_consent_data' ],
        ];
        return $exporters;
    }

    public static function export_consent_data( string $email, int $page = 1 ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'gdpr_press_consent_log';

        // Non possiamo cercare per email (salviamo solo IP hash),
        // ma possiamo cercare per consent_id se l'utente è loggato
        $data = [ 'data' => [], 'done' => true ];

        return $data;
    }

    /* ── WordPress Privacy Tools: Eraser ─────────────────────────── */

    public static function register_eraser( array $erasers ): array {
        $erasers['gdpr-press-consent'] = [
            'eraser_friendly_name' => __( 'GDPR Press — Consensi', 'gdpr-press' ),
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

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }
}
