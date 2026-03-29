<?php
/**
 * Rendering del cookie banner nel frontend.
 *
 * - Banner primo livello (informativa breve)
 * - Pannello preferenze secondo livello (categorie granulari)
 * - Widget riapertura
 */

defined( 'ABSPATH' ) || exit;

class GDPR_Press_Banner {

    public static function init(): void {
        add_action( 'wp_footer', [ __CLASS__, 'render' ], 999 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 999 );
    }

    /* ── Enqueue assets ──────────────────────────────────────────── */

    public static function enqueue(): void {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_style(
            'gdpr-press-banner',
            GDPR_PRESS_URL . 'assets/css/banner' . $suffix . '.css',
            [],
            GDPR_PRESS_VERSION
        );

        wp_enqueue_script(
            'gdpr-press-banner',
            GDPR_PRESS_URL . 'assets/js/banner' . $suffix . '.js',
            [],
            GDPR_PRESS_VERSION,
            [ 'strategy' => 'defer', 'in_footer' => true ]
        );

        $options = gdpr_press_options();

        wp_localize_script( 'gdpr-press-banner', 'gdprPressConfig', [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'gdpr_press_nonce' ),
            'cookieName'    => 'gdpr_press_consent',
            'cidCookieName' => 'gdpr_press_cid',
            'expiry'        => absint( $options['consent_expiry'] ),
            'policyVersion' => get_option( 'gdpr_press_policy_version', '' ),
            'gcmEnabled'    => ! empty( $options['gcm_enabled'] ),
            'gcmUpdateJs'   => ! empty( $options['gcm_enabled'] ) ? GDPR_Press_GCM::get_update_js() : '',
        ] );

