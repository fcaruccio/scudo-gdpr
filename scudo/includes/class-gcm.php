<?php
/**
 * Google Consent Mode v2.
 *
 * Inietta lo snippet di default "denied" nel <head> PRIMA di qualsiasi tag Google.
 * Il JS del banner si occupa dell'update dopo il consenso.
 */

defined( 'ABSPATH' ) || exit;

class Scudo_GCM {

    public static function init(): void {
        $options = scudo_options();
        if ( empty( $options['gcm_enabled'] ) ) {
            return;
        }

        // Priorità 1: deve essere il primo script nel <head>
        add_action( 'wp_head', [ __CLASS__, 'render_default_consent' ], 1 );
    }

    /**
     * Inietta il default consent state.
     * Tutti i parametri su "denied" finché l'utente non acconsente.
     */
    public static function render_default_consent(): void {
        $consent = Scudo_Consent::get_current_consent();

        // Determina lo stato iniziale in base al consenso corrente
        $analytics_granted   = ( $consent && ! empty( $consent['analytics'] ) ) ? 'granted' : 'denied';
        $marketing_granted   = ( $consent && ! empty( $consent['marketing'] ) ) ? 'granted' : 'denied';
        $preferences_granted = ( $consent && ! empty( $consent['preferences'] ) ) ? 'granted' : 'denied';

        ?>
<script data-gdpr-category="necessary">
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
gtag('consent','default',{
'ad_storage':'<?php echo $marketing_granted; ?>',
'ad_user_data':'<?php echo $marketing_granted; ?>',
'ad_personalization':'<?php echo $marketing_granted; ?>',
'analytics_storage':'<?php echo $analytics_granted; ?>',
'functionality_storage':'<?php echo $preferences_granted; ?>',
'personalization_storage':'<?php echo $preferences_granted; ?>',
'security_storage':'granted',
'wait_for_update':500
});
</script>
        <?php
    }

    /**
     * Restituisce il JS per aggiornare il consenso GCM (chiamato dal banner JS).
     */
    public static function get_update_js(): string {
        return "function scudoGcmUpdate(c){if(typeof gtag!=='function')return;"
             . "gtag('consent','update',{"
             . "'ad_storage':c.marketing?'granted':'denied',"
             . "'ad_user_data':c.marketing?'granted':'denied',"
             . "'ad_personalization':c.marketing?'granted':'denied',"
             . "'analytics_storage':c.analytics?'granted':'denied',"
             . "'functionality_storage':c.preferences?'granted':'denied',"
             . "'personalization_storage':c.preferences?'granted':'denied'"
             . "});}";
    }
}
