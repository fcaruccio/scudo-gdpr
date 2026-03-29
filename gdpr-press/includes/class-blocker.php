<?php
/**
 * Blocco preventivo di script e iframe prima del consenso.
 *
 * Usa output buffering per riscrivere l'HTML in uscita:
 * - Script di terze parti: type → text/plain, aggiunge data-gdpr-category
 * - Iframe (YouTube, Maps, ecc.): sostituiti con placeholder
 */

defined( 'ABSPATH' ) || exit;

class GDPR_Press_Blocker {

    /**
     * Pattern di dominio → categoria GDPR.
     * L'ordine conta: il primo match vince.
     */
    private const DOMAIN_MAP = [
        // Analytics
        'google-analytics.com'      => 'analytics',
        'googletagmanager.com'      => 'analytics',
        'analytics.google.com'      => 'analytics',
        'gtag/js'                   => 'analytics',
        'matomo'                    => 'analytics',
        'hotjar.com'                => 'analytics',
        'clarity.ms'                => 'analytics',
        'plausible.io'              => 'analytics',

        // Marketing / Profilazione
        'facebook.net'              => 'marketing',
        'fbevents.js'               => 'marketing',
        'connect.facebook'          => 'marketing',
        'doubleclick.net'           => 'marketing',
        'googlesyndication.com'     => 'marketing',
        'googleadservices.com'      => 'marketing',
        'google.com/pagead'         => 'marketing',
        'adsbygoogle'               => 'marketing',
        'linkedin.com/insight'      => 'marketing',
        'snap.licdn.com'            => 'marketing',
        'ads-twitter.com'           => 'marketing',
        'platform.twitter.com'      => 'marketing',
        'tiktok.com'                => 'marketing',
        'pinterest.com'             => 'marketing',
        'amazon-adsystem.com'       => 'marketing',
        'criteo.com'                => 'marketing',
        'outbrain.com'              => 'marketing',
        'taboola.com'               => 'marketing',

        // Social embeds (marketing perché installano cookie di tracciamento)
        'platform.instagram.com'    => 'marketing',

        // Preferenze
        'fonts.googleapis.com'      => 'preferences',
        'fonts.gstatic.com'         => 'preferences',
    ];

    /**
     * Iframe → categoria.
     */
    private const IFRAME_MAP = [
        'youtube.com'               => 'marketing',
        'youtube-nocookie.com'      => 'marketing',
        'youtu.be'                  => 'marketing',
        'vimeo.com'                 => 'marketing',
        'google.com/maps'           => 'marketing',
        'maps.google'               => 'marketing',
        'facebook.com/plugins'      => 'marketing',
        'open.spotify.com'          => 'preferences',
    ];

    /* ── Init ────────────────────────────────────────────────────── */

    public static function init(): void {
        // Non bloccare in admin, login, cron
        if ( is_admin() || wp_doing_cron() ) {
            return;
        }

        $consent = GDPR_Press_Consent::get_current_consent();
        $all_accepted = $consent !== null && ! empty( $consent['analytics'] ) && ! empty( $consent['marketing'] ) && ! empty( $consent['preferences'] );
        $fonts_active = class_exists( 'GDPR_Press_Fonts' ) && GDPR_Press_Fonts::is_active();

        // Se tutto è accettato E i font non sono in self-hosting, non intercettiamo (performance)
        if ( $all_accepted && ! $fonts_active ) {
            return;
        }

        add_action( 'template_redirect', [ __CLASS__, 'start_buffer' ], 1 );
    }

    public static function start_buffer(): void {
        ob_start( [ __CLASS__, 'process_buffer' ] );
    }

    /* ── Processa l'HTML in output ───────────────────────────────── */

    public static function process_buffer( string $html ): string {
        if ( empty( $html ) || stripos( $html, '<html' ) === false ) {
            return $html; // non è HTML, non toccare
        }

        $consent = GDPR_Press_Consent::get_current_consent();
        $options = gdpr_press_options();

        // Pattern personalizzati dall'admin
        $custom_patterns = array_filter( array_map( 'trim', explode( "\n", $options['custom_block_patterns'] ) ) );

        // Blocca script inline e con src
        $html = self::block_scripts( $html, $consent, $custom_patterns );

        // Blocca iframe
        $html = self::block_iframes( $html, $consent );

        // Blocca link preconnect/prefetch per risorse bloccate
        $html = self::block_link_hints( $html, $consent );

        // Self-hosting Google Fonts: riscrivi URL
        if ( class_exists( 'GDPR_Press_Fonts' ) && GDPR_Press_Fonts::is_active() ) {
            $html = GDPR_Press_Fonts::rewrite_html( $html );
        }

        return $html;
    }

