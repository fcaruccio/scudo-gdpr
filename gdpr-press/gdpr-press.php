<?php
/**
 * Plugin Name: GDPR Press
 * Plugin URI:  https://github.com/francescocaruccio/gdpr-press
 * Description: Plugin leggero per la conformità GDPR italiana ed europea. Cookie banner, blocco preventivo script, gestione consenso, Google Consent Mode v2.
 * Version:     1.0.0
 * Author:      Francesco Caruccio
 * Author URI:  https://francescocaruccio.it
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gdpr-press
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'GDPR_PRESS_VERSION', '1.0.0' );
define( 'GDPR_PRESS_FILE', __FILE__ );
define( 'GDPR_PRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'GDPR_PRESS_URL', plugin_dir_url( __FILE__ ) );
define( 'GDPR_PRESS_BASENAME', plugin_basename( __FILE__ ) );

/* ── Autoload classi ─────────────────────────────────────────────── */

require_once GDPR_PRESS_DIR . 'includes/class-consent.php';
require_once GDPR_PRESS_DIR . 'includes/class-blocker.php';
require_once GDPR_PRESS_DIR . 'includes/class-banner.php';
require_once GDPR_PRESS_DIR . 'includes/class-gcm.php';
require_once GDPR_PRESS_DIR . 'includes/class-scanner.php';
require_once GDPR_PRESS_DIR . 'includes/class-fonts.php';
require_once GDPR_PRESS_DIR . 'includes/class-privacy-policy.php';
require_once GDPR_PRESS_DIR . 'includes/class-forms.php';
require_once GDPR_PRESS_DIR . 'includes/class-i18n.php';
require_once GDPR_PRESS_DIR . 'includes/class-rights.php';

if ( is_admin() ) {
    require_once GDPR_PRESS_DIR . 'admin/class-admin.php';
}

/* ── Attivazione / Disattivazione ────────────────────────────────── */

register_activation_hook( __FILE__, 'gdpr_press_activate' );
register_deactivation_hook( __FILE__, [ 'GDPR_Press_Consent', 'deactivate' ] );

function gdpr_press_activate(): void {
    GDPR_Press_Consent::activate();
    GDPR_Press_Rights::create_table();
}

/* ── Bootstrap ───────────────────────────────────────────────────── */

add_action( 'plugins_loaded', 'gdpr_press_init' );

function gdpr_press_init(): void {
    load_plugin_textdomain( 'gdpr-press', false, dirname( GDPR_PRESS_BASENAME ) . '/languages' );

    // Inizializza solo nel frontend (non in admin, AJAX, REST, WP-CLI)
    if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        GDPR_Press_GCM::init();
        GDPR_Press_Blocker::init();
        GDPR_Press_Banner::init();
    }

    GDPR_Press_Consent::init();
    GDPR_Press_Scanner::init();
    GDPR_Press_Fonts::init();
    GDPR_Press_Privacy_Policy::init();
    GDPR_Press_Forms::init();
    GDPR_Press_I18n::init();
    GDPR_Press_Rights::init();

    // Export CSV handler
    if ( is_admin() && isset( $_GET['gdpr_press_export'] ) && $_GET['gdpr_press_export'] === 'csv' ) {
        add_action( 'admin_init', [ 'GDPR_Press_Consent', 'export_csv' ] );
    }

    if ( is_admin() ) {
        GDPR_Press_Admin::init();
    }
}

/* ── Helper: opzioni con default ─────────────────────────────────── */

function gdpr_press_option( string $key, $default = null ) {
    $options = get_option( 'gdpr_press_options', [] );
    return $options[ $key ] ?? $default;
}

function gdpr_press_options(): array {
    return wp_parse_args( get_option( 'gdpr_press_options', [] ), gdpr_press_defaults() );
}

function gdpr_press_defaults(): array {
    return [
        // Banner
        'banner_position'       => 'bottom',
        'banner_title'          => __( 'Questo sito utilizza i cookie', 'gdpr-press' ),
        'banner_text'           => __( 'Utilizziamo cookie tecnici e, previo tuo consenso, cookie di profilazione e di terze parti per migliorare la tua esperienza di navigazione.', 'gdpr-press' ),
        'accept_text'           => __( 'Accetta tutti', 'gdpr-press' ),
        'reject_text'           => __( 'Rifiuta tutti', 'gdpr-press' ),
        'customize_text'        => __( 'Personalizza', 'gdpr-press' ),
        'save_text'             => __( 'Salva preferenze', 'gdpr-press' ),
        'policy_page'           => 0,

        // Categorie cookie
        'cat_analytics_label'   => __( 'Analitici', 'gdpr-press' ),
        'cat_analytics_desc'    => __( 'Cookie utilizzati per raccogliere statistiche anonime sulle visite al sito.', 'gdpr-press' ),
        'cat_marketing_label'   => __( 'Marketing', 'gdpr-press' ),
        'cat_marketing_desc'    => __( 'Cookie utilizzati per mostrarti annunci pertinenti ai tuoi interessi.', 'gdpr-press' ),
        'cat_preferences_label' => __( 'Preferenze', 'gdpr-press' ),
        'cat_preferences_desc'  => __( 'Cookie che memorizzano le tue preferenze di navigazione.', 'gdpr-press' ),

        // Consenso
        'consent_expiry'        => 180, // giorni (6 mesi)
        'consent_logging'       => true,

        // Google Consent Mode v2
        'gcm_enabled'           => false,

        // Script da bloccare (pattern personalizzati, uno per riga)
        'custom_block_patterns' => '',

        // Colori
        'color_bg'              => '#1a1a2e',
        'color_text'            => '#ffffff',
        'color_accent'          => '#e94560',
        'color_accept'          => '#0f9b58',
        'color_reject'          => '#5f6368',

        // Widget riapertura
        'show_reopen_widget'    => true,
    ];
}
