<?php
/**
 * Rendering del cookie banner nel frontend.
 *
 * - Banner primo livello (informativa breve)
 * - Pannello preferenze secondo livello (categorie granulari)
 * - Widget riapertura
 */

defined( 'ABSPATH' ) || exit;

class Scudo_Banner {

    public static function init(): void {
        add_action( 'wp_footer', [ __CLASS__, 'render' ], 999 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 999 );
    }

    /* ── Enqueue assets ──────────────────────────────────────────── */

    public static function enqueue(): void {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_style(
            'scudo-banner',
            SCUDO_URL . 'assets/css/banner' . $suffix . '.css',
            [],
            SCUDO_VERSION
        );

        wp_enqueue_script(
            'scudo-banner',
            SCUDO_URL . 'assets/js/banner' . $suffix . '.js',
            [],
            SCUDO_VERSION,
            [ 'strategy' => 'defer', 'in_footer' => true ]
        );

        $options = scudo_options();

        wp_localize_script( 'scudo-banner', 'gdprPressConfig', [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'scudo_nonce' ),
            'cookieName'    => 'scudo_consent',
            'cidCookieName' => 'scudo_cid',
            'expiry'        => absint( $options['consent_expiry'] ),
            'policyVersion' => get_option( 'scudo_policy_version', '' ),
            'gcmEnabled'    => ! empty( $options['gcm_enabled'] ),
            'gcmUpdateJs'   => ! empty( $options['gcm_enabled'] ) ? Scudo_GCM::get_update_js() : '',
        ] );

