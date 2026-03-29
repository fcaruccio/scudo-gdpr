<?php
/**
 * Scudo — Uninstall
 *
 * Rimuove tutte le opzioni, le tabelle e i file del plugin quando viene eliminato.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Rimuovi opzioni
delete_option( 'scudo_options' );
delete_option( 'scudo_policy_version' );
delete_option( 'scudo_db_version' );
delete_option( 'scudo_detected_cookies' );
delete_option( 'scudo_custom_cookies' );
delete_option( 'scudo_fonts_map' );
delete_option( 'scudo_privacy_data' );

// Rimuovi tabelle
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}scudo_consent_log" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}scudo_rights_requests" );

// Rimuovi font scaricati
$upload_dir = wp_upload_dir();
$fonts_dir  = $upload_dir['basedir'] . '/scudo-fonts';
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
