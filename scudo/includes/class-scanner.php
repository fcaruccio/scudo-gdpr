<?php
/**
 * Scanner e database dei cookie.
 *
 * - Database integrato di cookie noti (GA, Facebook, WordPress, ecc.)
 * - Scansione AJAX che visita il sito e rileva i cookie attivi
 * - Shortcode [scudo_cookie_table] per la pagina cookie policy
 */

defined( 'ABSPATH' ) || exit;

class Scudo_Scanner {

    /**
     * Database integrato di cookie noti.
     * Chiave = pattern regex per il nome del cookie.
     * Valore = [ nome_visibile, categoria, durata, descrizione, fornitore ]
     */
    private const KNOWN_COOKIES = [
        // ── WordPress core ──
        'wordpress_logged_in_' => [
            'name'     => 'wordpress_logged_in_*',
            'category' => 'necessary',
            'duration' => 'Sessione / 14 giorni',
            'desc'     => 'Mantiene la sessione di login dell\'utente autenticato.',
            'provider' => 'Questo sito',
        ],
        'wordpress_sec_' => [
            'name'     => 'wordpress_sec_*',
            'category' => 'necessary',
            'duration' => 'Sessione / 14 giorni',
            'desc'     => 'Cookie di sicurezza per l\'autenticazione nell\'area amministrativa.',
            'provider' => 'Questo sito',
        ],
        'wordpress_test_cookie' => [
            'name'     => 'wordpress_test_cookie',
            'category' => 'necessary',
            'duration' => 'Sessione',
            'desc'     => 'Verifica che il browser accetti i cookie.',
            'provider' => 'Questo sito',
        ],
        'wp-settings-' => [
            'name'     => 'wp-settings-*',
            'category' => 'necessary',
            'duration' => '1 anno',
            'desc'     => 'Memorizza le preferenze dell\'interfaccia di amministrazione.',
            'provider' => 'Questo sito',
        ],
        'wp-settings-time-' => [
            'name'     => 'wp-settings-time-*',
            'category' => 'necessary',
            'duration' => '1 anno',
            'desc'     => 'Timestamp associato alle preferenze di amministrazione.',
            'provider' => 'Questo sito',
        ],
        'comment_author_' => [
            'name'     => 'comment_author_*',
            'category' => 'necessary',
            'duration' => '1 anno',
            'desc'     => 'Memorizza il nome dell\'utente per i commenti.',
            'provider' => 'Questo sito',
        ],

        // ── WooCommerce ──
        'woocommerce_cart_hash' => [
            'name'     => 'woocommerce_cart_hash',
            'category' => 'necessary',
            'duration' => 'Sessione',
            'desc'     => 'Mantiene il contenuto del carrello.',
            'provider' => 'Questo sito (WooCommerce)',
        ],
        'woocommerce_items_in_cart' => [
            'name'     => 'woocommerce_items_in_cart',
            'category' => 'necessary',
            'duration' => 'Sessione',
            'desc'     => 'Indica se ci sono articoli nel carrello.',
            'provider' => 'Questo sito (WooCommerce)',
        ],
        'wp_woocommerce_session_' => [
            'name'     => 'wp_woocommerce_session_*',
            'category' => 'necessary',
            'duration' => '2 giorni',
            'desc'     => 'Sessione WooCommerce per gestire carrello e checkout.',
            'provider' => 'Questo sito (WooCommerce)',
        ],

        // ── Scudo (noi stessi) ──
        'scudo_consent' => [
            'name'     => 'scudo_consent',
            'category' => 'necessary',
            'duration' => '6 mesi',
            'desc'     => 'Memorizza le preferenze cookie espresse dall\'utente.',
            'provider' => 'Questo sito (Scudo)',
        ],
        'scudo_cid' => [
            'name'     => 'scudo_cid',
            'category' => 'necessary',
            'duration' => '6 mesi',
            'desc'     => 'Identificativo anonimo per la registrazione del consenso.',
            'provider' => 'Questo sito (Scudo)',
        ],

        // ── Google Analytics ──
        '_ga' => [
            'name'     => '_ga',
            'category' => 'analytics',
            'duration' => '2 anni',
            'desc'     => 'Registra un ID univoco per generare dati statistici su come il visitatore utilizza il sito.',
            'provider' => 'Google LLC',
        ],
        '_ga_' => [
            'name'     => '_ga_*',
            'category' => 'analytics',
            'duration' => '2 anni',
            'desc'     => 'Utilizzato da Google Analytics per raccogliere dati sul numero di visite, data della prima e dell\'ultima visita.',
            'provider' => 'Google LLC',
        ],
        '_gid' => [
            'name'     => '_gid',
            'category' => 'analytics',
            'duration' => '24 ore',
            'desc'     => 'Registra un ID univoco per generare dati statistici su come il visitatore utilizza il sito.',
            'provider' => 'Google LLC',
        ],
        '_gat' => [
            'name'     => '_gat',
            'category' => 'analytics',
            'duration' => '1 minuto',
            'desc'     => 'Utilizzato per limitare la frequenza delle richieste a Google Analytics.',
            'provider' => 'Google LLC',
        ],
        '_gac_' => [
            'name'     => '_gac_*',
            'category' => 'marketing',
            'duration' => '90 giorni',
            'desc'     => 'Contiene informazioni sulla campagna Google Ads dell\'utente.',
            'provider' => 'Google LLC',
        ],

        // ── Google Tag Manager ──
        '_gcl_au' => [
            'name'     => '_gcl_au',
            'category' => 'marketing',
            'duration' => '90 giorni',
            'desc'     => 'Utilizzato da Google AdSense per la sperimentazione sull\'efficacia pubblicitaria.',
            'provider' => 'Google LLC',
        ],

        // ── Google Ads ──
        '_gcl_aw' => [
            'name'     => '_gcl_aw',
            'category' => 'marketing',
            'duration' => '90 giorni',
            'desc'     => 'Memorizza le informazioni sui click sugli annunci Google Ads per la misurazione delle conversioni.',
            'provider' => 'Google LLC',
        ],
        'test_cookie' => [
            'name'     => 'test_cookie',
            'category' => 'marketing',
            'duration' => '15 minuti',
            'desc'     => 'Utilizzato da DoubleClick (Google) per verificare se il browser accetta i cookie.',
            'provider' => 'Google LLC (DoubleClick)',
        ],
        'IDE' => [
            'name'     => 'IDE',
            'category' => 'marketing',
            'duration' => '1 anno',
            'desc'     => 'Utilizzato da DoubleClick (Google) per registrare e segnalare le azioni dell\'utente dopo aver visto o cliccato un annuncio.',
            'provider' => 'Google LLC (DoubleClick)',
        ],

        // ── Facebook ──
        '_fbp' => [
            'name'     => '_fbp',
            'category' => 'marketing',
            'duration' => '3 mesi',
            'desc'     => 'Utilizzato da Facebook per fornire annunci pubblicitari personalizzati.',
            'provider' => 'Meta Platforms Inc.',
        ],
        '_fbc' => [
            'name'     => '_fbc',
            'category' => 'marketing',
            'duration' => '2 anni',
            'desc'     => 'Memorizza l\'ultimo click sugli annunci Facebook per la misurazione delle conversioni.',
            'provider' => 'Meta Platforms Inc.',
        ],
        'fr' => [
            'name'     => 'fr',
            'category' => 'marketing',
            'duration' => '3 mesi',
            'desc'     => 'Cookie di profilazione di Facebook per mostrare annunci pertinenti.',
            'provider' => 'Meta Platforms Inc.',
        ],

        // ── Hotjar ──
        '_hj' => [
            'name'     => '_hj*',
            'category' => 'analytics',
            'duration' => '1 anno',
            'desc'     => 'Utilizzato da Hotjar per analizzare il comportamento degli utenti (heatmap, registrazioni).',
            'provider' => 'Hotjar Ltd.',
        ],

        // ── Microsoft Clarity ──
        '_clck' => [
            'name'     => '_clck',
            'category' => 'analytics',
            'duration' => '1 anno',
            'desc'     => 'Utilizzato da Microsoft Clarity per la raccolta di dati statistici sul comportamento degli utenti.',
            'provider' => 'Microsoft Corporation',
        ],
        '_clsk' => [
            'name'     => '_clsk',
            'category' => 'analytics',
            'duration' => '1 giorno',
            'desc'     => 'Collega le visualizzazioni di pagina consecutive nella sessione di Clarity.',
            'provider' => 'Microsoft Corporation',
        ],
        'CLID' => [
            'name'     => 'CLID',
            'category' => 'analytics',
            'duration' => '1 anno',
            'desc'     => 'Identifica la prima volta che Clarity ha visto l\'utente su qualsiasi sito che utilizza Clarity.',
            'provider' => 'Microsoft Corporation',
        ],

        // ── LinkedIn ──
        'li_sugr' => [
            'name'     => 'li_sugr',
            'category' => 'marketing',
            'duration' => '3 mesi',
            'desc'     => 'Utilizzato da LinkedIn per il tracciamento delle conversioni.',
            'provider' => 'LinkedIn Corporation',
        ],
        'bcookie' => [
            'name'     => 'bcookie',
            'category' => 'marketing',
            'duration' => '1 anno',
            'desc'     => 'Cookie identificativo del browser impostato da LinkedIn.',
            'provider' => 'LinkedIn Corporation',
        ],
        'ln_or' => [
            'name'     => 'ln_or',
            'category' => 'marketing',
            'duration' => '1 giorno',
            'desc'     => 'Utilizzato da LinkedIn Insight Tag per il tracciamento delle conversioni.',
            'provider' => 'LinkedIn Corporation',
        ],

        // ── Google Fonts / Preferences ──
        'NID' => [
            'name'     => 'NID',
            'category' => 'preferences',
            'duration' => '6 mesi',
            'desc'     => 'Cookie di preferenza di Google che memorizza impostazioni come la lingua preferita.',
            'provider' => 'Google LLC',
        ],

        // ── YouTube ──
        'YSC' => [
            'name'     => 'YSC',
            'category' => 'marketing',
            'duration' => 'Sessione',
            'desc'     => 'Registra un ID univoco per statistiche sui video YouTube visualizzati dall\'utente.',
            'provider' => 'Google LLC (YouTube)',
        ],
        'VISITOR_INFO1_LIVE' => [
            'name'     => 'VISITOR_INFO1_LIVE',
            'category' => 'marketing',
            'duration' => '6 mesi',
            'desc'     => 'Cerca di stimare la larghezza di banda dell\'utente sulle pagine con video YouTube integrati.',
            'provider' => 'Google LLC (YouTube)',
        ],
        'CONSENT' => [
            'name'     => 'CONSENT',
            'category' => 'marketing',
            'duration' => '2 anni',
            'desc'     => 'Memorizza lo stato del consenso dell\'utente per i servizi Google sul dominio corrente.',
            'provider' => 'Google LLC',
        ],
    ];

