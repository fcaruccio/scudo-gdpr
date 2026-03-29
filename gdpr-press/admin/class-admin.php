<?php
/**
 * Pannello amministrativo GDPR Press.
 */

defined( 'ABSPATH' ) || exit;

class GDPR_Press_Admin {

    private const OPTION_GROUP = 'gdpr_press_options_group';
    private const OPTION_NAME  = 'gdpr_press_options';
    private const PAGE_SLUG    = 'gdpr-press';

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_filter( 'plugin_action_links_' . GDPR_PRESS_BASENAME, [ __CLASS__, 'action_links' ] );
    }

    /* ── Menu ────────────────────────────────────────────────────── */

    public static function add_menu(): void {
        add_options_page(
            __( 'GDPR Press', 'gdpr-press' ),
            __( 'GDPR Press', 'gdpr-press' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function action_links( array $links ): array {
        $url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
        array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Impostazioni', 'gdpr-press' ) . '</a>' );
        return $links;
    }

    /* ── Registra impostazioni ───────────────────────────────────── */

    public static function register_settings(): void {
        register_setting( self::OPTION_GROUP, self::OPTION_NAME, [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
            'default'           => gdpr_press_defaults(),
        ] );
    }

    public static function sanitize( $input ): array {
        $defaults = gdpr_press_defaults();
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
                GDPR_Press_Consent::bump_policy_version();
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

        $options = gdpr_press_options();
        $stats   = GDPR_Press_Consent::get_stats( 30 );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'GDPR Press — Impostazioni', 'gdpr-press' ); ?></h1>

            <!-- Statistiche consensi -->
            <div class="gdpr-press-admin-stats" style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;">
                <?php
                $stat_items = [
                    [ 'label' => __( 'Totale consensi (30gg)', 'gdpr-press' ), 'value' => $stats['total'], 'color' => '#2271b1' ],
                    [ 'label' => __( 'Accetta tutti', 'gdpr-press' ), 'value' => $stats['accept_all'], 'color' => '#0f9b58' ],
                    [ 'label' => __( 'Rifiuta tutti', 'gdpr-press' ), 'value' => $stats['reject_all'], 'color' => '#e94560' ],
                    [ 'label' => __( 'Personalizzati', 'gdpr-press' ), 'value' => $stats['custom'], 'color' => '#f59e0b' ],
                ];
                foreach ( $stat_items as $item ) :
                    ?>
                    <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo esc_attr( $item['color'] ); ?>;border-radius:4px;padding:12px 20px;min-width:160px;">
                        <div style="font-size:28px;font-weight:700;color:<?php echo esc_attr( $item['color'] ); ?>;"><?php echo esc_html( number_format_i18n( $item['value'] ) ); ?></div>
                        <div style="font-size:13px;color:#50575e;"><?php echo esc_html( $item['label'] ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&gdpr_press_export=csv' ), 'gdpr_press_export_csv' ) ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'Esporta registri consenso (CSV)', 'gdpr-press' ); ?>
                </a>
            </p>

            <?php
            // Richieste diritti degli interessati
            $requests = class_exists( 'GDPR_Press_Rights' ) ? GDPR_Press_Rights::get_requests( 10 ) : [];
            if ( ! empty( $requests ) ) :
            ?>
            <h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'Ultime richieste diritti GDPR', 'gdpr-press' ); ?></h2>
            <table class="widefat striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Data', 'gdpr-press' ); ?></th>
                        <th><?php esc_html_e( 'Tipo', 'gdpr-press' ); ?></th>
                        <th><?php esc_html_e( 'Nome', 'gdpr-press' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'gdpr-press' ); ?></th>
                        <th><?php esc_html_e( 'Stato', 'gdpr-press' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $requests as $req ) : ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $req['created_at'] ) ) ); ?></td>
                        <td><?php echo esc_html( ucfirst( $req['request_type'] ) ); ?></td>
                        <td><?php echo esc_html( $req['name'] ); ?></td>
                        <td><?php echo esc_html( $req['email'] ); ?></td>
                        <td>
                            <?php if ( $req['status'] === 'pending' ) : ?>
                                <span style="color:#f59e0b;font-weight:600;"><?php esc_html_e( 'In attesa', 'gdpr-press' ); ?></span>
                            <?php else : ?>
                                <span style="color:#0f9b58;font-weight:600;"><?php esc_html_e( 'Completata', 'gdpr-press' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description"><?php esc_html_e( 'Per far esercitare i diritti agli utenti, usa lo shortcode [gdpr_press_rights_form] in una pagina.', 'gdpr-press' ); ?></p>
            <?php else : ?>
            <p class="description" style="margin-top:16px;">
                <?php esc_html_e( 'Per far esercitare i diritti agli utenti, usa lo shortcode [gdpr_press_rights_form] in una pagina.', 'gdpr-press' ); ?>
            </p>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( self::OPTION_GROUP ); ?>

                <!-- Banner -->
                <h2 class="title"><?php esc_html_e( 'Banner', 'gdpr-press' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="banner_position"><?php esc_html_e( 'Posizione', 'gdpr-press' ); ?></label></th>
                        <td>
                            <select name="gdpr_press_options[banner_position]" id="banner_position">
                                <option value="bottom" <?php selected( $options['banner_position'], 'bottom' ); ?>><?php esc_html_e( 'In basso', 'gdpr-press' ); ?></option>
                                <option value="top" <?php selected( $options['banner_position'], 'top' ); ?>><?php esc_html_e( 'In alto', 'gdpr-press' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="banner_title"><?php esc_html_e( 'Titolo', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" name="gdpr_press_options[banner_title]" id="banner_title" value="<?php echo esc_attr( $options['banner_title'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="banner_text"><?php esc_html_e( 'Testo', 'gdpr-press' ); ?></label></th>
                        <td><textarea name="gdpr_press_options[banner_text]" id="banner_text" rows="3" class="large-text"><?php echo esc_textarea( $options['banner_text'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="accept_text"><?php esc_html_e( 'Testo "Accetta"', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" name="gdpr_press_options[accept_text]" id="accept_text" value="<?php echo esc_attr( $options['accept_text'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reject_text"><?php esc_html_e( 'Testo "Rifiuta"', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" name="gdpr_press_options[reject_text]" id="reject_text" value="<?php echo esc_attr( $options['reject_text'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="customize_text"><?php esc_html_e( 'Testo "Personalizza"', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" name="gdpr_press_options[customize_text]" id="customize_text" value="<?php echo esc_attr( $options['customize_text'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="save_text"><?php esc_html_e( 'Testo "Salva preferenze"', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" name="gdpr_press_options[save_text]" id="save_text" value="<?php echo esc_attr( $options['save_text'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="policy_page"><?php esc_html_e( 'Pagina Cookie Policy', 'gdpr-press' ); ?></label></th>
                        <td>
                            <?php wp_dropdown_pages( [
                                'name'              => 'gdpr_press_options[policy_page]',
                                'id'                => 'policy_page',
                                'selected'          => $options['policy_page'],
                                'show_option_none'  => __( '— Seleziona —', 'gdpr-press' ),
                                'option_none_value' => 0,
                            ] ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Widget riapertura', 'gdpr-press' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="gdpr_press_options[show_reopen_widget]" value="1" <?php checked( $options['show_reopen_widget'] ); ?>>
                            <?php esc_html_e( 'Mostra icona per riaprire le preferenze cookie', 'gdpr-press' ); ?></label>
                        </td>
                    </tr>
                </table>

                <!-- Categorie Cookie -->
                <h2 class="title"><?php esc_html_e( 'Categorie Cookie', 'gdpr-press' ); ?></h2>
                <table class="form-table">
                    <?php
                    $cats = [
                        'analytics'   => __( 'Analitici', 'gdpr-press' ),
                        'marketing'   => __( 'Marketing', 'gdpr-press' ),
                        'preferences' => __( 'Preferenze', 'gdpr-press' ),
                    ];
                    foreach ( $cats as $slug => $default_label ) : ?>
                    <tr>
                        <th scope="row"><label for="cat_<?php echo $slug; ?>_label"><?php echo esc_html( $default_label ); ?> — <?php esc_html_e( 'Nome', 'gdpr-press' ); ?></label></th>
                        <td><input type="text" name="gdpr_press_options[cat_<?php echo $slug; ?>_label]" id="cat_<?php echo $slug; ?>_label" value="<?php echo esc_attr( $options[ 'cat_' . $slug . '_label' ] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cat_<?php echo $slug; ?>_desc"><?php echo esc_html( $default_label ); ?> — <?php esc_html_e( 'Descrizione', 'gdpr-press' ); ?></label></th>
                        <td><textarea name="gdpr_press_options[cat_<?php echo $slug; ?>_desc]" id="cat_<?php echo $slug; ?>_desc" rows="2" class="large-text"><?php echo esc_textarea( $options[ 'cat_' . $slug . '_desc' ] ); ?></textarea></td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <!-- Consenso -->
                <h2 class="title"><?php esc_html_e( 'Consenso', 'gdpr-press' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="consent_expiry"><?php esc_html_e( 'Durata consenso (giorni)', 'gdpr-press' ); ?></label></th>
                        <td>
                            <input type="number" name="gdpr_press_options[consent_expiry]" id="consent_expiry" value="<?php echo esc_attr( $options['consent_expiry'] ); ?>" min="1" max="365" class="small-text">
                            <p class="description"><?php esc_html_e( 'Il Garante Privacy consiglia 180 giorni (6 mesi).', 'gdpr-press' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Registrazione consensi', 'gdpr-press' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="gdpr_press_options[consent_logging]" value="1" <?php checked( $options['consent_logging'] ); ?>>
                            <?php esc_html_e( 'Registra i consensi nel database (prova legale)', 'gdpr-press' ); ?></label>
                        </td>
                    </tr>
                </table>

                <!-- Google Consent Mode v2 -->
                <h2 class="title"><?php esc_html_e( 'Google Consent Mode v2', 'gdpr-press' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Attiva GCM v2', 'gdpr-press' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="gdpr_press_options[gcm_enabled]" value="1" <?php checked( $options['gcm_enabled'] ); ?>>
                            <?php esc_html_e( 'Abilita Google Consent Mode v2 (Basic mode)', 'gdpr-press' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Imposta automaticamente i default su "denied" e li aggiorna dopo il consenso. Richiesto per Google Ads e GA4 nell\'EEA.', 'gdpr-press' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- Blocco Script -->
                <h2 class="title"><?php esc_html_e( 'Blocco Script', 'gdpr-press' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="custom_block_patterns"><?php esc_html_e( 'Pattern personalizzati', 'gdpr-press' ); ?></label></th>
                        <td>
                            <textarea name="gdpr_press_options[custom_block_patterns]" id="custom_block_patterns" rows="5" class="large-text code"><?php echo esc_textarea( $options['custom_block_patterns'] ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Un pattern per riga. Gli script che contengono questi pattern verranno bloccati come "marketing". Es: tracking.example.com', 'gdpr-press' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- Colori -->
                <h2 class="title"><?php esc_html_e( 'Colori', 'gdpr-press' ); ?></h2>
                <table class="form-table">
                    <?php
                    $colors = [
                        'color_bg'     => __( 'Sfondo banner', 'gdpr-press' ),
                        'color_text'   => __( 'Testo banner', 'gdpr-press' ),
                        'color_accent' => __( 'Accento (link, focus)', 'gdpr-press' ),
                        'color_accept' => __( 'Pulsante Accetta', 'gdpr-press' ),
                        'color_reject' => __( 'Pulsante Rifiuta', 'gdpr-press' ),
                    ];
                    foreach ( $colors as $key => $label ) : ?>
                    <tr>
                        <th scope="row"><label for="<?php echo $key; ?>"><?php echo esc_html( $label ); ?></label></th>
                        <td><input type="color" name="gdpr_press_options[<?php echo $key; ?>]" id="<?php echo $key; ?>" value="<?php echo esc_attr( $options[ $key ] ); ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button( __( 'Salva impostazioni', 'gdpr-press' ) ); ?>
            </form>

            <hr>

            <!-- Scansione Cookie -->
            <h2 class="title"><?php esc_html_e( 'Scansione Cookie', 'gdpr-press' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Scansiona il tuo sito per rilevare automaticamente i cookie impostati da script e servizi di terze parti. I risultati verranno utilizzati per popolare la tabella dei cookie nella cookie policy.', 'gdpr-press' ); ?></p>

            <p style="margin:16px 0;">
                <button type="button" class="button button-secondary" id="gdpr-press-scan-btn">
                    <?php esc_html_e( 'Scansiona cookie', 'gdpr-press' ); ?>
                </button>
                <span id="gdpr-press-scan-status" style="margin-left:12px;"></span>
            </p>

            <div id="gdpr-press-scan-results" style="display:none;">
                <table class="widefat striped" style="max-width:800px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Cookie', 'gdpr-press' ); ?></th>
                            <th><?php esc_html_e( 'Categoria', 'gdpr-press' ); ?></th>
                            <th><?php esc_html_e( 'Fornitore', 'gdpr-press' ); ?></th>
                            <th><?php esc_html_e( 'Stato', 'gdpr-press' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="gdpr-press-scan-tbody"></tbody>
                </table>
            </div>

            <hr>

            <!-- Shortcode info -->
            <h2 class="title"><?php esc_html_e( 'Cookie Policy', 'gdpr-press' ); ?></h2>
            <p><?php esc_html_e( 'Usa questo shortcode nella pagina della cookie policy per mostrare automaticamente la tabella dei cookie:', 'gdpr-press' ); ?></p>
            <p><code>[gdpr_press_cookie_table]</code></p>
            <p class="description"><?php esc_html_e( 'La tabella mostra tutti i cookie rilevati dalla scansione, organizzati per categoria, con nome, fornitore, durata e descrizione.', 'gdpr-press' ); ?></p>

            <?php GDPR_Press_Privacy_Policy::render_wizard(); ?>

            <hr>

            <!-- Google Fonts Self-Hosting -->
            <h2 class="title"><?php esc_html_e( 'Google Fonts — Self-Hosting', 'gdpr-press' ); ?></h2>
            <?php if ( GDPR_Press_Fonts::is_active() ) : ?>
                <p style="color:#0f9b58;font-weight:600;"><?php esc_html_e( 'I Google Fonts sono attualmente serviti dal tuo server. Nessun dato viene inviato a Google.', 'gdpr-press' ); ?></p>
            <?php else : ?>
                <p class="description"><?php esc_html_e( 'Scarica automaticamente i Google Fonts utilizzati dal tuo sito e servili localmente. Questo evita che l\'IP dei tuoi visitatori venga trasferito a Google (sentenza Tribunale di Monaco, 2022).', 'gdpr-press' ); ?></p>
            <?php endif; ?>

            <p style="margin:16px 0;">
                <button type="button" class="button button-secondary" id="gdpr-press-fonts-btn">
                    <?php echo GDPR_Press_Fonts::is_active()
                        ? esc_html__( 'Aggiorna font locali', 'gdpr-press' )
                        : esc_html__( 'Scarica Google Fonts', 'gdpr-press' ); ?>
                </button>
                <span id="gdpr-press-fonts-status" style="margin-left:12px;"></span>
            </p>

            <script>
            (function() {
                var nonce = '<?php echo esc_js( wp_create_nonce( 'gdpr_press_nonce' ) ); ?>';

                /* Scan cookies */
                var scanBtn = document.getElementById('gdpr-press-scan-btn');
                var scanStatus = document.getElementById('gdpr-press-scan-status');
                var scanResults = document.getElementById('gdpr-press-scan-results');
                var scanTbody = document.getElementById('gdpr-press-scan-tbody');

                if (scanBtn) {
                    scanBtn.addEventListener('click', function() {
                        scanBtn.disabled = true;
                        scanStatus.textContent = '<?php echo esc_js( __( 'Scansione in corso...', 'gdpr-press' ) ); ?>';
                        scanResults.style.display = 'none';
                        scanTbody.innerHTML = '';

                        var fd = new FormData();
                        fd.append('action', 'gdpr_press_scan_cookies');
                        fd.append('nonce', nonce);

                        fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                scanBtn.disabled = false;
                                if (data.success) {
                                    var cats = { necessary: '<?php echo esc_js( __( 'Necessario', 'gdpr-press' ) ); ?>', analytics: '<?php echo esc_js( __( 'Analitico', 'gdpr-press' ) ); ?>', marketing: '<?php echo esc_js( __( 'Marketing', 'gdpr-press' ) ); ?>', preferences: '<?php echo esc_js( __( 'Preferenza', 'gdpr-press' ) ); ?>' };
                                    scanStatus.textContent = data.data.count + ' cookie rilevati.';
                                    data.data.classified.forEach(function(c) {
                                        var tr = document.createElement('tr');
                                        var cat = c.info ? (cats[c.info.category] || c.info.category) : '—';
                                        var prov = c.info ? c.info.provider : '—';
                                        var st = c.known ? '✓ <?php echo esc_js( __( 'Riconosciuto', 'gdpr-press' ) ); ?>' : '⚠ <?php echo esc_js( __( 'Non classificato', 'gdpr-press' ) ); ?>';
                                        tr.innerHTML = '<td><code>' + c.name + '</code></td><td>' + cat + '</td><td>' + prov + '</td><td>' + st + '</td>';
                                        scanTbody.appendChild(tr);
                                    });
                                    scanResults.style.display = 'block';
                                } else {
                                    scanStatus.textContent = '<?php echo esc_js( __( 'Errore:', 'gdpr-press' ) ); ?> ' + (data.data || 'unknown');
                                }
                            })
                            .catch(function() {
                                scanBtn.disabled = false;
                                scanStatus.textContent = '<?php echo esc_js( __( 'Errore di rete.', 'gdpr-press' ) ); ?>';
                            });
                    });
                }

                /* Download Fonts */
                var fontsBtn = document.getElementById('gdpr-press-fonts-btn');
                var fontsStatus = document.getElementById('gdpr-press-fonts-status');

                if (fontsBtn) {
                    fontsBtn.addEventListener('click', function() {
                        fontsBtn.disabled = true;
                        fontsStatus.textContent = '<?php echo esc_js( __( 'Download in corso... (può richiedere qualche secondo)', 'gdpr-press' ) ); ?>';

                        var fd = new FormData();
                        fd.append('action', 'gdpr_press_download_fonts');
                        fd.append('nonce', nonce);

                        fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                fontsBtn.disabled = false;
                                fontsStatus.textContent = data.success ? data.data.message : ('<?php echo esc_js( __( 'Errore:', 'gdpr-press' ) ); ?> ' + (data.data || 'unknown'));
                                fontsStatus.style.color = data.success ? '#0f9b58' : '#e94560';
                            })
                            .catch(function() {
                                fontsBtn.disabled = false;
                                fontsStatus.textContent = '<?php echo esc_js( __( 'Errore di rete.', 'gdpr-press' ) ); ?>';
                                fontsStatus.style.color = '#e94560';
                            });
                    });
                }
            })();
            </script>

        </div>
        <?php
    }
}
