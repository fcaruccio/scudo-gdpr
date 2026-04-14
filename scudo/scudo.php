<?php
/**
 * Plugin Name: Scudo Cookie & Privacy
 * Plugin URI:  https://github.com/fcaruccio/scudo-gdpr
 * Description: Compliance GDPR leggera. Davvero. Cookie banner, blocco script, consenso granulare, Google Consent Mode v2, privacy policy wizard — tutto in 12KB.
 * Version:     1.0.2
 * Author:      Velocia
 * Author URI:  https://velocia.it
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: scudo-cookie-privacy
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'SCUDO_VERSION', '1.0.2' );
define( 'SCUDO_FILE', __FILE__ );
define( 'SCUDO_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCUDO_URL', plugin_dir_url( __FILE__ ) );
define( 'SCUDO_BASENAME', plugin_basename( __FILE__ ) );

/* ── Autoload classi ─────────────────────────────────────────────── */

require_once SCUDO_DIR . 'includes/class-consent.php';
require_once SCUDO_DIR . 'includes/class-blocker.php';
require_once SCUDO_DIR . 'includes/class-banner.php';
require_once SCUDO_DIR . 'includes/class-gcm.php';
require_once SCUDO_DIR . 'includes/class-scanner.php';
require_once SCUDO_DIR . 'includes/class-fonts.php';
require_once SCUDO_DIR . 'includes/class-privacy-policy.php';
require_once SCUDO_DIR . 'includes/class-forms.php';
require_once SCUDO_DIR . 'includes/class-i18n.php';
require_once SCUDO_DIR . 'includes/class-rights.php';

if ( is_admin() ) {
    require_once SCUDO_DIR . 'admin/class-admin.php';
    require_once SCUDO_DIR . 'admin/class-wizard.php';
}

/* ── Attivazione / Disattivazione ────────────────────────────────── */

register_activation_hook( __FILE__, 'scudo_activate' );
register_deactivation_hook( __FILE__, [ 'Scudo_Consent', 'deactivate' ] );

function scudo_activate(): void {
    Scudo_Consent::activate();
    Scudo_Rights::create_table();
}

/* ── Bootstrap ───────────────────────────────────────────────────── */

add_action( 'plugins_loaded', 'scudo_init' );

function scudo_init(): void {
    // Inizializza solo nel frontend (non in admin, AJAX, REST, WP-CLI)
    if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        Scudo_GCM::init();
        Scudo_Blocker::init();
        Scudo_Banner::init();
    }

    Scudo_Consent::init();
    Scudo_Scanner::init();
    Scudo_Fonts::init();
    Scudo_Privacy_Policy::init();
    Scudo_Forms::init();
    Scudo_I18n::init();
    Scudo_Rights::init();

    // Export CSV handler
    if ( is_admin() && isset( $_GET['scudo_export'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'scudo_export_csv' ) && sanitize_text_field( wp_unslash( $_GET['scudo_export'] ) ) === 'csv' ) {
        add_action( 'admin_init', [ 'Scudo_Consent', 'export_csv' ] );
    }

    if ( is_admin() ) {
        Scudo_Admin::init();
        Scudo_Wizard::init();
    }
}

/* ── Helper: opzioni con default ─────────────────────────────────── */

function scudo_option( string $key, $default = null ) {
    $options = get_option( 'scudo_options', [] );
    return $options[ $key ] ?? $default;
}

function scudo_options(): array {
    return wp_parse_args( get_option( 'scudo_options', [] ), scudo_defaults() );
}

function scudo_defaults(): array {
    return [
        // Banner
        'banner_position'       => 'bottom',
        'banner_title'          => __( 'Questo sito utilizza i cookie', 'scudo-cookie-privacy' ),
        'banner_text'           => __( 'Utilizziamo cookie tecnici e, previo tuo consenso, cookie di profilazione e di terze parti per migliorare la tua esperienza di navigazione.', 'scudo-cookie-privacy' ),
        'accept_text'           => __( 'Accetta tutti', 'scudo-cookie-privacy' ),
        'reject_text'           => __( 'Rifiuta tutti', 'scudo-cookie-privacy' ),
        'customize_text'        => __( 'Personalizza', 'scudo-cookie-privacy' ),
        'save_text'             => __( 'Salva preferenze', 'scudo-cookie-privacy' ),
        'policy_page'           => 0,

        // Categorie cookie
        'cat_analytics_label'   => __( 'Analitici', 'scudo-cookie-privacy' ),
        'cat_analytics_desc'    => __( 'Cookie utilizzati per raccogliere statistiche anonime sulle visite al sito.', 'scudo-cookie-privacy' ),
        'cat_marketing_label'   => __( 'Marketing', 'scudo-cookie-privacy' ),
        'cat_marketing_desc'    => __( 'Cookie utilizzati per mostrarti annunci pertinenti ai tuoi interessi.', 'scudo-cookie-privacy' ),
        'cat_preferences_label' => __( 'Preferenze', 'scudo-cookie-privacy' ),
        'cat_preferences_desc'  => __( 'Cookie che memorizzano le tue preferenze di navigazione.', 'scudo-cookie-privacy' ),

        // Consenso
        'consent_expiry'        => 180, // giorni (6 mesi)
        'consent_logging'       => true,

        // Google Consent Mode v2
        'gcm_enabled'           => false,

        // Script da bloccare (pattern personalizzati, uno per riga)
        'custom_block_patterns' => '',

        // Tema e Colori
        'color_theme'           => 'dark', // dark | light | custom
        'color_bg'              => '#1a1a2e',
        'color_text'            => '#ffffff',
        'color_accent'          => '#e94560',
        'color_accept'          => '#ffffff',
        'color_reject'          => '#ffffff',

        // Widget riapertura
        'show_reopen_widget'    => true,
    ];
}
