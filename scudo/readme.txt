=== Scudo - GDPR Compliance Leggera ===
Contributors: francescocaruccio
Tags: gdpr, cookie, privacy, consent, cookie banner
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compliance GDPR leggera. Davvero. Cookie banner, blocco script, consenso granulare, Google Consent Mode v2, privacy policy wizard — tutto in 12KB.

== Description ==

**Scudo** mette il tuo sito WordPress a norma GDPR in 2 minuti.

Un wizard guidato ti chiede chi sei, cosa fa il tuo sito e quali servizi usi. Scudo fa il resto: crea le pagine Cookie Policy, Privacy Policy e Diritti GDPR, attiva il banner dei cookie e blocca tutti gli script di tracciamento prima del consenso.

= Perché Scudo? =

* **12KB totali** di CSS + JS — il banner più leggero sul mercato
* **Zero dipendenze** — vanilla JavaScript, niente jQuery, niente librerie esterne
* **Conforme al Garante Privacy italiano** — linee guida giugno 2021
* **Conforme al GDPR europeo** — Regolamento UE 2016/679
* **Google Consent Mode v2** — integrato, Basic mode
* **Setup in 2 minuti** — wizard guidato con autodetect

= Funzionalità =

* Cookie banner con pulsanti Accetta/Rifiuta a pari prominenza (requisito Garante)
* X di chiusura equivale a rifiuto (requisito Garante)
* Pannello preferenze granulare (necessari, analitici, marketing, preferenze)
* Blocco preventivo di 30+ servizi di tracciamento (Google Analytics, Facebook Pixel, YouTube, Maps, Hotjar, ecc.)
* Placeholder descrittivi per contenuti bloccati (video, mappe, widget social)
* Registro dei consensi nel database (prova legale per ispezioni)
* Scadenza consenso configurabile (default 6 mesi come da Garante)
* Google Consent Mode v2 con tutti i 7 parametri
* Scansione automatica dei cookie del sito
* Database integrato di 30+ cookie noti con descrizioni
* Shortcode `[scudo_cookie_table]` per la pagina Cookie Policy
* Wizard Privacy Policy conforme agli Artt. 13-14 GDPR
* Shortcode `[scudo_privacy_policy]` per rendering dinamico
* Self-hosting automatico di Google Fonts (risolve il problema della sentenza di Monaco 2022)
* Integrazione automatica con Contact Form 7, WPForms, Gravity Forms
* Checkbox consenso nei commenti WordPress
* Form esercizio diritti GDPR (accesso, rettifica, cancellazione, portabilità, opposizione)
* Integrazione con WordPress Privacy Tools (export/erase data)
* Notifiche email al titolare per richieste diritti
* Export registri consenso in CSV per audit
* Supporto multilingue (WPML, Polylang)
* Widget riapertura preferenze cookie
* Tema banner scuro/chiaro/personalizzato
* Accessibilità WCAG 2.1 AA (tastiera, screen reader, contrasto, focus management)
* Compatibilità con plugin di caching (bypass intelligente se consenso già dato)

= Conformità normativa =

Scudo è progettato per rispettare:

* **GDPR** — Regolamento UE 2016/679
* **Direttiva ePrivacy** — 2002/58/CE
* **Codice Privacy italiano** — D.Lgs. 196/2003 (aggiornato dal D.Lgs. 101/2018)
* **Linee guida Garante Privacy** — 10 giugno 2021
* **Google Consent Mode v2** — obbligatorio da marzo 2024

= Nessun dark pattern =

* Pulsanti Accetta e Rifiuta con la stessa dimensione e peso visivo
* Nessuna casella preselezionata
* Nessun scroll-as-consent
* Nessun cookie wall
* X di chiusura = rifiuto

== Installation ==

1. Carica la cartella `scudo` nella directory `/wp-content/plugins/`
2. Attiva il plugin dal menu "Plugin" di WordPress
3. Segui il wizard di configurazione guidata (appare automaticamente)
4. In alternativa, vai in Impostazioni → Scudo per la configurazione manuale

= Configurazione rapida (wizard) =

Il wizard ti guida in 5 step:

1. **Chi sei** — I tuoi dati aziendali (precompilati dal sito)
2. **Cosa fa il tuo sito** — Scudo rileva automaticamente i plugin installati
3. **Quali servizi usi** — Scudo scansiona il sito e pre-seleziona quelli trovati
4. **Scegli il tema** — Scuro o chiaro
5. **Attiva Scudo** — Crea automaticamente le pagine Cookie Policy, Privacy Policy e Diritti

= Shortcode disponibili =

* `[scudo_cookie_table]` — Tabella cookie per la Cookie Policy
* `[scudo_privacy_policy]` — Privacy Policy generata dal wizard
* `[scudo_rights_form]` — Form per esercitare i diritti GDPR

== Frequently Asked Questions ==

= Scudo rallenta il mio sito? =

No. Scudo pesa 12KB totali (CSS + JS) e non ha dipendenze esterne. Se il visitatore ha già espresso il consenso, l'output buffer non si attiva nemmeno — zero overhead.

= Funziona con i plugin di caching? =

Sì. Il consenso è gestito interamente lato client (cookie + JavaScript). L'output buffer si disattiva automaticamente quando il consenso è già stato dato.

= Devo configurare Google Consent Mode manualmente? =

No. Attiva l'opzione nelle impostazioni e Scudo gestisce tutto: imposta i default su "denied" e li aggiorna dopo il consenso.

= Posso usare Scudo con WPML o Polylang? =

Sì. Scudo registra automaticamente tutte le stringhe del banner per la traduzione.

= I Google Fonts vengono bloccati? =

Sì. Puoi anche scaricarli localmente con un click dalla tab Strumenti — così il tuo sito non trasferisce più l'IP dei visitatori a Google.

= Il plugin è conforme al Garante Privacy italiano? =

Scudo è stato progettato seguendo le linee guida del Garante del 10 giugno 2021. Pulsanti a pari prominenza, X = rifiuto, nessun dark pattern, scadenza 6 mesi, registro dei consensi.

= Cosa succede se disattivo il plugin? =

I registri dei consensi restano nel database (sono la tua prova legale). Le pagine create dal wizard restano pubblicate. Puoi riattivare il plugin in qualsiasi momento.

= Cosa succede se lo elimino completamente? =

Tutti i dati del plugin vengono rimossi: opzioni, tabelle del database e font scaricati.

== Screenshots ==

1. Cookie banner — tema scuro
2. Cookie banner — tema chiaro
3. Pannello preferenze granulare
4. Dashboard con statistiche consensi
5. Setup wizard — Step 1
6. Tab Impostazioni Banner
7. Scansione automatica cookie

== Changelog ==

= 1.0.0 =
* Prima release pubblica
* Cookie banner conforme Garante Privacy 2021
* Blocco preventivo 30+ servizi di tracciamento
* Google Consent Mode v2 (Basic mode)
* Setup wizard guidato con autodetect
* Privacy Policy wizard (Artt. 13-14 GDPR)
* Self-hosting Google Fonts
* Integrazione form (CF7, WPForms, Gravity Forms)
* Gestione diritti interessati (Artt. 15-22 GDPR)
* Export CSV registri consenso
* Supporto WPML/Polylang

== Upgrade Notice ==

= 1.0.0 =
Prima release. Installa e segui il wizard per mettere il tuo sito a norma in 2 minuti.