    /* ── Init ────────────────────────────────────────────────────── */

    public static function init(): void {
        // Shortcode per la tabella cookie nella cookie policy
        add_shortcode( 'scudo_cookie_table', [ __CLASS__, 'shortcode_cookie_table' ] );

        // AJAX per la scansione
        add_action( 'wp_ajax_scudo_scan_cookies', [ __CLASS__, 'ajax_scan' ] );
    }

    /* ── Shortcode: tabella cookie ───────────────────────────────── */

    public static function shortcode_cookie_table( $atts ): string {
        $atts = shortcode_atts( [
            'show_empty' => 'no',
        ], $atts, 'scudo_cookie_table' );

        $cookies = self::get_cookie_list();

        // Raggruppa per categoria
        $categories = [
            'necessary'   => [
                'label' => __( 'Cookie necessari', 'scudo-cookie-privacy' ),
                'desc'  => __( 'Questi cookie sono indispensabili per il funzionamento del sito e non possono essere disattivati. Vengono impostati in risposta ad azioni da te effettuate, come la gestione delle preferenze sulla privacy, il login o la compilazione di moduli.', 'scudo-cookie-privacy' ),
                'items' => [],
            ],
            'analytics'   => [
                'label' => __( 'Cookie analitici', 'scudo-cookie-privacy' ),
                'desc'  => __( 'Questi cookie ci permettono di contare le visite e le fonti di traffico per misurare e migliorare le prestazioni del nostro sito. Ci aiutano a capire quali pagine sono più o meno popolari e a vedere come i visitatori si muovono nel sito. Tutte le informazioni raccolte da questi cookie sono aggregate e quindi anonime.', 'scudo-cookie-privacy' ),
                'items' => [],
            ],
            'marketing'   => [
                'label' => __( 'Cookie di marketing', 'scudo-cookie-privacy' ),
                'desc'  => __( 'Questi cookie possono essere impostati attraverso il nostro sito da parte dei nostri partner pubblicitari. Possono essere utilizzati da queste aziende per costruire un profilo dei tuoi interessi e mostrarti annunci pertinenti su altri siti. Non memorizzano direttamente informazioni personali, ma si basano sull\'identificazione univoca del tuo browser e dispositivo.', 'scudo-cookie-privacy' ),
                'items' => [],
            ],
            'preferences' => [
                'label' => __( 'Cookie di preferenza', 'scudo-cookie-privacy' ),
                'desc'  => __( 'Questi cookie permettono al sito di ricordare le scelte che hai fatto (come la lingua o la regione) e di fornire funzionalità avanzate e personalizzate.', 'scudo-cookie-privacy' ),
                'items' => [],
            ],
        ];

        foreach ( $cookies as $cookie ) {
            $cat = $cookie['category'];
            if ( isset( $categories[ $cat ] ) ) {
                $categories[ $cat ]['items'][] = $cookie;
            }
        }

        // Genera HTML
        $html = '<div class="scudo-cookie-policy">';

        foreach ( $categories as $cat_slug => $cat_data ) {
            if ( empty( $cat_data['items'] ) && $atts['show_empty'] !== 'yes' ) {
                continue;
            }

            $html .= '<div class="scudo-cookie-policy__section">';
            $html .= '<h3 class="scudo-cookie-policy__heading">' . esc_html( $cat_data['label'] ) . '</h3>';
            $html .= '<p class="scudo-cookie-policy__desc">' . esc_html( $cat_data['desc'] ) . '</p>';

            if ( ! empty( $cat_data['items'] ) ) {
                $html .= '<div class="scudo-cookie-policy__table-wrap">';
                $html .= '<table class="scudo-cookie-policy__table">';
                $html .= '<thead><tr>'
                       . '<th>' . esc_html__( 'Cookie', 'scudo-cookie-privacy' ) . '</th>'
                       . '<th>' . esc_html__( 'Fornitore', 'scudo-cookie-privacy' ) . '</th>'
                       . '<th>' . esc_html__( 'Durata', 'scudo-cookie-privacy' ) . '</th>'
                       . '<th>' . esc_html__( 'Descrizione', 'scudo-cookie-privacy' ) . '</th>'
                       . '</tr></thead><tbody>';

                foreach ( $cat_data['items'] as $cookie ) {
                    $html .= '<tr>'
                           . '<td><code>' . esc_html( $cookie['name'] ) . '</code></td>'
                           . '<td>' . esc_html( $cookie['provider'] ) . '</td>'
                           . '<td>' . esc_html( $cookie['duration'] ) . '</td>'
                           . '<td>' . esc_html( $cookie['desc'] ) . '</td>'
                           . '</tr>';
                }

                $html .= '</tbody></table></div>';
            } else {
                $html .= '<p><em>' . esc_html__( 'Nessun cookie in questa categoria.', 'scudo-cookie-privacy' ) . '</em></p>';
            }

            $html .= '</div>';
        }

        // Data ultimo aggiornamento
        $html .= '<p class="scudo-cookie-policy__updated"><small>'
               . esc_html__( 'Ultimo aggiornamento:', 'scudo-cookie-privacy' ) . ' '
               . esc_html( get_option( 'scudo_policy_version', gmdate( 'Y-m-d' ) ) )
               . '</small></p>';

        $html .= '</div>';

        // Enqueue inline styles for the cookie table.
        $cookie_table_css = '.scudo-cookie-policy__section{margin-bottom:32px}'
            . '.scudo-cookie-policy__heading{font-size:1.2em;margin-bottom:8px}'
            . '.scudo-cookie-policy__desc{color:#555;margin-bottom:16px;font-size:.95em;line-height:1.6}'
            . '.scudo-cookie-policy__table-wrap{overflow-x:auto}'
            . '.scudo-cookie-policy__table{width:100%;border-collapse:collapse;font-size:.9em}'
            . '.scudo-cookie-policy__table th,.scudo-cookie-policy__table td{padding:10px 12px;border:1px solid #e5e7eb;text-align:left;vertical-align:top}'
            . '.scudo-cookie-policy__table th{background:#f9fafb;font-weight:600;white-space:nowrap}'
            . '.scudo-cookie-policy__table code{font-size:.85em;background:#f3f4f6;padding:2px 6px;border-radius:3px}'
            . '.scudo-cookie-policy__updated{margin-top:24px;color:#888}';
        wp_register_style( 'scudo-cookie-table', false, array(), SCUDO_VERSION );
        wp_enqueue_style( 'scudo-cookie-table' );
        wp_add_inline_style( 'scudo-cookie-table', $cookie_table_css );

        return $html;
    }

