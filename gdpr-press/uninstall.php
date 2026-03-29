<?php
/**
 * GDPR Press — Uninstall
 *
 * Rimuove tutte le opzioni, le tabelle e i file del plugin quando viene eliminato.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Rimuovi opzioni
delete_option( 'gdpr_press_options' );
delete_option( 'gdpr_press_policy_version' );
delete_option( 'gdpr_press_db_version' );
delete_option( 'gdpr_press_detected_cookies' );
delete_option( 'gdpr_press_custom_cookies' );
delete_option( 'gdpr_press_fonts_map' );
delete_option( 'gdpr_press_privacy_data' );

// Rimuovi tabelle
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gdpr_press_consent_log" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gdpr_press_rights_requests" );

// Rimuovi font scaricati
$upload_dir = wp_upload_dir();
$fonts_dir  = $upload_dir['basedir'] . '/gdpr-press-fonts';
if ( is_dir( $fonts_dir ) ) {
    $files = glob( $fonts_dir . '/*' );
    if ( $files ) {
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                wp_delete_file( $file );
            }
        }
    }
    rmdir( $fonts_dir );
}
