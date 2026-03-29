<?php
/**
 * Supporto multilingue: WPML e Polylang.
 *
 * Registra tutte le stringhe del banner per la traduzione.
 */

defined( 'ABSPATH' ) || exit;

class Scudo_I18n {

    private const CONTEXT = 'Scudo';

    /**
     * Chiavi delle opzioni che contengono stringhe traducibili.
     */
    private const TRANSLATABLE_KEYS = [
        'banner_title',
        'banner_text',
        'accept_text',
        'reject_text',
        'customize_text',
        'save_text',
        'cat_analytics_label',
        'cat_analytics_desc',
        'cat_marketing_label',
        'cat_marketing_desc',
        'cat_preferences_label',
        'cat_preferences_desc',
    ];

    public static function init(): void {
        // Registra stringhe quando le opzioni vengono salvate
        add_action( 'update_option_scudo_options', [ __CLASS__, 'register_strings' ], 10, 2 );

        // Registra stringhe all'init (per la prima volta)
        add_action( 'init', [ __CLASS__, 'register_strings_on_init' ], 20 );
    }

    /* ── Registra stringhe per WPML / Polylang ───────────────────── */

    public static function register_strings_on_init(): void {
        $options = scudo_options();
        self::do_register( $options );
    }

    public static function register_strings( $old_value, $new_value ): void {
        if ( is_array( $new_value ) ) {
            self::do_register( $new_value );
        }
    }

    private static function do_register( array $options ): void {
        // WPML String Translation
        if ( function_exists( 'icl_register_string' ) ) {
            foreach ( self::TRANSLATABLE_KEYS as $key ) {
                if ( ! empty( $options[ $key ] ) ) {
                    icl_register_string( self::CONTEXT, $key, $options[ $key ] );
                }
            }
        }

        // Polylang
        if ( function_exists( 'pll_register_string' ) ) {
            foreach ( self::TRANSLATABLE_KEYS as $key ) {
                if ( ! empty( $options[ $key ] ) ) {
                    $label = str_replace( '_', ' ', ucfirst( $key ) );
                    pll_register_string( $label, $options[ $key ], self::CONTEXT );
                }
            }
        }
    }

    /* ── Traduci una stringa ─────────────────────────────────────── */

    public static function translate( string $key, string $value ): string {
        // WPML
        if ( function_exists( 'icl_t' ) ) {
            return icl_t( self::CONTEXT, $key, $value );
        }

        // Polylang
        if ( function_exists( 'pll__' ) ) {
            return pll__( $value );
        }

        return $value;
    }

    /**
     * Applica la traduzione a tutte le opzioni traducibili.
     */
    public static function translate_options( array $options ): array {
        foreach ( self::TRANSLATABLE_KEYS as $key ) {
            if ( ! empty( $options[ $key ] ) ) {
                $options[ $key ] = self::translate( $key, $options[ $key ] );
            }
        }
        return $options;
    }
}
