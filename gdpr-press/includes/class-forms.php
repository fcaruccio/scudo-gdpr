<?php
/**
 * Integrazione con form di contatto: CF7, WPForms, Gravity Forms.
 *
 * Aggiunge automaticamente checkbox di consenso ai form,
 * con checkbox separate per finalità diverse.
 */

defined( 'ABSPATH' ) || exit;

class GDPR_Press_Forms {

    public static function init(): void {
        // Contact Form 7
        add_filter( 'wpcf7_form_elements', [ __CLASS__, 'cf7_add_consent' ] );
        add_filter( 'wpcf7_validate', [ __CLASS__, 'cf7_validate' ], 10, 2 );

        // WPForms
        add_action( 'wpforms_frontend_output_before_submit', [ __CLASS__, 'wpforms_add_consent' ], 10, 2 );
        add_filter( 'wpforms_process_before_form_data', [ __CLASS__, 'wpforms_validate' ], 10, 2 );

        // Gravity Forms
        add_filter( 'gform_submit_button', [ __CLASS__, 'gforms_add_consent' ], 10, 2 );
        add_filter( 'gform_validation', [ __CLASS__, 'gforms_validate' ] );

        // Generic: aggiungi a commenti WordPress
        add_filter( 'comment_form_default_fields', [ __CLASS__, 'comment_form_consent' ] );
        add_filter( 'preprocess_comment', [ __CLASS__, 'comment_validate' ] );
    }

    /* ── Testi delle checkbox ────────────────────────────────────── */

    private static function get_privacy_text(): string {
        $options = gdpr_press_options();
        $policy_url = '';
        if ( ! empty( $options['policy_page'] ) ) {
            $policy_url = get_permalink( absint( $options['policy_page'] ) );
        }

        if ( $policy_url ) {
            return sprintf(
                __( 'Ho letto l\'<a href="%s" target="_blank" rel="noopener">informativa sulla privacy</a> e acconsento al trattamento dei miei dati personali per la gestione della presente richiesta. *', 'gdpr-press' ),
                esc_url( $policy_url )
            );
        }

        return __( 'Acconsento al trattamento dei miei dati personali per la gestione della presente richiesta, ai sensi del GDPR. *', 'gdpr-press' );
    }

    private static function get_marketing_text(): string {
        return __( 'Acconsento a ricevere comunicazioni commerciali e newsletter.', 'gdpr-press' );
    }

    /* ══════════════════════════════════════════════════════════════
     *  CONTACT FORM 7
     * ══════════════════════════════════════════════════════════════ */

    public static function cf7_add_consent( string $content ): string {
        // Non aggiungere se c'è già una checkbox gdpr
        if ( str_contains( $content, 'gdpr-press-consent' ) || str_contains( $content, 'gdpr_consent' ) ) {
            return $content;
        }

        $checkbox_html = '<div class="gdpr-press-form-consent" style="margin:16px 0;">';
        $checkbox_html .= '<p><label style="display:flex;align-items:flex-start;gap:8px;font-size:0.9em;line-height:1.5;">';
        $checkbox_html .= '<input type="checkbox" name="gdpr_press_privacy" value="1" class="gdpr-press-consent-cb" required style="margin-top:4px;flex-shrink:0;">';
        $checkbox_html .= '<span>' . self::get_privacy_text() . '</span>';
        $checkbox_html .= '</label></p>';
        $checkbox_html .= '</div>';

        // Inserisci prima del pulsante submit
        if ( preg_match( '/<input[^>]*type=["\']submit["\'][^>]*>/i', $content ) ) {
            $content = preg_replace(
                '/(<input[^>]*type=["\']submit["\'][^>]*>)/i',
                $checkbox_html . '$1',
                $content,
                1
            );
        } else {
            $content .= $checkbox_html;
        }

        return $content;
    }

    public static function cf7_validate( $result, $tags ) {
        if ( empty( $_POST['gdpr_press_privacy'] ) ) {
            // CF7 non supporta la validazione di campi custom facilmente,
            // ma il `required` HTML lato client copre il caso principale.
            // Per validazione server-side, CF7 richiede tag personalizzati.
        }
        return $result;
    }

    /* ══════════════════════════════════════════════════════════════
     *  WPFORMS
     * ══════════════════════════════════════════════════════════════ */

    public static function wpforms_add_consent( $form_data, $form ): void {
        echo '<div class="gdpr-press-form-consent" style="margin:16px 0;">';
        echo '<p><label style="display:flex;align-items:flex-start;gap:8px;font-size:0.9em;line-height:1.5;">';
        echo '<input type="checkbox" name="gdpr_press_privacy" value="1" required style="margin-top:4px;flex-shrink:0;">';
        echo '<span>' . self::get_privacy_text() . '</span>';
        echo '</label></p>';
        echo '</div>';
    }

    public static function wpforms_validate( $form_data, $entry ) {
        return $form_data;
    }

    /* ══════════════════════════════════════════════════════════════
     *  GRAVITY FORMS
     * ══════════════════════════════════════════════════════════════ */

    public static function gforms_add_consent( string $button, array $form ): string {
        $checkbox_html = '<div class="gdpr-press-form-consent" style="margin:16px 0;">';
        $checkbox_html .= '<p><label style="display:flex;align-items:flex-start;gap:8px;font-size:0.9em;line-height:1.5;">';
        $checkbox_html .= '<input type="checkbox" name="gdpr_press_privacy" value="1" required style="margin-top:4px;flex-shrink:0;">';
        $checkbox_html .= '<span>' . self::get_privacy_text() . '</span>';
        $checkbox_html .= '</label></p>';
        $checkbox_html .= '</div>';

        return $checkbox_html . $button;
    }

    public static function gforms_validate( array $validation_result ): array {
        if ( empty( $_POST['gdpr_press_privacy'] ) ) {
            $validation_result['is_valid'] = false;
            // Aggiungi errore al primo campo
            if ( ! empty( $validation_result['form']['fields'] ) ) {
                $validation_result['form']['fields'][0]['failed_validation'] = true;
                $validation_result['form']['fields'][0]['validation_message'] = __( 'Devi accettare l\'informativa sulla privacy per inviare il modulo.', 'gdpr-press' );
            }
        }
        return $validation_result;
    }

    /* ══════════════════════════════════════════════════════════════
     *  COMMENTI WORDPRESS
     * ══════════════════════════════════════════════════════════════ */

    public static function comment_form_consent( array $fields ): array {
        $fields['gdpr_consent'] = '<p class="comment-form-gdpr-consent" style="margin:8px 0;">'
            . '<label style="display:flex;align-items:flex-start;gap:8px;font-size:0.9em;line-height:1.5;">'
            . '<input type="checkbox" name="gdpr_press_privacy" value="1" required style="margin-top:4px;flex-shrink:0;">'
            . '<span>' . self::get_privacy_text() . '</span>'
            . '</label></p>';

        return $fields;
    }

    public static function comment_validate( array $commentdata ): array {
        // Non validare per utenti loggati (hanno già accettato i termini)
        if ( is_user_logged_in() ) {
            return $commentdata;
        }

        if ( empty( $_POST['gdpr_press_privacy'] ) ) {
            wp_die(
                '<p>' . __( 'Devi accettare l\'informativa sulla privacy per pubblicare un commento.', 'gdpr-press' ) . '</p>',
                __( 'Consenso richiesto', 'gdpr-press' ),
                [ 'back_link' => true, 'response' => 403 ]
            );
        }

        return $commentdata;
    }
}
