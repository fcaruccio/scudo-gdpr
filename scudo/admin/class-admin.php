<?php
/**
 * Pannello amministrativo Scudo.
 */

defined( 'ABSPATH' ) || exit;

class Scudo_Admin {

    private const OPTION_GROUP = 'scudo_options_group';
    private const OPTION_NAME  = 'scudo_options';
    private const PAGE_SLUG    = 'scudo';

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_filter( 'plugin_action_links_' . SCUDO_BASENAME, [ __CLASS__, 'action_links' ] );
    }

    /* ── Menu ────────────────────────────────────────────────────── */

    private const PARENT_SLUG = 'velocia';

    public static function add_menu(): void {
        // Menu top-level "Velocia" (crealo solo se non esiste già)
        global $menu;
        $exists = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && $item[2] === self::PARENT_SLUG ) {
                    $exists = true;
                    break;
                }
            }
        }

        if ( ! $exists ) {
            add_menu_page(
                'Velocia',
                'Velocia',
                'manage_options',
                self::PARENT_SLUG,
                [ __CLASS__, 'render_page' ],
                'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#a7aaad"><path d="M10 2l-7 3v5c0 4.4 3 8.5 7 9.6 4-1.1 7-5.2 7-9.6V5l-7-3zm0 2.2l5 2.1v3.7c0 3.5-2.2 6.7-5 7.8-2.8-1.1-5-4.3-5-7.8V6.3l5-2.1zm-1.3 8.2l-2.4-2.4 1.1-1.1 1.3 1.3 3.5-3.5 1.1 1.1-4.6 4.6z"/></svg>' ),
                30
            );
        }

        // Sottomenu "Scudo"
        add_submenu_page(
            self::PARENT_SLUG,
            __( 'Scudo — GDPR', 'scudo-cookie-privacy' ),
            __( 'Scudo — GDPR', 'scudo-cookie-privacy' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );

        // Rimuovi il sottomenu duplicato "Velocia" che WordPress crea automaticamente
        remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
    }

    public static function action_links( array $links ): array {
        $url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Impostazioni', 'scudo-cookie-privacy' ) . '</a>' );
        return $links;
    }

    /* ── Registra impostazioni ───────────────────────────────────── */

    public static function register_settings(): void {
        register_setting( self::OPTION_GROUP, self::OPTION_NAME, [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
            'default'           => scudo_defaults(),
        ] );
    }

    public static function sanitize( $input ): array {
        $defaults = scudo_defaults();
        $clean    = [];

        // Testi
        $text_fields = [
            'banner_title', 'banner_text', 'accept_text', 'reject_text',
            'customize_text', 'save_text',
            'cat_analytics_label', 'cat_analytics_desc',
            'cat_marketing_label', 'cat_marketing_desc',
            'cat_preferences_label', 'cat_preferences_desc',
        ];
        foreach ( $text_fields as $f ) {
            $clean[ $f ] = sanitize_text_field( $input[ $f ] ?? $defaults[ $f ] );
        }

        // Selettori
        $clean['banner_position'] = in_array( $input['banner_position'] ?? '', [ 'top', 'bottom' ], true )
            ? $input['banner_position'] : 'bottom';
        $clean['policy_page'] = absint( $input['policy_page'] ?? 0 );
        $clean['color_theme'] = in_array( $input['color_theme'] ?? '', [ 'dark', 'light', 'custom' ], true )
            ? $input['color_theme'] : 'dark';

        // Numeri
        $clean['consent_expiry'] = max( 1, min( 365, absint( $input['consent_expiry'] ?? 180 ) ) );

        // Booleani
        $clean['consent_logging']    = ! empty( $input['consent_logging'] );
        $clean['gcm_enabled']        = ! empty( $input['gcm_enabled'] );
        $clean['show_reopen_widget'] = ! empty( $input['show_reopen_widget'] );

        // Textarea
        $clean['custom_block_patterns'] = sanitize_textarea_field( $input['custom_block_patterns'] ?? '' );

        // Colori
        $color_fields = [ 'color_bg', 'color_text', 'color_accent', 'color_accept', 'color_reject' ];
        foreach ( $color_fields as $f ) {
            $val = sanitize_hex_color( $input[ $f ] ?? '' );
            $clean[ $f ] = $val ?: $defaults[ $f ];
        }

        // Se le opzioni sono cambiate, aggiorna la versione della policy
        $old = get_option( self::OPTION_NAME, [] );
        $policy_fields = array_merge( $text_fields, [ 'policy_page' ] );
        foreach ( $policy_fields as $f ) {
            if ( ( $old[ $f ] ?? '' ) !== ( $clean[ $f ] ?? '' ) ) {
                Scudo_Consent::bump_policy_version();
                break;
            }
        }

        return $clean;
    }

    /* ── Render pagina impostazioni ──────────────────────────────── */

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options = scudo_options();
        $stats   = Scudo_Consent::get_stats( 30 );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab navigation, no data mutation
        $tab     = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
        $admin_css = '.scudo-toggle{position:relative;display:inline-flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;line-height:1.4}'
            . '.scudo-toggle input[type="checkbox"]{position:absolute;opacity:0;width:0;height:0}'
            . '.scudo-toggle .scudo-toggle__track{position:relative;width:40px;height:22px;background:#ccd0d4;border-radius:11px;transition:background .2s ease;flex-shrink:0}'
            . '.scudo-toggle .scudo-toggle__track::after{content:"";position:absolute;top:2px;left:2px;width:18px;height:18px;background:#fff;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform .2s ease}'
            . '.scudo-toggle input:checked+.scudo-toggle__track{background:#0f9b58}'
            . '.scudo-toggle input:checked+.scudo-toggle__track::after{transform:translateX(18px)}'
            . '.scudo-toggle input:focus-visible+.scudo-toggle__track{outline:2px solid #2271b1;outline-offset:2px}'
            . '.scudo-check{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;margin:6px 0}'
            . '.scudo-check input[type="checkbox"]{position:absolute;opacity:0;width:0;height:0}'
            . '.scudo-check .scudo-check__box{width:20px;height:20px;border:2px solid #8c8f94;border-radius:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s ease,border-color .15s ease}'
            . '.scudo-check .scudo-check__box svg{opacity:0;transition:opacity .15s ease}'
            . '.scudo-check input:checked+.scudo-check__box{background:#0f9b58;border-color:#0f9b58}'
            . '.scudo-check input:checked+.scudo-check__box svg{opacity:1}'
            . '.scudo-check input:focus-visible+.scudo-check__box{outline:2px solid #2271b1;outline-offset:2px}'
            . '.scudo-tab-content{display:none}.scudo-tab-content.active{display:block}';
        wp_register_style( 'scudo-admin', false, array(), SCUDO_VERSION );
        wp_enqueue_style( 'scudo-admin' );
        wp_add_inline_style( 'scudo-admin', $admin_css );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Scudo — Compliance GDPR leggera. Davvero.', 'scudo-cookie-privacy' ); ?></h1>

            <!-- Tab Navigation -->
            <?php
            $tabs = [
                'dashboard' => __( 'Dashboard', 'scudo-cookie-privacy' ),
                'banner'    => __( 'Banner e Testi', 'scudo-cookie-privacy' ),
                'consent'   => __( 'Consenso e Blocco', 'scudo-cookie-privacy' ),
                'shortcodes' => __( 'Shortcode', 'scudo-cookie-privacy' ),
                'tools'     => __( 'Strumenti', 'scudo-cookie-privacy' ),
            ];
            ?>
            <nav class="nav-tab-wrapper" style="margin-bottom:20px;">
                <?php foreach ( $tabs as $slug => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $slug ) ); ?>"
                       class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if ( $tab === 'dashboard' ) : ?>
            <!-- ═══ TAB: DASHBOARD ═══ -->
            <div class="scudo-admin-stats" style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;">
                <?php
                $stat_items = [
                    [ 'label' => __( 'Totale consensi (30gg)', 'scudo-cookie-privacy' ), 'value' => $stats['total'], 'color' => '#2271b1' ],
                    [ 'label' => __( 'Accetta tutti', 'scudo-cookie-privacy' ), 'value' => $stats['accept_all'], 'color' => '#0f9b58' ],
                    [ 'label' => __( 'Rifiuta tutti', 'scudo-cookie-privacy' ), 'value' => $stats['reject_all'], 'color' => '#e94560' ],
                    [ 'label' => __( 'Personalizzati', 'scudo-cookie-privacy' ), 'value' => $stats['custom'], 'color' => '#f59e0b' ],
                ];
                foreach ( $stat_items as $item ) : ?>
                    <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo esc_attr( $item['color'] ); ?>;border-radius:4px;padding:12px 20px;min-width:160px;">
                        <div style="font-size:28px;font-weight:700;color:<?php echo esc_attr( $item['color'] ); ?>;"><?php echo esc_html( number_format_i18n( $item['value'] ) ); ?></div>
                        <div style="font-size:13px;color:#50575e;"><?php echo esc_html( $item['label'] ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&scudo_export=csv' ), 'scudo_export_csv' ) ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'Esporta registri consenso (CSV)', 'scudo-cookie-privacy' ); ?>
                </a>
            </p>

            <?php
            $requests = class_exists( 'Scudo_Rights' ) ? Scudo_Rights::get_requests( 10 ) : [];
            if ( ! empty( $requests ) ) : ?>
            <h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'Ultime richieste diritti GDPR', 'scudo-cookie-privacy' ); ?></h2>
            <table class="widefat striped" style="max-width:900px;">
                <thead><tr>
                    <th><?php esc_html_e( 'Data', 'scudo-cookie-privacy' ); ?></th>
                    <th><?php esc_html_e( 'Tipo', 'scudo-cookie-privacy' ); ?></th>
                    <th><?php esc_html_e( 'Nome', 'scudo-cookie-privacy' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'scudo-cookie-privacy' ); ?></th>
                    <th><?php esc_html_e( 'Stato', 'scudo-cookie-privacy' ); ?></th>
                </tr></thead>
                <tbody>
                    <?php
                    $type_labels = [
                        'access'        => __( 'Accesso ai dati', 'scudo-cookie-privacy' ),
                        'rectification' => __( 'Rettifica', 'scudo-cookie-privacy' ),
                        'erasure'       => __( 'Cancellazione', 'scudo-cookie-privacy' ),
                        'restriction'   => __( 'Limitazione', 'scudo-cookie-privacy' ),
                        'portability'   => __( 'Portabilità', 'scudo-cookie-privacy' ),
                        'objection'     => __( 'Opposizione', 'scudo-cookie-privacy' ),
                    ];
                    foreach ( $requests as $req ) : ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $req['created_at'] ) ) ); ?></td>
                        <td><?php echo esc_html( $type_labels[ $req['request_type'] ] ?? $req['request_type'] ); ?></td>
                        <td><?php echo esc_html( $req['name'] ); ?></td>
                        <td><?php echo esc_html( $req['email'] ); ?></td>
                        <td><?php echo $req['status'] === 'pending' ? '<span style="color:#f59e0b;font-weight:600;">' . esc_html__( 'In attesa', 'scudo-cookie-privacy' ) . '</span>' : '<span style="color:#0f9b58;font-weight:600;">' . esc_html__( 'Completata', 'scudo-cookie-privacy' ) . '</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php elseif ( $tab === 'shortcodes' ) : ?>
            <!-- ═══ TAB: SHORTCODE ═══ -->

            <div class="postbox" style="max-width:900px;">
                <div class="postbox-header"><h2 style="padding:8px 12px;"><?php esc_html_e( 'Tabella Cookie', 'scudo-cookie-privacy' ); ?></h2></div>
                <div class="inside">
                    <code style="display:block;font-size:14px;padding:10px 14px;background:#f0f0f1;border-radius:4px;margin-bottom:10px;">[scudo_cookie_table]</code>
                    <p class="description"><?php esc_html_e( 'Inseriscilo nella pagina Cookie Policy. Mostra automaticamente la tabella di tutti i cookie rilevati dalla scansione, organizzati per categoria (necessari, analitici, marketing, preferenze) con nome, fornitore, durata e descrizione.', 'scudo-cookie-privacy' ); ?></p>
                </div>
            </div>

            <div class="postbox" style="max-width:900px;">
                <div class="postbox-header"><h2 style="padding:8px 12px;"><?php esc_html_e( 'Privacy Policy', 'scudo-cookie-privacy' ); ?></h2></div>
                <div class="inside">
                    <code style="display:block;font-size:14px;padding:10px 14px;background:#f0f0f1;border-radius:4px;margin-bottom:10px;">[scudo_privacy_policy]</code>
                    <p class="description"><?php esc_html_e( 'Inseriscilo nella pagina Privacy Policy. Mostra l\'informativa completa generata dal wizard (tab Strumenti), conforme agli Artt. 13-14 del GDPR. Si aggiorna automaticamente quando modifichi i dati nel wizard.', 'scudo-cookie-privacy' ); ?></p>
                </div>
            </div>

            <div class="postbox" style="max-width:900px;">
                <div class="postbox-header"><h2 style="padding:8px 12px;"><?php esc_html_e( 'Form Diritti GDPR', 'scudo-cookie-privacy' ); ?></h2></div>
                <div class="inside">
                    <code style="display:block;font-size:14px;padding:10px 14px;background:#f0f0f1;border-radius:4px;margin-bottom:10px;">[scudo_rights_form]</code>
                    <p class="description"><?php esc_html_e( 'Inseriscilo in una pagina dedicata (es. "Esercita i tuoi diritti"). Mostra un form dove i visitatori possono richiedere accesso, rettifica, cancellazione, portabilità o opposizione ai propri dati. Le richieste arrivano via email e compaiono nella Dashboard.', 'scudo-cookie-privacy' ); ?></p>
                </div>
            </div>

            <?php elseif ( $tab === 'banner' ) : ?>
            <!-- ═══ TAB: BANNER E TESTI ═══ -->

            <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
                <?php settings_fields( self::OPTION_GROUP ); ?>
                <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=banner' ) ); ?>">
                <?php // Preserva valori delle altre tab ?>
                <input type="hidden" name="scudo_options[cat_analytics_label]" value="<?php echo esc_attr( $options['cat_analytics_label'] ); ?>">
                <input type="hidden" name="scudo_options[cat_analytics_desc]" value="<?php echo esc_attr( $options['cat_analytics_desc'] ); ?>">
                <input type="hidden" name="scudo_options[cat_marketing_label]" value="<?php echo esc_attr( $options['cat_marketing_label'] ); ?>">
                <input type="hidden" name="scudo_options[cat_marketing_desc]" value="<?php echo esc_attr( $options['cat_marketing_desc'] ); ?>">
                <input type="hidden" name="scudo_options[cat_preferences_label]" value="<?php echo esc_attr( $options['cat_preferences_label'] ); ?>">
                <input type="hidden" name="scudo_options[cat_preferences_desc]" value="<?php echo esc_attr( $options['cat_preferences_desc'] ); ?>">
                <input type="hidden" name="scudo_options[consent_expiry]" value="<?php echo esc_attr( $options['consent_expiry'] ); ?>">
                <input type="hidden" name="scudo_options[consent_logging]" value="<?php echo $options['consent_logging'] ? '1' : ''; ?>">
                <input type="hidden" name="scudo_options[gcm_enabled]" value="<?php echo $options['gcm_enabled'] ? '1' : ''; ?>">
                <input type="hidden" name="scudo_options[custom_block_patterns]" value="<?php echo esc_attr( $options['custom_block_patterns'] ); ?>">

                <!-- Card: Contenuto del banner -->
                <div class="postbox" style="max-width:900px;">
                    <div class="postbox-header"><h2 style="padding:8px 12px;"><?php esc_html_e( 'Contenuto del banner', 'scudo-cookie-privacy' ); ?></h2></div>
                    <div class="inside">
                        <p class="description"><?php esc_html_e( 'Il testo che il visitatore vede quando arriva sul sito per la prima volta.', 'scudo-cookie-privacy' ); ?></p>
                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th scope="row"><label for="banner_title"><?php esc_html_e( 'Titolo', 'scudo-cookie-privacy' ); ?></label></th>
                                <td><input type="text" name="scudo_options[banner_title]" id="banner_title" value="<?php echo esc_attr( $options['banner_title'] ); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="banner_text"><?php esc_html_e( 'Messaggio', 'scudo-cookie-privacy' ); ?></label></th>
                                <td><textarea name="scudo_options[banner_text]" id="banner_text" rows="3" class="large-text"><?php echo esc_textarea( $options['banner_text'] ); ?></textarea></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Card: Testi dei pulsanti -->
                <div class="postbox" style="max-width:900px;">
                    <div class="postbox-header"><h2 style="padding:8px 12px;"><?php esc_html_e( 'Testi dei pulsanti', 'scudo-cookie-privacy' ); ?></h2></div>
                    <div class="inside">
                        <p class="description"><?php esc_html_e( 'I pulsanti Accetta e Rifiuta hanno automaticamente la stessa evidenza grafica, come richiesto dal Garante.', 'scudo-cookie-privacy' ); ?></p>
                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th scope="row"><label for="accept_text"><?php esc_html_e( 'Accetta tutti', 'scudo-cookie-privacy' ); ?></label></th>
                                <td><input type="text" name="scudo_options[accept_text]" id="accept_text" value="<?php echo esc_attr( $options['accept_text'] ); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="reject_text"><?php esc_html_e( 'Rifiuta tutti', 'scudo-cookie-privacy' ); ?></label></th>
                                <td><input type="text" name="scudo_options[reject_text]" id="reject_text" value="<?php echo esc_attr( $options['reject_text'] ); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="customize_text"><?php esc_html_e( 'Personalizza', 'scudo-cookie-privacy' ); ?></label></th>
                                <td><input type="text" name="scudo_options[customize_text]" id="customize_text" value="<?php echo esc_attr( $options['customize_text'] ); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="save_text"><?php esc_html_e( 'Salva preferenze', 'scudo-cookie-privacy' ); ?></label></th>
                                <td><input type="text" name="scudo_options[save_text]" id="save_text" value="<?php echo esc_attr( $options['save_text'] ); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Card: Impostazioni generali -->
                <div class="postbox" style="max-width:900px;">
                    <div class="postbox-header"><h2 style="padding:8px 12px;"><?php esc_html_e( 'Impostazioni del banner', 'scudo-cookie-privacy' ); ?></h2></div>
                    <div class="inside">
                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th scope="row"><label for="banner_position"><?php esc_html_e( 'Posizione', 'scudo-cookie-privacy' ); ?></label></th>
                                <td>
                                    <select name="scudo_options[banner_position]" id="banner_position">
                                        <option value="bottom" <?php selected( $options['banner_position'], 'bottom' ); ?>><?php esc_html_e( 'In basso (consigliato)', 'scudo-cookie-privacy' ); ?></option>
                                        <option value="top" <?php selected( $options['banner_position'], 'top' ); ?>><?php esc_html_e( 'In alto', 'scudo-cookie-privacy' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="policy_page"><?php esc_html_e( 'Pagina Cookie Policy', 'scudo-cookie-privacy' ); ?></label></th>
                                <td>
                                    <?php
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages handles its own escaping
                                    wp_dropdown_pages( [
                                        'name'              => 'scudo_options[policy_page]',
                                        'id'                => 'policy_page',
                                        'selected'          => absint( $options['policy_page'] ),
                                        'show_option_none'  => esc_html__( '— Seleziona una pagina —', 'scudo-cookie-privacy' ),
                                        'option_none_value' => 0,
                                    ] );
                                    ?>
                                    <p class="description"><?php esc_html_e( 'Il banner inserisce un link a questa pagina. Puoi usare [scudo_cookie_table] al suo interno.', 'scudo-cookie-privacy' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Icona riapertura', 'scudo-cookie-privacy' ); ?></th>
                                <td>
                                    <label class="scudo-toggle">
                                        <input type="checkbox" name="scudo_options[show_reopen_widget]" value="1" <?php checked( $options['show_reopen_widget'] ); ?>>
                                        <span class="scudo-toggle__track"></span>
                                        <?php esc_html_e( 'Mostra l\'icona cookie in basso a sinistra per riaprire le preferenze', 'scudo-cookie-privacy' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Card: Tema e Colori -->
                <div class="postbox" style="max-width:900px;">
                    <div class="postbox-header"><h2 style="padding:8px 12px;"><?php esc_html_e( 'Tema del banner', 'scudo-cookie-privacy' ); ?></h2></div>
                    <div class="inside">
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
                            <?php
                            $themes = [
                                'dark'   => [
                                    'label' => __( 'Scuro', 'scudo-cookie-privacy' ),
                                    'bg' => '#1a1a2e', 'text' => '#fff', 'btn' => '#fff', 'btn_text' => '#1a1a2e',
                                ],
                                'light'  => [
                                    'label' => __( 'Chiaro', 'scudo-cookie-privacy' ),
                                    'bg' => '#ffffff', 'text' => '#1a1a2e', 'btn' => '#1a1a2e', 'btn_text' => '#fff',
                                ],
                                'custom' => [
                                    'label' => __( 'Personalizzato', 'scudo-cookie-privacy' ),
                                    'bg' => 'linear-gradient(135deg,#667eea,#764ba2)', 'text' => '#fff', 'btn' => '#fff', 'btn_text' => '#333',
                                ],
                            ];
                            foreach ( $themes as $theme_key => $theme_data ) :
                                $is_active = ( $options['color_theme'] ?? 'dark' ) === $theme_key;
                            ?>
                            <label style="cursor:pointer;display:block;">
                                <input type="radio" name="scudo_options[color_theme]" value="<?php echo esc_attr( $theme_key ); ?>" <?php checked( $is_active ); ?> style="display:none;" class="scudo-theme-radio">
                                <div style="width:220px;border:3px solid <?php echo $is_active ? '#2271b1' : '#c3c4c7'; ?>;border-radius:8px;overflow:hidden;transition:border-color .2s;">
                                    <!-- Mini preview -->
                                    <div style="background:<?php echo esc_attr( $theme_data['bg'] ); ?>;padding:14px 16px;min-height:80px;display:flex;flex-direction:column;justify-content:space-between;">
                                        <div>
                                            <div style="color:<?php echo esc_attr( $theme_data['text'] ); ?>;font-size:11px;font-weight:700;margin-bottom:2px;"><?php esc_html_e( 'Questo sito utilizza i cookie', 'scudo-cookie-privacy' ); ?></div>
                                            <div style="color:<?php echo esc_attr( $theme_data['text'] ); ?>;font-size:9px;opacity:0.7;"><?php esc_html_e( 'Utilizziamo cookie tecnici e...', 'scudo-cookie-privacy' ); ?></div>
                                        </div>
                                        <div style="display:flex;gap:6px;margin-top:10px;">
                                            <span style="background:<?php echo esc_attr( $theme_data['btn'] ); ?>;color:<?php echo esc_attr( $theme_data['btn_text'] ); ?>;font-size:9px;font-weight:600;padding:4px 10px;border-radius:4px;"><?php esc_html_e( 'Accetta', 'scudo-cookie-privacy' ); ?></span>
                                            <span style="background:<?php echo esc_attr( $theme_data['btn'] ); ?>;color:<?php echo esc_attr( $theme_data['btn_text'] ); ?>;font-size:9px;font-weight:600;padding:4px 10px;border-radius:4px;"><?php esc_html_e( 'Rifiuta', 'scudo-cookie-privacy' ); ?></span>
                                        </div>
                                    </div>
                                    <div style="padding:8px 12px;background:#f9fafb;text-align:center;font-size:12px;font-weight:600;color:<?php echo $is_active ? '#2271b1' : '#50575e'; ?>;">
                                        <?php echo esc_html( $theme_data['label'] ); ?>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Color pickers (visibili solo con tema "custom") -->
                        <div id="scudo-custom-colors" style="<?php echo ( $options['color_theme'] ?? 'dark' ) === 'custom' ? '' : 'display:none;'; ?>">
                            <p class="description" style="margin-bottom:12px;"><?php esc_html_e( 'Scegli i colori per adattare il banner al design del tuo sito.', 'scudo-cookie-privacy' ); ?></p>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
                                <?php
                                $colors = [
                                    'color_bg'     => __( 'Sfondo', 'scudo-cookie-privacy' ),
                                    'color_text'   => __( 'Testo', 'scudo-cookie-privacy' ),
                                    'color_accent' => __( 'Accento / Link', 'scudo-cookie-privacy' ),
                                    'color_accept' => __( 'Pulsante Accetta', 'scudo-cookie-privacy' ),
                                    'color_reject' => __( 'Pulsante Rifiuta', 'scudo-cookie-privacy' ),
                                ];
                                foreach ( $colors as $key => $label ) : ?>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <input type="color" name="scudo_options[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $options[ $key ] ); ?>" style="width:40px;height:34px;border:1px solid #c3c4c7;border-radius:4px;padding:2px;cursor:pointer;">
                                    <label for="<?php echo esc_attr( $key ); ?>" style="font-size:13px;"><?php echo esc_html( $label ); ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php
                        wp_register_script( 'scudo-admin-theme', '', array(), SCUDO_VERSION, true );
                        wp_enqueue_script( 'scudo-admin-theme' );
                        $theme_js = '(function(){' .
                            'var radios=document.querySelectorAll(".scudo-theme-radio");' .
                            'var customPanel=document.getElementById("scudo-custom-colors");' .
                            'radios.forEach(function(r){' .
                                'r.addEventListener("change",function(){' .
                                    'document.querySelectorAll(".scudo-theme-radio").forEach(function(rr){' .
                                        'rr.closest("label").querySelector("div").style.borderColor="#c3c4c7";' .
                                        'rr.closest("label").querySelector("div > div:last-child").style.color="#50575e";' .
                                    '});' .
                                    'this.closest("label").querySelector("div").style.borderColor="#2271b1";' .
                                    'this.closest("label").querySelector("div > div:last-child").style.color="#2271b1";' .
                                    'customPanel.style.display=this.value==="custom"?"":"none";' .
                                '});' .
                            '});' .
                        '})();';
                        wp_add_inline_script( 'scudo-admin-theme', $theme_js );
                        ?>
                    </div>
                </div>

                <?php submit_button( __( 'Salva impostazioni', 'scudo-cookie-privacy' ) ); ?>
            </form>

            <?php elseif ( $tab === 'consent' ) : ?>
            <!-- ═══ TAB: CONSENSO E BLOCCO ═══ -->
            <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
                <?php settings_fields( self::OPTION_GROUP ); ?>
                <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=consent' ) ); ?>">
                <?php // Hidden fields per preservare i valori delle altre tab ?>
                <input type="hidden" name="scudo_options[banner_position]" value="<?php echo esc_attr( $options['banner_position'] ); ?>">
                <input type="hidden" name="scudo_options[banner_title]" value="<?php echo esc_attr( $options['banner_title'] ); ?>">
                <input type="hidden" name="scudo_options[banner_text]" value="<?php echo esc_attr( $options['banner_text'] ); ?>">
                <input type="hidden" name="scudo_options[accept_text]" value="<?php echo esc_attr( $options['accept_text'] ); ?>">
                <input type="hidden" name="scudo_options[reject_text]" value="<?php echo esc_attr( $options['reject_text'] ); ?>">
                <input type="hidden" name="scudo_options[customize_text]" value="<?php echo esc_attr( $options['customize_text'] ); ?>">
                <input type="hidden" name="scudo_options[save_text]" value="<?php echo esc_attr( $options['save_text'] ); ?>">
                <input type="hidden" name="scudo_options[policy_page]" value="<?php echo esc_attr( $options['policy_page'] ); ?>">
                <input type="hidden" name="scudo_options[show_reopen_widget]" value="<?php echo $options['show_reopen_widget'] ? '1' : ''; ?>">
                <input type="hidden" name="scudo_options[color_theme]" value="<?php echo esc_attr( $options['color_theme'] ); ?>">
                <input type="hidden" name="scudo_options[color_bg]" value="<?php echo esc_attr( $options['color_bg'] ); ?>">
                <input type="hidden" name="scudo_options[color_text]" value="<?php echo esc_attr( $options['color_text'] ); ?>">
                <input type="hidden" name="scudo_options[color_accent]" value="<?php echo esc_attr( $options['color_accent'] ); ?>">
                <input type="hidden" name="scudo_options[color_accept]" value="<?php echo esc_attr( $options['color_accept'] ); ?>">
                <input type="hidden" name="scudo_options[color_reject]" value="<?php echo esc_attr( $options['color_reject'] ); ?>">

                <!-- Card: Categorie Cookie -->
                <?php
                $cats = [
                    'analytics'   => [ __( 'Analitici', 'scudo-cookie-privacy' ), __( 'Google Analytics, Hotjar, Clarity e simili', 'scudo-cookie-privacy' ) ],
                    'marketing'   => [ __( 'Marketing', 'scudo-cookie-privacy' ), __( 'Facebook Pixel, Google Ads, YouTube, social embed', 'scudo-cookie-privacy' ) ],
                    'preferences' => [ __( 'Preferenze', 'scudo-cookie-privacy' ), __( 'Google Fonts, widget di personalizzazione', 'scudo-cookie-privacy' ) ],
                ];
                foreach ( $cats as $slug => $cat_info ) : ?>
                <div class="postbox" style="max-width:900px;">
                    <div class="postbox-header"><h2 style="padding:8px 12px;"><?php
                    // translators: %s is the cookie category name (e.g. Analytics, Marketing, Preferences).
                    printf( esc_html__( 'Categoria: %s', 'scudo-cookie-privacy' ), esc_html( $cat_info[0] ) );
                ?></h2></div>
                    <div class="inside">
                        <p class="description"><?php echo esc_html( $cat_info[1] ); ?></p>
                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th scope="row"><label for="cat_<?php echo esc_attr( $slug ); ?>_label"><?php esc_html_e( 'Nome visibile', 'scudo-cookie-privacy' ); ?></label></th>
                                <td><input type="text" name="scudo_options[cat_<?php echo esc_attr( $slug ); ?>_label]" id="cat_<?php echo esc_attr( $slug ); ?>_label" value="<?php echo esc_attr( $options[ 'cat_' . $slug . '_label' ] ); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="cat_<?php echo esc_attr( $slug ); ?>_desc"><?php esc_html_e( 'Descrizione', 'scudo-cookie-privacy' ); ?></label></th>
                                <td><textarea name="scudo_options[cat_<?php echo esc_attr( $slug ); ?>_desc]" id="cat_<?php echo esc_attr( $slug ); ?>_desc" rows="2" class="large-text"><?php echo esc_textarea( $options[ 'cat_' . $slug . '_desc' ] ); ?></textarea></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Card: Gestione consenso -->
                <div class="postbox" style="max-width:900px;">
                    <div class="postbox-header"><h2 style="padding:8px 12px;"><?php esc_html_e( 'Gestione del consenso', 'scudo-cookie-privacy' ); ?></h2></div>
                    <div class="inside">
                        <p class="description"><?php esc_html_e( 'Scudo salva ogni consenso come prova legale per il Garante Privacy.', 'scudo-cookie-privacy' ); ?></p>
                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th scope="row"><label for="consent_expiry"><?php esc_html_e( 'Scadenza', 'scudo-cookie-privacy' ); ?></label></th>
                                <td>
                                    <input type="number" name="scudo_options[consent_expiry]" id="consent_expiry" value="<?php echo esc_attr( $options['consent_expiry'] ); ?>" min="1" max="365" class="small-text"> <?php esc_html_e( 'giorni', 'scudo-cookie-privacy' ); ?>
                                    <p class="description"><?php esc_html_e( 'Dopo quanti giorni il banner riappare. Il Garante raccomanda 180 (6 mesi).', 'scudo-cookie-privacy' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Registro', 'scudo-cookie-privacy' ); ?></th>
                                <td>
                                    <label class="scudo-toggle">
                                        <input type="checkbox" name="scudo_options[consent_logging]" value="1" <?php checked( $options['consent_logging'] ); ?>>
                                        <span class="scudo-toggle__track"></span>
                                        <?php esc_html_e( 'Salva ogni consenso nel database', 'scudo-cookie-privacy' ); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Registra data, ora, scelte e versione policy. L\'IP viene hashato per privacy. Prova legale in caso di ispezione.', 'scudo-cookie-privacy' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Card: Google Consent Mode v2 -->
                <div class="postbox" style="max-width:900px;">
                    <div class="postbox-header"><h2 style="padding:8px 12px;"><?php esc_html_e( 'Google Consent Mode v2', 'scudo-cookie-privacy' ); ?></h2></div>
                    <div class="inside">
                        <p class="description"><?php esc_html_e( 'Il protocollo con cui Scudo comunica a Google lo stato del consenso. Obbligatorio dal marzo 2024 per chi usa Google Ads nell\'EEA.', 'scudo-cookie-privacy' ); ?></p>
                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Stato', 'scudo-cookie-privacy' ); ?></th>
                                <td>
                                    <label class="scudo-toggle">
                                        <input type="checkbox" name="scudo_options[gcm_enabled]" value="1" <?php checked( $options['gcm_enabled'] ); ?>>
                                        <span class="scudo-toggle__track"></span>
                                        <?php esc_html_e( 'Attiva Google Consent Mode v2 (Basic mode)', 'scudo-cookie-privacy' ); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Attivalo se usi Google Analytics o Google Ads. Scudo imposta tutto su "denied" e aggiorna dopo il consenso.', 'scudo-cookie-privacy' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Card: Blocco script -->
                <div class="postbox" style="max-width:900px;">
                    <div class="postbox-header"><h2 style="padding:8px 12px;"><?php esc_html_e( 'Blocco preventivo degli script', 'scudo-cookie-privacy' ); ?></h2></div>
                    <div class="inside">
                        <p class="description"><?php esc_html_e( 'Scudo blocca automaticamente 30+ servizi (GA, Facebook Pixel, YouTube, ecc.). Aggiungi qui quelli non ancora nella lista.', 'scudo-cookie-privacy' ); ?></p>
                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th scope="row"><label for="custom_block_patterns"><?php esc_html_e( 'Servizi aggiuntivi', 'scudo-cookie-privacy' ); ?></label></th>
                                <td>
                                    <textarea name="scudo_options[custom_block_patterns]" id="custom_block_patterns" rows="4" class="large-text code"><?php echo esc_textarea( $options['custom_block_patterns'] ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'Un dominio per riga. Es: tracking.mioservizio.com — verranno bloccati come "marketing".', 'scudo-cookie-privacy' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button( __( 'Salva impostazioni', 'scudo-cookie-privacy' ) ); ?>
            </form>

            <?php elseif ( $tab === 'tools' ) : ?>
            <!-- ═══ TAB: STRUMENTI ═══ -->

            <!-- Scansione Cookie -->
            <h2 class="title"><?php esc_html_e( 'Scansione Cookie', 'scudo-cookie-privacy' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Scansiona il tuo sito per rilevare automaticamente i cookie impostati da script e servizi di terze parti. I risultati verranno utilizzati per popolare la tabella dei cookie nella cookie policy.', 'scudo-cookie-privacy' ); ?></p>

            <p style="margin:16px 0;">
                <button type="button" class="button button-secondary" id="scudo-scan-btn">
                    <?php esc_html_e( 'Scansiona cookie', 'scudo-cookie-privacy' ); ?>
                </button>
                <span id="scudo-scan-status" style="margin-left:12px;"></span>
            </p>

            <div id="scudo-scan-results" style="display:none;">
                <table class="widefat striped" style="max-width:800px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Cookie', 'scudo-cookie-privacy' ); ?></th>
                            <th><?php esc_html_e( 'Categoria', 'scudo-cookie-privacy' ); ?></th>
                            <th><?php esc_html_e( 'Fornitore', 'scudo-cookie-privacy' ); ?></th>
                            <th><?php esc_html_e( 'Stato', 'scudo-cookie-privacy' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="scudo-scan-tbody"></tbody>
                </table>
            </div>

            <hr>

            <!-- Shortcode info -->
            <h2 class="title"><?php esc_html_e( 'Cookie Policy', 'scudo-cookie-privacy' ); ?></h2>
            <p><?php esc_html_e( 'Usa questo shortcode nella pagina della cookie policy per mostrare automaticamente la tabella dei cookie:', 'scudo-cookie-privacy' ); ?></p>
            <p><code>[scudo_cookie_table]</code></p>
            <p class="description"><?php esc_html_e( 'La tabella mostra tutti i cookie rilevati dalla scansione, organizzati per categoria, con nome, fornitore, durata e descrizione.', 'scudo-cookie-privacy' ); ?></p>

            <?php Scudo_Privacy_Policy::render_wizard(); ?>

            <hr>

            <!-- Google Fonts Self-Hosting -->
            <h2 class="title"><?php esc_html_e( 'Google Fonts — Self-Hosting', 'scudo-cookie-privacy' ); ?></h2>
            <?php if ( Scudo_Fonts::is_active() ) : ?>
                <p style="color:#0f9b58;font-weight:600;"><?php esc_html_e( 'I Google Fonts sono attualmente serviti dal tuo server. Nessun dato viene inviato a Google.', 'scudo-cookie-privacy' ); ?></p>
            <?php else : ?>
                <p class="description"><?php esc_html_e( 'Scarica automaticamente i Google Fonts utilizzati dal tuo sito e servili localmente. Questo evita che l\'IP dei tuoi visitatori venga trasferito a Google (sentenza Tribunale di Monaco, 2022).', 'scudo-cookie-privacy' ); ?></p>
            <?php endif; ?>

            <p style="margin:16px 0;">
                <button type="button" class="button button-secondary" id="scudo-fonts-btn">
                    <?php echo Scudo_Fonts::is_active()
                        ? esc_html__( 'Aggiorna font locali', 'scudo-cookie-privacy' )
                        : esc_html__( 'Scarica Google Fonts', 'scudo-cookie-privacy' ); ?>
                </button>
                <span id="scudo-fonts-status" style="margin-left:12px;"></span>
            </p>

            <?php
            wp_register_script( 'scudo-admin-tools', '', array(), SCUDO_VERSION, true );
            wp_enqueue_script( 'scudo-admin-tools' );
            wp_localize_script( 'scudo-admin-tools', 'scudoAdminTools', array(
                'nonce' => wp_create_nonce( 'scudo_nonce' ),
                'i18n'  => array(
                    'scanning'      => __( 'Scansione in corso...', 'scudo-cookie-privacy' ),
                    'necessary'     => __( 'Necessario', 'scudo-cookie-privacy' ),
                    'analytics'     => __( 'Analitico', 'scudo-cookie-privacy' ),
                    'marketing'     => __( 'Marketing', 'scudo-cookie-privacy' ),
                    'preferences'   => __( 'Preferenza', 'scudo-cookie-privacy' ),
                    'recognized'    => __( 'Riconosciuto', 'scudo-cookie-privacy' ),
                    'unclassified'  => __( 'Non classificato', 'scudo-cookie-privacy' ),
                    'error'         => __( 'Errore:', 'scudo-cookie-privacy' ),
                    'networkError'  => __( 'Errore di rete.', 'scudo-cookie-privacy' ),
                    'downloading'   => __( 'Download in corso... (può richiedere qualche secondo)', 'scudo-cookie-privacy' ),
                ),
            ) );
            $tools_js = '(function(){' .
                'var nonce=scudoAdminTools.nonce;' .
                'var scanBtn=document.getElementById("scudo-scan-btn");' .
                'var scanStatus=document.getElementById("scudo-scan-status");' .
                'var scanResults=document.getElementById("scudo-scan-results");' .
                'var scanTbody=document.getElementById("scudo-scan-tbody");' .
                'if(scanBtn){' .
                    'scanBtn.addEventListener("click",function(){' .
                        'scanBtn.disabled=true;' .
                        'scanStatus.textContent=scudoAdminTools.i18n.scanning;' .
                        'scanResults.style.display="none";' .
                        'scanTbody.innerHTML="";' .
                        'var fd=new FormData();' .
                        'fd.append("action","scudo_scan_cookies");' .
                        'fd.append("nonce",nonce);' .
                        'fetch(ajaxurl,{method:"POST",body:fd,credentials:"same-origin"})' .
                            '.then(function(r){return r.json();})' .
                            '.then(function(data){' .
                                'scanBtn.disabled=false;' .
                                'if(data.success){' .
                                    'var cats={necessary:scudoAdminTools.i18n.necessary,analytics:scudoAdminTools.i18n.analytics,marketing:scudoAdminTools.i18n.marketing,preferences:scudoAdminTools.i18n.preferences};' .
                                    'scanStatus.textContent=data.data.count+" cookie rilevati.";' .
                                    'data.data.classified.forEach(function(c){' .
                                        'var tr=document.createElement("tr");' .
                                        'var cat=c.info?(cats[c.info.category]||c.info.category):"\u2014";' .
                                        'var prov=c.info?c.info.provider:"\u2014";' .
                                        'var st=c.known?"\u2713 "+scudoAdminTools.i18n.recognized:"\u26A0 "+scudoAdminTools.i18n.unclassified;' .
                                        'tr.innerHTML="<td><code>"+c.name+"</code></td><td>"+cat+"</td><td>"+prov+"</td><td>"+st+"</td>";' .
                                        'scanTbody.appendChild(tr);' .
                                    '});' .
                                    'scanResults.style.display="block";' .
                                '}else{' .
                                    'scanStatus.textContent=scudoAdminTools.i18n.error+" "+(data.data||"unknown");' .
                                '}' .
                            '})' .
                            '.catch(function(){' .
                                'scanBtn.disabled=false;' .
                                'scanStatus.textContent=scudoAdminTools.i18n.networkError;' .
                            '});' .
                    '});' .
                '}' .
                'var fontsBtn=document.getElementById("scudo-fonts-btn");' .
                'var fontsStatus=document.getElementById("scudo-fonts-status");' .
                'if(fontsBtn){' .
                    'fontsBtn.addEventListener("click",function(){' .
                        'fontsBtn.disabled=true;' .
                        'fontsStatus.textContent=scudoAdminTools.i18n.downloading;' .
                        'var fd=new FormData();' .
                        'fd.append("action","scudo_download_fonts");' .
                        'fd.append("nonce",nonce);' .
                        'fetch(ajaxurl,{method:"POST",body:fd,credentials:"same-origin"})' .
                            '.then(function(r){return r.json();})' .
                            '.then(function(data){' .
                                'fontsBtn.disabled=false;' .
                                'fontsStatus.textContent=data.success?data.data.message:(scudoAdminTools.i18n.error+" "+(data.data||"unknown"));' .
                                'fontsStatus.style.color=data.success?"#0f9b58":"#e94560";' .
                            '})' .
                            '.catch(function(){' .
                                'fontsBtn.disabled=false;' .
                                'fontsStatus.textContent=scudoAdminTools.i18n.networkError;' .
                                'fontsStatus.style.color="#e94560";' .
                            '});' .
                    '});' .
                '}' .
            '})();';
            wp_add_inline_script( 'scudo-admin-tools', $tools_js );
            ?>

            <?php endif; // end tabs ?>
        </div>
        <?php
    }
}
