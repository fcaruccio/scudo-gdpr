<?php
/**
 * Gestione consenso: cookie, logging nel DB, scadenza, revoca.
 */

defined( 'ABSPATH' ) || exit;

class GDPR_Press_Consent {

    private const TABLE   = 'gdpr_press_consent_log';
    private const COOKIE  = 'gdpr_press_consent';
    private const VERSION = 'gdpr_press_policy_version';

    /* ── Singleton hook ──────────────────────────────────────────── */

    public static function init(): void {
        add_action( 'wp_ajax_gdpr_press_save_consent', [ __CLASS__, 'ajax_save' ] );
        add_action( 'wp_ajax_nopriv_gdpr_press_save_consent', [ __CLASS__, 'ajax_save' ] );
    }

    /* ── Attivazione: crea tabella log consensi ──────────────────── */

    public static function activate(): void {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            consent_id VARCHAR(64) NOT NULL,
            ip_hash VARCHAR(64) NOT NULL,
            user_agent VARCHAR(500) DEFAULT '',
            choices TEXT NOT NULL,
            policy_version VARCHAR(32) NOT NULL,
            action VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_consent_id (consent_id),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Versione policy iniziale
        if ( ! get_option( self::VERSION ) ) {
            update_option( self::VERSION, gmdate( 'Y-m-d' ) );
        }

        update_option( 'gdpr_press_db_version', GDPR_PRESS_VERSION );
    }

    public static function deactivate(): void {
        // Non eliminiamo la tabella: i dati di consenso servono come prova legale
    }

    /* ── AJAX: salva consenso ────────────────────────────────────── */

    public static function ajax_save(): void {
        // Nonce verificato lato JS
        check_ajax_referer( 'gdpr_press_nonce', 'nonce' );

        $action_type = sanitize_text_field( wp_unslash( $_POST['consent_action'] ?? '' ) );
        $choices_raw = wp_unslash( $_POST['choices'] ?? '{}' );
        $choices     = json_decode( $choices_raw, true );

        if ( ! is_array( $choices ) || ! in_array( $action_type, [ 'accept_all', 'reject_all', 'custom', 'revoke' ], true ) ) {
            wp_send_json_error( 'invalid_data', 400 );
        }

        // Sanitizza le scelte: solo booleani per chiavi conosciute
        $clean = [];
        foreach ( [ 'necessary', 'analytics', 'marketing', 'preferences' ] as $cat ) {
            $clean[ $cat ] = ! empty( $choices[ $cat ] );
        }
        $clean['necessary'] = true; // sempre attivi

        $consent_id = self::get_or_create_consent_id();
        $options    = gdpr_press_options();

        // Log nel DB
        if ( $options['consent_logging'] ) {
            self::log_consent( $consent_id, $clean, $action_type );
        }

        // Imposta cookie di consenso
        $expiry = time() + ( absint( $options['consent_expiry'] ) * DAY_IN_SECONDS );
        $cookie_value = wp_json_encode( [
            'choices'        => $clean,
            'timestamp'      => time(),
            'policy_version' => get_option( self::VERSION, '' ),
        ] );

        // Il cookie viene impostato lato client dal JS per compatibilità con page cache
        wp_send_json_success( [
            'consent_id'   => $consent_id,
            'cookie_name'  => self::COOKIE,
            'cookie_value' => $cookie_value,
            'expiry'       => $expiry,
            'choices'      => $clean,
        ] );
    }

    /* ── Log consenso nel DB ─────────────────────────────────────── */

    private static function log_consent( string $consent_id, array $choices, string $action_type ): void {
        global $wpdb;

        $ip_raw = self::get_client_ip();
        $ip_hash = hash( 'sha256', $ip_raw . wp_salt( 'auth' ) ); // hash + salt per privacy

        $wpdb->insert(
            $wpdb->prefix . self::TABLE,
            [
                'consent_id'     => $consent_id,
                'ip_hash'        => $ip_hash,
                'user_agent'     => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 500 ),
                'choices'        => wp_json_encode( $choices ),
                'policy_version' => get_option( self::VERSION, '' ),
                'action'         => $action_type,
                'created_at'     => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /* ── Helper: consent ID ──────────────────────────────────────── */

    private static function get_or_create_consent_id(): string {
        if ( ! empty( $_COOKIE['gdpr_press_cid'] ) ) {
            return sanitize_text_field( $_COOKIE['gdpr_press_cid'] );
        }
        return wp_generate_uuid4();
    }

    /* ── Helper: IP client ───────────────────────────────────────── */

    private static function get_client_ip(): string {
        $headers = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];
        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                // Prendi solo il primo IP se è una lista
                if ( str_contains( $ip, ',' ) ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /* ── API pubblica: leggi il consenso corrente ────────────────── */

    public static function get_current_consent(): ?array {
        if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
            return null;
        }
        $data = json_decode( wp_unslash( $_COOKIE[ self::COOKIE ] ), true );
        if ( ! is_array( $data ) || empty( $data['choices'] ) ) {
            return null;
        }

        // Verifica scadenza e versione policy
        $options = gdpr_press_options();
        $max_age = absint( $options['consent_expiry'] ) * DAY_IN_SECONDS;
        $current_version = get_option( self::VERSION, '' );

        if ( ( time() - ( $data['timestamp'] ?? 0 ) ) > $max_age ) {
            return null; // scaduto
        }
        if ( ( $data['policy_version'] ?? '' ) !== $current_version ) {
            return null; // policy aggiornata, richiedere nuovo consenso
        }

        return $data['choices'];
    }

    public static function has_consent( string $category ): bool {
        $consent = self::get_current_consent();
        return $consent !== null && ! empty( $consent[ $category ] );
    }

    /* ── Policy version: aggiornamento ───────────────────────────── */

    public static function bump_policy_version(): void {
        update_option( self::VERSION, gmdate( 'Y-m-d\TH:i' ) );
    }

    /* ── Admin: statistiche consensi ─────────────────────────────── */

    public static function get_stats( int $days = 30 ): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT action, COUNT(*) as total FROM {$table} WHERE created_at >= %s GROUP BY action",
                $since
            ),
            ARRAY_A
        );

        $stats = [ 'accept_all' => 0, 'reject_all' => 0, 'custom' => 0, 'revoke' => 0, 'total' => 0 ];
        foreach ( $results as $row ) {
            $stats[ $row['action'] ] = (int) $row['total'];
            $stats['total']         += (int) $row['total'];
        }

        return $stats;
    }

    /* ── Export CSV dei registri consenso ─────────────────────────── */

    public static function export_csv(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'gdpr_press_export_csv' );

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

        $filename = 'gdpr-press-consent-log-' . gmdate( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // BOM per Excel
        fwrite( $output, "\xEF\xBB\xBF" );

        // Header
        fputcsv( $output, [ 'ID', 'Consent ID', 'IP Hash', 'User Agent', 'Scelte', 'Versione Policy', 'Azione', 'Data/Ora (UTC)' ] );

        foreach ( $rows as $row ) {
            fputcsv( $output, [
                $row['id'],
                $row['consent_id'],
                $row['ip_hash'],
                $row['user_agent'],
                $row['choices'],
                $row['policy_version'],
                $row['action'],
                $row['created_at'],
            ] );
        }

        fclose( $output );
        exit;
    }
}