        // CSS custom per i colori del banner
        $css = self::generate_custom_css( $options );
        wp_add_inline_style( 'gdpr-press-banner', $css );
    }

    /* ── Render HTML del banner ──────────────────────────────────── */

    public static function render(): void {
        $options = gdpr_press_options();

        // Applica traduzioni WPML/Polylang
        if ( class_exists( 'GDPR_Press_I18n' ) ) {
            $options = GDPR_Press_I18n::translate_options( $options );
        }

        // Link alla cookie policy
        $policy_url = '';
        if ( ! empty( $options['policy_page'] ) ) {
            $policy_url = get_permalink( absint( $options['policy_page'] ) );
        }

        $position = $options['banner_position'] === 'top' ? 'top' : 'bottom';
        ?>

<!-- GDPR Press Cookie Banner -->
<div id="gdpr-press-banner" class="gdpr-press-banner gdpr-press-banner--<?php echo $position; ?>" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Gestione cookie', 'gdpr-press' ); ?>" hidden>
    <div class="gdpr-press-banner__inner">
        <div class="gdpr-press-banner__content">
            <p class="gdpr-press-banner__title"><?php echo esc_html( $options['banner_title'] ); ?></p>
            <p class="gdpr-press-banner__text">
                <?php echo esc_html( $options['banner_text'] ); ?>
                <?php if ( $policy_url ) : ?>
                    <a href="<?php echo esc_url( $policy_url ); ?>" class="gdpr-press-banner__link" target="_blank" rel="noopener"><?php esc_html_e( 'Cookie policy completa', 'gdpr-press' ); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <div class="gdpr-press-banner__actions">
            <button type="button" class="gdpr-press-btn gdpr-press-btn--accept" data-gdpr-action="accept_all"><?php echo esc_html( $options['accept_text'] ); ?></button>
            <button type="button" class="gdpr-press-btn gdpr-press-btn--reject" data-gdpr-action="reject_all"><?php echo esc_html( $options['reject_text'] ); ?></button>
            <button type="button" class="gdpr-press-btn gdpr-press-btn--customize" data-gdpr-action="customize"><?php echo esc_html( $options['customize_text'] ); ?></button>
        </div>
        <button type="button" class="gdpr-press-banner__close" data-gdpr-action="reject_all" aria-label="<?php esc_attr_e( 'Chiudi (equivale a rifiutare)', 'gdpr-press' ); ?>">&times;</button>
    </div>
</div>

<!-- GDPR Press Preferences Panel -->
<div id="gdpr-press-prefs" class="gdpr-press-prefs" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Preferenze cookie', 'gdpr-press' ); ?>" hidden>
    <div class="gdpr-press-prefs__overlay" data-gdpr-action="close_prefs"></div>
    <div class="gdpr-press-prefs__panel">
        <div class="gdpr-press-prefs__header">
            <h2 class="gdpr-press-prefs__title"><?php esc_html_e( 'Preferenze cookie', 'gdpr-press' ); ?></h2>
            <button type="button" class="gdpr-press-prefs__close" data-gdpr-action="close_prefs" aria-label="<?php esc_attr_e( 'Chiudi', 'gdpr-press' ); ?>">&times;</button>
        </div>
        <div class="gdpr-press-prefs__body">

            <!-- Necessari (sempre attivi) -->
            <div class="gdpr-press-prefs__category">
                <div class="gdpr-press-prefs__cat-header">
                    <label class="gdpr-press-prefs__cat-label">
                        <input type="checkbox" checked disabled>
                        <span><?php esc_html_e( 'Necessari', 'gdpr-press' ); ?></span>
                    </label>
                    <span class="gdpr-press-prefs__badge"><?php esc_html_e( 'Sempre attivi', 'gdpr-press' ); ?></span>
                </div>
                <p class="gdpr-press-prefs__cat-desc"><?php esc_html_e( 'Cookie indispensabili per il funzionamento del sito. Non possono essere disattivati.', 'gdpr-press' ); ?></p>
            </div>

            <!-- Analytics -->
            <div class="gdpr-press-prefs__category">
                <div class="gdpr-press-prefs__cat-header">
                    <label class="gdpr-press-prefs__cat-label">
                        <input type="checkbox" name="gdpr_analytics" data-gdpr-cat="analytics">
                        <span><?php echo esc_html( $options['cat_analytics_label'] ); ?></span>
                    </label>
                </div>
                <p class="gdpr-press-prefs__cat-desc"><?php echo esc_html( $options['cat_analytics_desc'] ); ?></p>
            </div>

            <!-- Marketing -->
            <div class="gdpr-press-prefs__category">
                <div class="gdpr-press-prefs__cat-header">
                    <label class="gdpr-press-prefs__cat-label">
                        <input type="checkbox" name="gdpr_marketing" data-gdpr-cat="marketing">
                        <span><?php echo esc_html( $options['cat_marketing_label'] ); ?></span>
                    </label>
                </div>
                <p class="gdpr-press-prefs__cat-desc"><?php echo esc_html( $options['cat_marketing_desc'] ); ?></p>
            </div>

            <!-- Preferenze -->
            <div class="gdpr-press-prefs__category">
                <div class="gdpr-press-prefs__cat-header">
                    <label class="gdpr-press-prefs__cat-label">
                        <input type="checkbox" name="gdpr_preferences" data-gdpr-cat="preferences">
                        <span><?php echo esc_html( $options['cat_preferences_label'] ); ?></span>
                    </label>
                </div>
                <p class="gdpr-press-prefs__cat-desc"><?php echo esc_html( $options['cat_preferences_desc'] ); ?></p>
            </div>

        </div>
        <div class="gdpr-press-prefs__footer">
            <button type="button" class="gdpr-press-btn gdpr-press-btn--accept" data-gdpr-action="accept_all"><?php echo esc_html( $options['accept_text'] ); ?></button>
            <button type="button" class="gdpr-press-btn gdpr-press-btn--reject" data-gdpr-action="reject_all"><?php echo esc_html( $options['reject_text'] ); ?></button>
            <button type="button" class="gdpr-press-btn gdpr-press-btn--save" data-gdpr-action="save_prefs"><?php echo esc_html( $options['save_text'] ); ?></button>
        </div>
    </div>
</div>

<?php if ( ! empty( $options['show_reopen_widget'] ) ) : ?>
<!-- GDPR Press Reopen Widget -->
<button type="button" id="gdpr-press-reopen" class="gdpr-press-reopen" aria-label="<?php esc_attr_e( 'Gestisci preferenze cookie', 'gdpr-press' ); ?>" hidden>
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/></svg>
</button>
<?php endif; ?>
<!-- /GDPR Press -->
        <?php
    }

    /* ── CSS dinamico per colori custom ──────────────────────────── */

    private static function generate_custom_css( array $options ): string {
        $bg      = sanitize_hex_color( $options['color_bg'] ) ?: '#1a1a2e';
        $text    = sanitize_hex_color( $options['color_text'] ) ?: '#ffffff';
        $accent  = sanitize_hex_color( $options['color_accent'] ) ?: '#e94560';
        $accept  = sanitize_hex_color( $options['color_accept'] ) ?: '#0f9b58';
        $reject  = sanitize_hex_color( $options['color_reject'] ) ?: '#5f6368';

        return ":root{"
            . "--gdpr-bg:{$bg};"
            . "--gdpr-text:{$text};"
            . "--gdpr-accent:{$accent};"
            . "--gdpr-accept:{$accept};"
            . "--gdpr-reject:{$reject};"
            . "}";
    }
}