    /* ── Lista cookie: database noto + cookie personalizzati ─────── */

    public static function get_cookie_list(): array {
        $cookies = [];

        // Cookie dal database integrato: includi quelli rilevanti per il sito
        $detected = get_option( 'scudo_detected_cookies', [] );

        // Sempre includi i cookie di Scudo e WordPress base
        $always_include = [
            'scudo_consent', 'scudo_cid',
            'wordpress_test_cookie',
        ];

        foreach ( self::KNOWN_COOKIES as $pattern => $info ) {
            // Includi se è nella lista "sempre" o se rilevato dalla scansione
            $include = in_array( $pattern, $always_include, true );

            if ( ! $include ) {
                foreach ( $detected as $detected_name ) {
                    if ( str_starts_with( $detected_name, rtrim( $pattern, '_' ) ) || $detected_name === $pattern ) {
                        $include = true;
                        break;
                    }
                }
            }

            if ( $include ) {
                $cookies[] = $info;
            }
        }

        // Cookie personalizzati dall'admin
        $custom = get_option( 'scudo_custom_cookies', [] );
        foreach ( $custom as $cc ) {
            if ( ! empty( $cc['name'] ) ) {
                $cookies[] = [
                    'name'     => sanitize_text_field( $cc['name'] ),
                    'category' => sanitize_text_field( $cc['category'] ?? 'necessary' ),
                    'duration' => sanitize_text_field( $cc['duration'] ?? '' ),
                    'desc'     => sanitize_text_field( $cc['desc'] ?? '' ),
                    'provider' => sanitize_text_field( $cc['provider'] ?? '' ),
                ];
            }
        }

        return $cookies;
    }