        // CSS custom per i colori del banner
        $css = self::generate_custom_css( $options );
        wp_add_inline_style( 'scudo-banner', $css );
    }

    /* ── Render HTML del banner ──────────────────────────────────── */

    public static function render(): void {
        $options = scudo_options();

        // Applica traduzioni WPML/Polylang
        if ( class_exists( 'Scudo_I18n' ) ) {
            $options = Scudo_I18n::translate_options( $options );
        }

        // Link alla cookie policy
        $policy_url = '';
        if ( ! empty( $options['policy_page'] ) ) {
            $policy_url = get_permalink( absint( $options['policy_page'] ) );
        }

        $position = $options['banner_position'] === 'top' ? 'top' : 'bottom';
        ?>

<!-- Scudo Cookie Banner -->
<div id="scudo-banner" class="scudo-banner scudo-banner--<?php echo $position; ?>" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( 'Gestione cookie', 'scudo' ); ?>" hidden>
    <div class="scudo-banner__inner">
        <div class="scudo-banner__content">
            <p class="scudo-banner__title"><?php echo esc_html( $options['banner_title'] ); ?></p>
            <p class="scudo-banner__text">
                <?php echo esc_html( $options['banner_text'] ); ?>
                <?php if ( $policy_url ) : ?>
                    <a href="<?php echo esc_url( $policy_url ); ?>" class="scudo-banner__link" target="_blank" rel="noopener"><?php esc_html_e( 'Cookie policy completa', 'scudo' ); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <div class="scudo-banner__actions">
            <button type="button" class="scudo-btn scudo-btn--accept" data-gdpr-action="accept_all"><?php echo esc_html( $options['accept_text'] ); ?></button>
            <button type="button" class="scudo-btn scudo-btn--reject" data-gdpr-action="reject_all"><?php echo esc_html( $options['reject_text'] ); ?></button>
            <button type="button" class="scudo-btn scudo-btn--customize" data-gdpr-action="customize"><?php echo esc_html( $options['customize_text'] ); ?></button>
        </div>
        <button type="button" class="scudo-banner__close" data-gdpr-action="reject_all" aria-label="<?php esc_attr_e( 'Chiudi (equivale a rifiutare)', 'scudo' ); ?>">&times;</button>
    </div>
</div>

<!-- Scudo Preferences Panel -->
<div id="scudo-prefs" class="scudo-prefs" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Preferenze cookie', 'scudo' ); ?>" hidden>
    <div class="scudo-prefs__overlay" data-gdpr-action="close_prefs"></div>
    <div class="scudo-prefs__panel">
        <div class="scudo-prefs__header">
            <h2 class="scudo-prefs__title"><?php esc_html_e( 'Preferenze cookie', 'scudo' ); ?></h2>
            <button type="button" class="scudo-prefs__close" data-gdpr-action="close_prefs" aria-label="<?php esc_attr_e( 'Chiudi', 'scudo' ); ?>">&times;</button>
        </div>
        <div class="scudo-prefs__body">

            <!-- Necessari (sempre attivi) -->
            <div class="scudo-prefs__category">
                <div class="scudo-prefs__cat-header">
                    <label class="scudo-prefs__cat-label">
                        <input type="checkbox" checked disabled>
                        <span><?php esc_html_e( 'Necessari', 'scudo' ); ?></span>
                    </label>
                    <span class="scudo-prefs__badge"><?php esc_html_e( 'Sempre attivi', 'scudo' ); ?></span>
                </div>
                <p class="scudo-prefs__cat-desc"><?php esc_html_e( 'Cookie indispensabili per il funzionamento del sito. Non possono essere disattivati.', 'scudo' ); ?></p>
            </div>

            <!-- Analytics -->
            <div class="scudo-prefs__category">
                <div class="scudo-prefs__cat-header">
                    <label class="scudo-prefs__cat-label">
                        <input type="checkbox" name="gdpr_analytics" data-gdpr-cat="analytics">
                        <span><?php echo esc_html( $options['cat_analytics_label'] ); ?></span>
                    </label>
                </div>
                <p class="scudo-prefs__cat-desc"><?php echo esc_html( $options['cat_analytics_desc'] ); ?></p>
            </div>

            <!-- Marketing -->
            <div class="scudo-prefs__category">
                <div class="scudo-prefs__cat-header">
                    <label class="scudo-prefs__cat-label">
                        <input type="checkbox" name="gdpr_marketing" data-gdpr-cat="marketing">
                        <span><?php echo esc_html( $options['cat_marketing_label'] ); ?></span>
                    </label>
                </div>
                <p class="scudo-prefs__cat-desc"><?php echo esc_html( $options['cat_marketing_desc'] ); ?></p>
            </div>

            <!-- Preferenze -->
            <div class="scudo-prefs__category">
                <div class="scudo-prefs__cat-header">
                    <label class="scudo-prefs__cat-label">
                        <input type="checkbox" name="gdpr_preferences" data-gdpr-cat="preferences">
                        <span><?php echo esc_html( $options['cat_preferences_label'] ); ?></span>
                    </label>
                </div>
                <p class="scudo-prefs__cat-desc"><?php echo esc_html( $options['cat_preferences_desc'] ); ?></p>
            </div>

        </div>
        <div class="scudo-prefs__footer">
            <button type="button" class="scudo-btn scudo-btn--accept" data-gdpr-action="accept_all"><?php echo esc_html( $options['accept_text'] ); ?></button>
            <button type="button" class="scudo-btn scudo-btn--reject" data-gdpr-action="reject_all"><?php echo esc_html( $options['reject_text'] ); ?></button>
            <button type="button" class="scudo-btn scudo-btn--save" data-gdpr-action="save_prefs"><?php echo esc_html( $options['save_text'] ); ?></button>
        </div>
    </div>
</div>

<?php if ( ! empty( $options['show_reopen_widget'] ) ) : ?>
<!-- Scudo Reopen Widget -->
<button type="button" id="scudo-reopen" class="scudo-reopen" aria-label="<?php esc_attr_e( 'Gestisci preferenze cookie', 'scudo' ); ?>" hidden>
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/></svg>
</button>
<?php endif; ?>
<!-- /Scudo -->
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