    /* ── Blocco script ───────────────────────────────────────────── */

    private static function block_scripts( string $html, ?array $consent, array $custom_patterns ): string {
        // Regex per catturare tag <script ...>...</script> e <script ... />
        return preg_replace_callback(
            '/<script\b([^>]*)>(.*?)<\/script>/is',
            function ( $match ) use ( $consent, $custom_patterns ) {
                $attrs   = $match[1];
                $content = $match[2];
                $full    = $match[0];

                // Se ha già data-gdpr-category="necessary", non bloccare
                if ( preg_match( '/data-gdpr-category\s*=\s*["\']necessary["\']/', $attrs ) ) {
                    return $full;
                }

                // Se ha data-gdpr-category impostato manualmente, rispettalo
                if ( preg_match( '/data-gdpr-category\s*=\s*["\'](\w+)["\']/', $attrs, $cat_match ) ) {
                    $category = $cat_match[1];
                    if ( self::category_allowed( $category, $consent ) ) {
                        return $full;
                    }
                    return self::disable_script( $attrs, $content, $category );
                }

                // Determina categoria dal contenuto o src
                $category = self::detect_category( $attrs . ' ' . $content, $custom_patterns );

                if ( $category === null ) {
                    return $full; // script non riconosciuto → non bloccare (principio: non rompere il sito)
                }

                if ( self::category_allowed( $category, $consent ) ) {
                    return $full;
                }

                return self::disable_script( $attrs, $content, $category );
            },
            $html
        ) ?? $html;
    }

    private static function disable_script( string $attrs, string $content, string $category ): string {
        // Cambia type a text/plain per impedire l'esecuzione
        if ( preg_match( '/\btype\s*=/', $attrs ) ) {
            $attrs = preg_replace( '/\btype\s*=\s*["\'][^"\']*["\']/', 'type="text/plain"', $attrs );
        } else {
            $attrs .= ' type="text/plain"';
        }

        // Aggiungi data-gdpr-category se non presente
        if ( ! str_contains( $attrs, 'data-gdpr-category' ) ) {
            $attrs .= ' data-gdpr-category="' . esc_attr( $category ) . '"';
        }

        return '<script' . $attrs . '>' . $content . '</script>';
    }

    /* ── Blocco iframe ───────────────────────────────────────────── */

    private static function block_iframes( string $html, ?array $consent ): string {
        return preg_replace_callback(
            '/<iframe\b([^>]*)(?:\/>|>(.*?)<\/iframe>)/is',
            function ( $match ) use ( $consent ) {
                $attrs = $match[1];
                $full  = $match[0];

                // Se ha data-gdpr-category="necessary", non bloccare
                if ( preg_match( '/data-gdpr-category\s*=\s*["\']necessary["\']/', $attrs ) ) {
                    return $full;
                }

                // Determina categoria dall'src
                $category = self::detect_iframe_category( $attrs );

                if ( $category === null ) {
                    return $full; // iframe non riconosciuto
                }

                if ( self::category_allowed( $category, $consent ) ) {
                    return $full;
                }

                return self::create_iframe_placeholder( $attrs, $category );
            },
            $html
        ) ?? $html;
    }