    /* ── Scansione AJAX (lanciata dall'admin) ────────────────────── */

    public static function ajax_scan(): void {
        check_ajax_referer( 'scudo_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        // Visita la homepage e raccoglie i cookie impostati
        $url = home_url( '/' );
        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'cookies'    => [],
            'sslverify'  => false,
            'user-agent' => 'GDPR-Press-Scanner/1.0',
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        // Estrai cookie dalla risposta
        $response_cookies = wp_remote_retrieve_cookies( $response );
        $detected = [];
        foreach ( $response_cookies as $cookie ) {
            $detected[] = $cookie->name;
        }

        // Analizza l'HTML per rilevare script di terze parti
        $body = wp_remote_retrieve_body( $response );
        $detected = array_merge( $detected, self::detect_cookies_from_html( $body ) );
        $detected = array_unique( $detected );

        // Salva i risultati
        update_option( 'scudo_detected_cookies', $detected );

        // Classifica i cookie rilevati
        $classified = [];
        foreach ( $detected as $name ) {
            $info = self::classify_cookie( $name );
            $classified[] = [
                'name'   => $name,
                'known'  => $info !== null,
                'info'   => $info,
            ];
        }

        wp_send_json_success( [
            'detected'   => $detected,
            'classified' => $classified,
            'count'      => count( $detected ),
        ] );
    }

    /**
     * Scansione silenziosa (senza output JSON) — usata dal wizard.
     */
    public static function ajax_scan_silent(): void {
        $url = home_url( '/' );
        $response = wp_remote_get( $url, [
            'timeout' => 10, 'cookies' => [], 'sslverify' => false,
            'user-agent' => 'Scudo-Scanner/1.0',
        ] );
        if ( is_wp_error( $response ) ) return;

        $response_cookies = wp_remote_retrieve_cookies( $response );
        $detected = [];
        foreach ( $response_cookies as $cookie ) {
            $detected[] = $cookie->name;
        }
        $body = wp_remote_retrieve_body( $response );
        $detected = array_unique( array_merge( $detected, self::detect_cookies_from_html( $body ) ) );
        update_option( 'scudo_detected_cookies', $detected );
    }

    /**
     * Analizza l'HTML per inferire quali cookie verranno impostati
     * (basandosi sugli script presenti nella pagina).
     */
    private static function detect_cookies_from_html( string $html ): array {
        $detected = [];

        // Google Analytics
        if ( preg_match( '/google-analytics\.com|gtag\/js|googletagmanager\.com/', $html ) ) {
            $detected = array_merge( $detected, [ '_ga', '_ga_', '_gid', '_gat' ] );
        }

        // Google Ads
        if ( preg_match( '/googleadservices\.com|googlesyndication\.com|doubleclick\.net/', $html ) ) {
            $detected = array_merge( $detected, [ '_gcl_aw', '_gcl_au', 'test_cookie', 'IDE' ] );
        }

        // Facebook Pixel
        if ( preg_match( '/facebook\.net|fbevents\.js|connect\.facebook/', $html ) ) {
            $detected = array_merge( $detected, [ '_fbp', '_fbc', 'fr' ] );
        }

        // Hotjar
        if ( preg_match( '/hotjar\.com/', $html ) ) {
            $detected[] = '_hj';
        }

        // Microsoft Clarity
        if ( preg_match( '/clarity\.ms/', $html ) ) {
            $detected = array_merge( $detected, [ '_clck', '_clsk', 'CLID' ] );
        }

        // LinkedIn
        if ( preg_match( '/linkedin\.com\/insight|snap\.licdn\.com/', $html ) ) {
            $detected = array_merge( $detected, [ 'li_sugr', 'bcookie', 'ln_or' ] );
        }

        // YouTube
        if ( preg_match( '/youtube\.com|youtube-nocookie\.com/', $html ) ) {
            $detected = array_merge( $detected, [ 'YSC', 'VISITOR_INFO1_LIVE', 'CONSENT' ] );
        }

        // WooCommerce
        if ( preg_match( '/woocommerce|wc-cart/', $html ) || class_exists( 'WooCommerce' ) ) {
            $detected = array_merge( $detected, [ 'woocommerce_cart_hash', 'woocommerce_items_in_cart', 'wp_woocommerce_session_' ] );
        }

        return $detected;
    }

    /**
     * Classifica un cookie confrontandolo con il database noto.
     */
    private static function classify_cookie( string $name ): ?array {
        // Match esatto
        if ( isset( self::KNOWN_COOKIES[ $name ] ) ) {
            return self::KNOWN_COOKIES[ $name ];
        }

        // Match per prefisso
        foreach ( self::KNOWN_COOKIES as $pattern => $info ) {
            if ( str_ends_with( $pattern, '_' ) && str_starts_with( $name, $pattern ) ) {
                return $info;
            }
        }

        return null;
    }

    /* ── Elenco di tutti i cookie noti (per l'admin) ─────────────── */

    public static function get_known_cookies(): array {
        return self::KNOWN_COOKIES;
    }
}
