<?php
/**
 * Self-hosting Google Fonts.
 *
 * Scarica i font da Google, li salva localmente in wp-content/uploads/scudo-fonts/
 * e riscrive i link CSS nell'output buffer per puntare ai file locali.
 */

defined( 'ABSPATH' ) || exit;

class Scudo_Fonts {

    private const UPLOAD_DIR  = 'scudo-fonts';
    private const OPTION_MAP  = 'scudo_fonts_map'; // mappa URL remoto → locale
    private const GOOGLE_CSS  = 'fonts.googleapis.com';
    private const GOOGLE_FILE = 'fonts.gstatic.com';

    /* ── Init ────────────────────────────────────────────────────── */

    public static function init(): void {
        add_action( 'wp_ajax_scudo_download_fonts', [ __CLASS__, 'ajax_download' ] );
    }

    /**
     * Riscrive i link Google Fonts nell'HTML con versioni locali.
     * Chiamato dall'output buffer del Blocker (se i font sono stati scaricati).
     */
    public static function rewrite_html( string $html ): string {
        $map = get_option( self::OPTION_MAP, [] );
        if ( empty( $map ) ) {
            return $html;
        }

        // Sostituisci i link CSS di Google Fonts con quelli locali
        foreach ( $map as $remote_url => $local_url ) {
            $html = str_replace( $remote_url, $local_url, $html );
        }

        // Rimuovi preconnect a fonts.googleapis.com e fonts.gstatic.com
        $html = preg_replace(
            '/<link[^>]*rel=["\'](?:preconnect|dns-prefetch)["\'][^>]*(?:fonts\.googleapis\.com|fonts\.gstatic\.com)[^>]*\/?>/i',
            '<!-- scudo: Google Fonts served locally -->',
            $html
        ) ?? $html;

        return $html;
    }

    /* ── AJAX: scarica i font ────────────────────────────────────── */

    public static function ajax_download(): void {
        check_ajax_referer( 'scudo_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        // Step 1: Trova tutti i link Google Fonts nel sito
        $css_urls = self::find_google_font_urls();

        if ( empty( $css_urls ) ) {
            wp_send_json_success( [
                'message' => __( 'Nessun Google Font rilevato nel sito.', 'scudo-cookie-privacy' ),
                'count'   => 0,
            ] );
        }

        // Step 2: Crea la directory di upload
        $upload_dir = self::get_upload_dir();
        if ( ! $upload_dir ) {
            wp_send_json_error( __( 'Impossibile creare la directory dei font.', 'scudo-cookie-privacy' ) );
        }

        $map = [];
        $font_count = 0;

        foreach ( $css_urls as $css_url ) {
            $result = self::download_css_and_fonts( $css_url, $upload_dir );
            if ( $result ) {
                $map[ $css_url ] = $result['local_css_url'];
                $font_count += $result['font_count'];
            }
        }

        // Salva la mappa
        update_option( self::OPTION_MAP, $map );

        wp_send_json_success( [
            'message'    => sprintf(
                // translators: %1$d is the number of fonts downloaded, %2$d is the number of CSS stylesheets processed.
                __( 'Scaricati %1$d font da %2$d fogli di stile Google Fonts. I font vengono ora serviti dal tuo server.', 'scudo-cookie-privacy' ),
                $font_count,
                count( $map )
            ),
            'count'      => $font_count,
            'css_count'  => count( $map ),
        ] );
    }

    /* ── Trova gli URL dei CSS Google Fonts nel sito ─────────────── */

    private static function find_google_font_urls(): array {
        // Visita la homepage per trovare i link
        $response = wp_remote_get( home_url( '/' ), [
            'timeout'    => 15,
            'sslverify'  => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 GDPR-Press-Fonts/1.0',
        ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $body = wp_remote_retrieve_body( $response );
        $urls = [];

        // Trova link CSS di Google Fonts
        if ( preg_match_all( '/(?:href|url)\s*[=\(]\s*["\']?(https?:\/\/fonts\.googleapis\.com\/css2?[^"\')\s>]+)/i', $body, $matches ) ) {
            $urls = array_unique( $matches[1] );
        }

        return $urls;
    }

    /* ── Scarica CSS e font files ────────────────────────────────── */

    private static function download_css_and_fonts( string $css_url, array $upload_dir ): ?array {
        // Scarica il CSS con user-agent che restituisce woff2
        $response = wp_remote_get( $css_url, [
            'timeout'    => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $css = wp_remote_retrieve_body( $response );
        if ( empty( $css ) ) {
            return null;
        }

        $font_count = 0;

        // Trova tutti gli URL dei font nel CSS e scaricali
        $css = preg_replace_callback(
            '/url\s*\(\s*["\']?(https?:\/\/fonts\.gstatic\.com\/[^"\')\s]+)["\']?\s*\)/i',
            function ( $match ) use ( $upload_dir, &$font_count ) {
                $font_url = $match[1];

                // Nome file dal URL
                $parsed   = wp_parse_url( $font_url );
                $filename = basename( $parsed['path'] ?? 'font.woff2' );

                // Aggiungi hash per evitare collisioni
                $hash = substr( md5( $font_url ), 0, 8 );
                $filename = $hash . '-' . $filename;

                $local_path = $upload_dir['path'] . '/' . $filename;
                $local_url  = $upload_dir['url'] . '/' . $filename;

                // Scarica solo se non esiste già
                if ( ! file_exists( $local_path ) ) {
                    $font_response = wp_remote_get( $font_url, [
                        'timeout' => 30,
                        'stream'  => true,
                        'filename' => $local_path,
                    ] );

                    if ( is_wp_error( $font_response ) ) {
                        return $match[0]; // fallback: mantieni l'URL originale
                    }
                }

                $font_count++;
                return 'url(' . $local_url . ')';
            },
            $css
        );

        // Salva il CSS riscritto
        $css_hash    = substr( md5( $css_url ), 0, 12 );
        $css_filename = 'gfonts-' . $css_hash . '.css';
        $css_path    = $upload_dir['path'] . '/' . $css_filename;
        $css_local_url = $upload_dir['url'] . '/' . $css_filename;

        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $wp_filesystem->put_contents( $css_path, $css, FS_CHMOD_FILE );

        return [
            'local_css_url' => $css_local_url,
            'font_count'    => $font_count,
        ];
    }

    /* ── Directory di upload ─────────────────────────────────────── */

    private static function get_upload_dir(): ?array {
        $wp_upload = wp_upload_dir();
        $dir = $wp_upload['basedir'] . '/' . self::UPLOAD_DIR;
        $url = $wp_upload['baseurl'] . '/' . self::UPLOAD_DIR;

        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem->is_writable( $dir ) ) {
            return null;
        }

        return [ 'path' => $dir, 'url' => $url ];
    }

    /* ── Pulizia: rimuovi i font scaricati ───────────────────────── */

    public static function clean(): void {
        $wp_upload = wp_upload_dir();
        $dir = $wp_upload['basedir'] . '/' . self::UPLOAD_DIR;

        if ( is_dir( $dir ) ) {
            $files = glob( $dir . '/*' );
            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( is_file( $file ) ) {
                        wp_delete_file( $file );
                    }
                }
            }
            global $wp_filesystem;
            if ( empty( $wp_filesystem ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $wp_filesystem->rmdir( $dir );
        }

        delete_option( self::OPTION_MAP );
    }

    /* ── Stato: sono stati scaricati i font? ─────────────────────── */

    public static function is_active(): bool {
        $map = get_option( self::OPTION_MAP, [] );
        return ! empty( $map );
    }
}