    /**
     * Mappa dominio → informazioni descrittive per il placeholder.
     * Ogni voce: [ icona SVG, titolo, descrizione, nome servizio ]
     */
    private const SERVICE_INFO = [
        'youtube.com'          => [
            'icon'    => '<svg width="32" height="32" viewBox="0 0 24 24" fill="#FF0000" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31.9 31.9 0 0 0 0 12a31.9 31.9 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31.9 31.9 0 0 0 24 12a31.9 31.9 0 0 0-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z"/></svg>',
            'title'   => 'Video YouTube',
            'desc'    => 'Qui è presente un video ospitato su YouTube. Caricandolo, YouTube potrebbe raccogliere dati sulla tua navigazione tramite cookie di profilazione.',
            'service' => 'YouTube (Google LLC)',
        ],
        'youtube-nocookie.com' => [
            'icon'    => '<svg width="32" height="32" viewBox="0 0 24 24" fill="#FF0000" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31.9 31.9 0 0 0 0 12a31.9 31.9 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31.9 31.9 0 0 0 24 12a31.9 31.9 0 0 0-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z"/></svg>',
            'title'   => 'Video YouTube',
            'desc'    => 'Qui è presente un video ospitato su YouTube. Caricandolo, YouTube potrebbe raccogliere dati sulla tua navigazione tramite cookie di profilazione.',
            'service' => 'YouTube (Google LLC)',
        ],
        'vimeo.com'            => [
            'icon'    => '<svg width="32" height="32" viewBox="0 0 24 24" fill="#1AB7EA" aria-hidden="true"><path d="M23.977 6.416c-.105 2.338-1.739 5.543-4.894 9.609C15.9 20.693 12.999 23 10.621 23c-1.474 0-2.72-1.362-3.741-4.084C5.921 15.632 5.008 9.632 3.752 9.632c-.18 0-.812.38-1.897 1.141L.631 9.263c1.896-1.665 3.706-3.328 5.333-3.508 1.474-.158 2.381.866 2.722 3.071.72 4.588 1.083 7.44 1.983 7.44.72 0 2.072-2.338 3.35-5.846.18-.72-.36-1.083-1.983-.72 1.384-4.588 4.165-6.826 8.305-6.716 3.07.073 4.52 2.088 4.636 6.432z"/></svg>',
            'title'   => 'Video Vimeo',
            'desc'    => 'Qui è presente un video ospitato su Vimeo. Caricandolo, Vimeo potrebbe raccogliere dati sulla tua navigazione tramite cookie.',
            'service' => 'Vimeo Inc.',
        ],
        'google.com/maps'      => [
            'icon'    => '<svg width="32" height="32" viewBox="0 0 24 24" fill="#4285F4" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>',
            'title'   => 'Mappa Google Maps',
            'desc'    => 'Qui è presente una mappa interattiva di Google Maps. Caricandola, Google potrebbe raccogliere dati sulla tua posizione e navigazione.',
            'service' => 'Google Maps (Google LLC)',
        ],
        'maps.google'          => [
            'icon'    => '<svg width="32" height="32" viewBox="0 0 24 24" fill="#4285F4" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>',
            'title'   => 'Mappa Google Maps',
            'desc'    => 'Qui è presente una mappa interattiva di Google Maps. Caricandola, Google potrebbe raccogliere dati sulla tua posizione e navigazione.',
            'service' => 'Google Maps (Google LLC)',
        ],
        'facebook.com/plugins' => [
            'icon'    => '<svg width="32" height="32" viewBox="0 0 24 24" fill="#1877F2" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'title'   => 'Contenuto Facebook',
            'desc'    => 'Qui è presente un contenuto incorporato da Facebook. Caricandolo, Meta potrebbe raccogliere dati sulla tua navigazione tramite cookie di tracciamento.',
            'service' => 'Meta Platforms Inc.',
        ],
        'open.spotify.com'     => [
            'icon'    => '<svg width="32" height="32" viewBox="0 0 24 24" fill="#1DB954" aria-hidden="true"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>',
            'title'   => 'Contenuto Spotify',
            'desc'    => 'Qui è presente un contenuto incorporato da Spotify. Caricandolo, Spotify potrebbe raccogliere dati sulla tua navigazione.',
            'service' => 'Spotify AB',
        ],
    ];

    private static function create_iframe_placeholder( string $attrs, string $category ): string {
        // Estrai dimensioni dall'iframe originale
        $width  = '100%';
        $height = '400px';
        if ( preg_match( '/\bwidth\s*=\s*["\']?(\d+)/', $attrs, $m ) ) {
            $width = $m[1] . 'px';
        }
        if ( preg_match( '/\bheight\s*=\s*["\']?(\d+)/', $attrs, $m ) ) {
            $height = $m[1] . 'px';
        }

        // Estrai src originale
        $src = '';
        if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/', $attrs, $m ) ) {
            $src = $m[1];
        }

