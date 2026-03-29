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

    public static function add_menu(): void {
        add_options_page(
            __( 'Scudo', 'scudo' ),
            __( 'Scudo', 'scudo' ),
            'manage_options',
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function action_links( array $links ): array {
        $url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
        array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Impostazioni', 'scudo' ) . '</a>' );
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
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Scudo — Impostazioni', 'scudo' ); ?></h1>

            <!-- Statistiche consensi -->
            <div class="scudo-admin-stats" style="display:flex;gap:16px;margin:20px 0;flex-wrap:wrap;">
                <?php
                $stat_items = [
                    [ 'label' => __( 'Totale consensi (30gg)', 'scudo' ), 'value' => $stats['total'], 'color' => '#2271b1' ],
                    [ 'label' => __( 'Accetta tutti', 'scudo' ), 'value' => $stats['accept_all'], 'color' => '#0f9b58' ],
                    [ 'label' => __( 'Rifiuta tutti', 'scudo' ), 'value' => $stats['reject_all'], 'color' => '#e94560' ],
                    [ 'label' => __( 'Personalizzati', 'scudo' ), 'value' => $stats['custom'], 'color' => '#f59e0b' ],
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
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&scudo_export=csv' ), 'scudo_export_csv' ) ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'Esporta registri consenso (CSV)', 'scudo' ); ?>
                </a>
            </p>

            <?php
            // Richieste diritti degli interessati
            $requests = class_exists( 'Scudo_Rights' ) ? Scudo_Rights::get_requests( 10 ) : [];
            if ( ! empty( $requests ) ) :
            ?>
            <h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'Ultime richieste diritti GDPR', 'scudo' ); ?></h2>
            <table class="widefat striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Data', 'scudo' ); ?></th>
                        <th><?php esc_html_e( 'Tipo', 'scudo' ); ?></th>
                        <th><?php esc_html_e( 'Nome', 'scudo' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'scudo' ); ?></th>
                        <th><?php esc_html_e( 'Stato', 'scudo' ); ?></th>
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
                                <span style="color:#f59e0b;font-weight:600;"><?php esc_html_e( 'In attesa', 'scudo' ); ?></span>
                            <?php else : ?>
                                <span style="color:#0f9b58;font-weight:600;"><?php esc_html_e( 'Completata', 'scudo' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description"><?php esc_html_e( 'Per far esercitare i diritti agli utenti, usa lo shortcode [scudo_rights_form] in una pagina.', 'scudo' ); ?></p>
            <?php else : ?>
            <p class="description" style="margin-top:16px;">
                <?php esc_html_e( 'Per far esercitare i diritti agli utenti, usa lo shortcode [scudo_rights_form] in una pagina.', 'scudo' ); ?>
            </p>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( self::OPTION_GROUP ); ?>

                <!-- Banner -->
                <h2 class="title"><?php esc_html_e( 'Banner', 'scudo' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="banner_position"><?php esc_html_e( 'Posizione', 'scudo' ); ?></label></th>
                        <td>
                            <select name="scudo_options[banner_position]" id="banner_position">
                                <option value="bottom" <?php selected( $options['banner_position'], 'bottom' ); ?>><?php esc_html_e( 'In basso', 'scudo' ); ?></option>
                                <option value="top" <?php selected( $options['banner_position'], 'top' ); ?>><?php esc_html_e( 'In alto', 'scudo' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="banner_title"><?php esc_html_e( 'Titolo', 'scudo' ); ?></label></th>
                        <td><input type="text" name="scudo_options[banner_title]" id="banner_title" value="<?php echo esc_attr( $options['banner_title'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="banner_text"><?php esc_html_e( 'Testo', 'scudo' ); ?></label></th>
                        <td><textarea name="scudo_options[banner_text]" id="banner_text" rows="3" class="large-text"><?php echo esc_textarea( $options['banner_text'] ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="accept_text"><?php esc_html_e( 'Testo "Accetta"', 'scudo' ); ?></label></th>
                        <td><input type="text" name="scudo_options[accept_text]" id="accept_text" value="<?php echo esc_attr( $options['accept_text'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="reject_text"><?php esc_html_e( 'Testo "Rifiuta"', 'scudo' ); ?></label></th>
                        <td><input type="text" name="scudo_options[reject_text]" id="reject_text" value="<?php echo esc_attr( $options['reject_text'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="customize_text"><?php esc_html_e( 'Testo "Personalizza"', 'scudo' ); ?></label></th>
                        <td><input type="text" name="scudo_options[customize_text]" id="customize_text" value="<?php echo esc_attr( $options['customize_text'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="save_text"><?php esc_html_e( 'Testo "Salva preferenze"', 'scudo' ); ?></label></th>
                        <td><input type="text" name="scudo_options[save_text]" id="save_text" value="<?php echo esc_attr( $options['save_text'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="policy_page"><?php esc_html_e( 'Pagina Cookie Policy', 'scudo' ); ?></label></th>
                        <td>
                            <?php wp_dropdown_pages( [
                                'name'              => 'scudo_options[policy_page]',
                                'id'                => 'policy_page',
                                'selected'          => $options['policy_page'],
                                'show_option_none'  => __( '— Seleziona —', 'scudo' ),
                                'option_none_value' => 0,
                            ] ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Widget riapertura', 'scudo' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="scudo_options[show_reopen_widget]" value="1" <?php checked( $options['show_reopen_widget'] ); ?>>
                            <?php esc_html_e( 'Mostra icona per riaprire le preferenze cookie', 'scudo' ); ?></label>
                        </td>
                    </tr>
                </table>

                <!-- Categorie Cookie -->
                <h2 class="title"><?php esc_html_e( 'Categorie Cookie', 'scudo' ); ?></h2>
                <table class="form-table">
                    <?php
                    $cats = [
                        'analytics'   => __( 'Analitici', 'scudo' ),
                        'marketing'   => __( 'Marketing', 'scudo' ),
                        'preferences' => __( 'Preferenze', 'scudo' ),
                    ];
                    foreach ( $cats as $slug => $default_label ) : ?>
                    <tr>
                        <th scope="row"><label for="cat_<?php echo $slug; ?>_label"><?php echo esc_html( $default_label ); ?> — <?php esc_html_e( 'Nome', 'scudo' ); ?></label></th>
                        <td><input type="text" name="scudo_options[cat_<?php echo $slug; ?>_label]" id="cat_<?php echo $slug; ?>_label" value="<?php echo esc_attr( $options[ 'cat_' . $slug . '_label' ] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cat_<?php echo $slug; ?>_desc"><?php echo esc_html( $default_label ); ?> — <?php esc_html_e( 'Descrizione', 'scudo' ); ?></label></th>
                        <td><textarea name="scudo_options[cat_<?php echo $slug; ?>_desc]" id="cat_<?php echo $slug; ?>_desc" rows="2" class="large-text"><?php echo esc_textarea( $options[ 'cat_' . $slug . '_desc' ] ); ?></textarea></td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <!-- Consenso -->
                <h2 class="title"><?php esc_html_e( 'Consenso', 'scudo' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="consent_expiry"><?php esc_html_e( 'Durata consenso (giorni)', 'scudo' ); ?></label></th>
                        <td>
                            <input type="number" name="scudo_options[consent_expiry]" id="consent_expiry" value="<?php echo esc_attr( $options['consent_expiry'] ); ?>" min="1" max="365" class="small-text">
                            <p class="description"><?php esc_html_e( 'Il Garante Privacy consiglia 180 giorni (6 mesi).', 'scudo' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Registrazione consensi', 'scudo' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="scudo_options[consent_logging]" value="1" <?php checked( $options['consent_logging'] ); ?>>
                            <?php esc_html_e( 'Registra i consensi nel database (prova legale)', 'scudo' ); ?></label>
                        </td>
                    </tr>
                </table>

                <!-- Google Consent Mode v2 -->
                <h2 class="title"><?php esc_html_e( 'Google Consent Mode v2', 'scudo' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Attiva GCM v2', 'scudo' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="scudo_options[gcm_enabled]" value="1" <?php checked( $options['gcm_enabled'] ); ?>>
                            <?php esc_html_e( 'Abilita Google Consent Mode v2 (Basic mode)', 'scudo' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Imposta automaticamente i default su "denied" e li aggiorna dopo il consenso. Richiesto per Google Ads e GA4 nell\'EEA.', 'scudo' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- Blocco Script -->
                <h2 class="title"><?php esc_html_e( 'Blocco Script', 'scudo' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="custom_block_patterns"><?php esc_html_e( 'Pattern personalizzati', 'scudo' ); ?></label></th>
                        <td>
                            <textarea name="scudo_options[custom_block_patterns]" id="custom_block_patterns" rows="5" class="large-text code"><?php echo esc_textarea( $options['custom_block_patterns'] ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Un pattern per riga. Gli script che contengono questi pattern verranno bloccati come "marketing". Es: tracking.example.com', 'scudo' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- Colori -->
                <h2 class="title"><?php esc_html_e( 'Colori', 'scudo' ); ?></h2>
                <table class="form-table">
                    <?php
                    $colors = [
                        'color_bg'     => __( 'Sfondo banner', 'scudo' ),
                        'color_text'   => __( 'Testo banner', 'scudo' ),
                        'color_accent' => __( 'Accento (link, focus)', 'scudo' ),
                        'color_accept' => __( 'Pulsante Accetta', 'scudo' ),
                        'color_reject' => __( 'Pulsante Rifiuta', 'scudo' ),
                    ];
                    foreach ( $colors as $key => $label ) : ?>
                    <tr>
                        <th scope="row"><label for="<?php echo $key; ?>"><?php echo esc_html( $label ); ?></label></th>
                        <td><input type="color" name="scudo_options[<?php echo $key; ?>]" id="<?php echo $key; ?>" value="<?php echo esc_attr( $options[ $key ] ); ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button( __( 'Salva impostazioni', 'scudo' ) ); ?>
            </form>

            <hr>

            <!-- Scansione Cookie -->
            <h2 class="title"><?php esc_html_e( 'Scansione Cookie', 'scudo' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Scansiona il tuo sito per rilevare automaticamente i cookie impostati da script e servizi di terze parti. I risultati verranno utilizzati per popolare la tabella dei cookie nella cookie policy.', 'scudo' ); ?></p>

            <p style="margin:16px 0;">
                <button type="button" class="button button-secondary" id="scudo-scan-btn">
                    <?php esc_html_e( 'Scansiona cookie', 'scudo' ); ?>
                </button>
                <span id="scudo-scan-status" style="margin-left:12px;"></span>
            </p>

            <div id="scudo-scan-results" style="display:none;">
                <table class="widefat striped" style="max-width:800px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Cookie', 'scudo' ); ?></th>
                            <th><?php esc_html_e( 'Categoria', 'scudo' ); ?></th>
                            <th><?php esc_html_e( 'Fornitore', 'scudo' ); ?></th>
                            <th><?php esc_html_e( 'Stato', 'scudo' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="scudo-scan-tbody"></tbody>
                </table>
            </div>

            <hr>

            <!-- Shortcode info -->
            <h2 class="title"><?php esc_html_e( 'Cookie Policy', 'scudo' ); ?></h2>
            <p><?php esc_html_e( 'Usa questo shortcode nella pagina della cookie policy per mostrare automaticamente la tabella dei cookie:', 'scudo' ); ?></p>
            <p><code>[scudo_cookie_table]</code></p>
            <p class="description"><?php esc_html_e( 'La tabella mostra tutti i cookie rilevati dalla scansione, organizzati per categoria, con nome, fornitore, durata e descrizione.', 'scudo' ); ?></p>

            <?php Scudo_Privacy_Policy::render_wizard(); ?>

            <hr>

            <!-- Google Fonts Self-Hosting -->
            <h2 class="title"><?php esc_html_e( 'Google Fonts — Self-Hosting', 'scudo' ); ?></h2>
            <?php if ( Scudo_Fonts::is_active() ) : ?>
                <p style="color:#0f9b58;font-weight:600;"><?php esc_html_e( 'I Google Fonts sono attualmente serviti dal tuo server. Nessun dato viene inviato a Google.', 'scudo' ); ?></p>
            <?php else : ?>
                <p class="description"><?php esc_html_e( 'Scarica automaticamente i Google Fonts utilizzati dal tuo sito e servili localmente. Questo evita che l\'IP dei tuoi visitatori venga trasferito a Google (sentenza Tribunale di Monaco, 2022).', 'scudo' ); ?></p>
            <?php endif; ?>

            <p style="margin:16px 0;">
                <button type="button" class="button button-secondary" id="scudo-fonts-btn">
                    <?php echo Scudo_Fonts::is_active()
                        ? esc_html__( 'Aggiorna font locali', 'scudo' )
                        : esc_html__( 'Scarica Google Fonts', 'scudo' ); ?>
                </button>
                <span id="scudo-fonts-status" style="margin-left:12px;"></span>
            </p>

            <script>
            (function() {
                var nonce = '<?php echo esc_js( wp_create_nonce( 'scudo_nonce' ) ); ?>';

                /* Scan cookies */
                var scanBtn = document.getElementById('scudo-scan-btn');
                var scanStatus = document.getElementById('scudo-scan-status');
                var scanResults = document.getElementById('scudo-scan-results');
                var scanTbody = document.getElementById('scudo-scan-tbody');

                if (scanBtn) {
                    scanBtn.addEventListener('click', function() {
                        scanBtn.disabled = true;
                        scanStatus.textContent = '<?php echo esc_js( __( 'Scansione in corso...', 'scudo' ) ); ?>';
                        scanResults.style.display = 'none';
                        scanTbody.innerHTML = '';

                        var fd = new FormData();
                        fd.append('action', 'scudo_scan_cookies');
                        fd.append('nonce', nonce);

                        fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                scanBtn.disabled = false;
                                if (data.success) {
                                    var cats = { necessary: '<?php echo esc_js( __( 'Necessario', 'scudo' ) ); ?>', analytics: '<?php echo esc_js( __( 'Analitico', 'scudo' ) ); ?>', marketing: '<?php echo esc_js( __( 'Marketing', 'scudo' ) ); ?>', preferences: '<?php echo esc_js( __( 'Preferenza', 'scudo' ) ); ?>' };
                                    scanStatus.textContent = data.data.count + ' cookie rilevati.';
                                    data.data.classified.forEach(function(c) {
                                        var tr = document.createElement('tr');
                                        var cat = c.info ? (cats[c.info.category] || c.info.category) : '—';
                                        var prov = c.info ? c.info.provider : '—';
                                        var st = c.known ? '✓ <?php echo esc_js( __( 'Riconosciuto', 'scudo' ) ); ?>' : '⚠ <?php echo esc_js( __( 'Non classificato', 'scudo' ) ); ?>';
                                        tr.innerHTML = '<td><code>' + c.name + '</code></td><td>' + cat + '</td><td>' + prov + '</td><td>' + st + '</td>';
                                        scanTbody.appendChild(tr);
                                    });
                                    scanResults.style.display = 'block';
                                } else {
                                    scanStatus.textContent = '<?php echo esc_js( __( 'Errore:', 'scudo' ) ); ?> ' + (data.data || 'unknown');
                                }
                            })
                            .catch(function() {
                                scanBtn.disabled = false;
                                scanStatus.textContent = '<?php echo esc_js( __( 'Errore di rete.', 'scudo' ) ); ?>';
                            });
                    });
                }

                /* Download Fonts */
                var fontsBtn = document.getElementById('scudo-fonts-btn');
                var fontsStatus = document.getElementById('scudo-fonts-status');

                if (fontsBtn) {
                    fontsBtn.addEventListener('click', function() {
                        fontsBtn.disabled = true;
                        fontsStatus.textContent = '<?php echo esc_js( __( 'Download in corso... (può richiedere qualche secondo)', 'scudo' ) ); ?>';

                        var fd = new FormData();
                        fd.append('action', 'scudo_download_fonts');
                        fd.append('nonce', nonce);

                        fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                fontsBtn.disabled = false;
                                fontsStatus.textContent = data.success ? data.data.message : ('<?php echo esc_js( __( 'Errore:', 'scudo' ) ); ?> ' + (data.data || 'unknown'));
                                fontsStatus.style.color = data.success ? '#0f9b58' : '#e94560';
                            })
                            .catch(function() {
                                fontsBtn.disabled = false;
                                fontsStatus.textContent = '<?php echo esc_js( __( 'Errore di rete.', 'scudo' ) ); ?>';
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