        // Trova le info descrittive del servizio
        $info = self::detect_service_info( $src );

        $icon    = $info['icon'];
        $title   = esc_html( $info['title'] );
        $desc    = esc_html( $info['desc'] );
        $service = esc_html( $info['service'] );
        $cat_attr = esc_attr( $category );

        $privacy_note = esc_html__( 'Per tutelare la tua privacy, questo contenuto non viene caricato automaticamente. Se scegli di visualizzarlo, i tuoi dati potrebbero essere condivisi con', 'gdpr-press' );
        $btn_text     = esc_html__( 'Gestisci le tue preferenze cookie', 'gdpr-press' );

        return '<div class="gdpr-press-placeholder" data-gdpr-category="' . $cat_attr . '" data-gdpr-src="' . esc_attr( $src ) . '" data-gdpr-attrs="' . esc_attr( $attrs ) . '" style="width:' . esc_attr( $width ) . ';height:' . esc_attr( $height ) . '">'
             . '<div class="gdpr-press-placeholder__inner">'
             . '<div class="gdpr-press-placeholder__icon">' . $icon . '</div>'
             . '<p class="gdpr-press-placeholder__title">' . $title . '</p>'
             . '<p class="gdpr-press-placeholder__text">' . $desc . '</p>'
             . '<p class="gdpr-press-placeholder__privacy">' . $privacy_note . ' <strong>' . $service . '</strong>.</p>'
             . '<button type="button" class="gdpr-press-placeholder__btn" data-gdpr-action="customize">' . $btn_text . '</button>'
             . '</div></div>';
    }

    /**
     * Rileva il servizio dall'URL src e restituisce le info descrittive.
     */
    private static function detect_service_info( string $src ): array {
        foreach ( self::SERVICE_INFO as $domain => $info ) {
            if ( stripos( $src, $domain ) !== false ) {
                return $info;
            }
        }

        // Fallback generico
        return [
            'icon'    => '<svg width="32" height="32" viewBox="0 0 24 24" fill="#6b7280" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
            'title'   => __( 'Contenuto esterno', 'gdpr-press' ),
            'desc'    => __( 'Qui è presente un contenuto incorporato da un servizio esterno.', 'gdpr-press' ),
            'service' => __( 'un servizio di terze parti', 'gdpr-press' ),
        ];
    }

    /* ── Blocco link preconnect/prefetch ──────────────────────────── */

    private static function block_link_hints( string $html, ?array $consent ): string {
        return preg_replace_callback(
            '/<link\b([^>]*)\brel\s*=\s*["\'](?:preconnect|dns-prefetch|prefetch|preload)["\']([^>]*)\/?\s*>/i',
            function ( $match ) use ( $consent ) {
                $attrs = $match[1] . $match[2];
                $full  = $match[0];

                $category = self::detect_category( $attrs, [] );
                if ( $category === null || self::category_allowed( $category, $consent ) ) {
                    return $full;
                }

                // Rimuovi il tag: non serve se lo script è bloccato
                return '<!-- gdpr-press: blocked link hint (' . esc_html( $category ) . ') -->';
            },
            $html
        ) ?? $html;
    }

    /* ── Rilevamento categoria ───────────────────────────────────── */

    private static function detect_category( string $haystack, array $custom_patterns ): ?string {
        // Controlla pattern personalizzati (tutti mappati a marketing)
        foreach ( $custom_patterns as $pattern ) {
            if ( $pattern !== '' && stripos( $haystack, $pattern ) !== false ) {
                return 'marketing';
            }
        }

        // Controlla mappa domini
        foreach ( self::DOMAIN_MAP as $domain => $category ) {
            if ( stripos( $haystack, $domain ) !== false ) {
                return $category;
            }
        }

        return null;
    }

    private static function detect_iframe_category( string $attrs ): ?string {
        foreach ( self::IFRAME_MAP as $domain => $category ) {
            if ( stripos( $attrs, $domain ) !== false ) {
                return $category;
            }
        }
        return null;
    }

    /* ── Verifica se la categoria è consentita ───────────────────── */

    private static function category_allowed( string $category, ?array $consent ): bool {
        if ( $category === 'necessary' ) {
            return true;
        }
        return $consent !== null && ! empty( $consent[ $category ] );
    }
}
