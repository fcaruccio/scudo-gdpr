# Scudo — Plugin GDPR per WordPress

**Scudo** è un plugin WordPress gratuito e open-source per la conformità al GDPR e alle linee guida del Garante Privacy italiano (giugno 2021).

Leggero (12 KB totali tra CSS e JS), senza dipendenze esterne, senza jQuery. Funziona con qualsiasi tema WordPress.

> **Stato:** in fase di approvazione sulla directory ufficiale WordPress.org. Nel frattempo puoi scaricarlo da qui e installarlo manualmente.

---

## Installazione

1. Vai nella sezione [Releases](../../releases) di questo repository
2. Scarica il file `scudo-1.0.0.zip`
3. Nel tuo WordPress, vai su **Plugin → Aggiungi nuovo → Carica plugin**
4. Seleziona il file zip scaricato e clicca **Installa ora**
5. Attiva il plugin
6. Segui il wizard di configurazione guidata che apparirà automaticamente

In alternativa, puoi scaricare il codice sorgente e copiare la cartella `scudo/` dentro `wp-content/plugins/`.

---

## Cosa fa Scudo

### Banner dei cookie

Mostra un banner conforme alle linee guida del Garante Privacy italiano del 2021:

- **Tre pulsanti con uguale peso visivo**: "Accetta tutti", "Rifiuta tutti" e "Personalizza" — nessun dark pattern
- **La X chiude il banner e vale come rifiuto**, come richiesto dal Garante
- **Pannello preferenze granulare** con 4 categorie di cookie:
  - **Necessari** (sempre attivi, non disattivabili)
  - **Analitici** (Google Analytics, Matomo, Hotjar, Clarity, ecc.)
  - **Marketing** (Facebook Pixel, Google Ads, LinkedIn, TikTok, ecc.)
  - **Preferenze** (cookie funzionali)
- **Nessuna casella pre-selezionata**: tutti i cookie non necessari partono da "off"
- **Lo scroll non vale come consenso**: serve un clic esplicito
- **Widget per riaprire le preferenze** sempre accessibile (icona cookie persistente)
- **Tema scuro, chiaro o personalizzato** con color picker

### Blocco preventivo degli script

Scudo blocca automaticamente gli script di tracciamento **prima** che vengano eseguiti, finché l'utente non dà il consenso. Sono riconosciuti oltre 30 servizi:

- **Analytics:** Google Analytics/GA4, Google Tag Manager, Matomo, Hotjar, Microsoft Clarity, Plausible
- **Marketing:** Facebook Pixel, Google Ads, DoubleClick, LinkedIn Insight, TikTok Pixel, Pinterest, Twitter/X, Amazon, Criteo, Outbrain, Taboola
- **Contenuti incorporati:** YouTube, Vimeo, Google Maps, Instagram, Facebook, Spotify

Per i contenuti incorporati (video, mappe, ecc.) vengono mostrati dei **placeholder interattivi** che spiegano perché il contenuto è bloccato e permettono di aprire le preferenze.

Puoi anche aggiungere **pattern di blocco personalizzati** dalle impostazioni.

### Google Consent Mode v2

Supporto integrato per il Google Consent Mode v2, obbligatorio da marzo 2024 per chi usa Google Ads nello Spazio Economico Europeo. Tutti e 7 i parametri sono gestiti:

- `ad_storage`, `ad_user_data`, `ad_personalization` → categoria Marketing
- `analytics_storage` → categoria Analitici
- `functionality_storage`, `personalization_storage` → categoria Preferenze
- `security_storage` → sempre concesso

Tutto parte da "denied" e viene aggiornato quando l'utente dà il consenso.

### Registrazione del consenso

Ogni consenso viene registrato in una tabella dedicata del database con:

- ID univoco del consenso (UUID)
- Hash dell'indirizzo IP (SHA-256, mai l'IP in chiaro)
- Scelte effettuate (JSON)
- Versione della policy al momento del consenso
- Tipo di azione (accetta tutto, rifiuta tutto, personalizzato, revoca)
- Data e ora

I log sono esportabili in CSV dall'area admin, utili per dimostrare la conformità in caso di controllo.

### Cookie policy automatica

Scudo include un database di oltre 60 cookie conosciuti (WordPress, WooCommerce, Google Analytics, Facebook, ecc.) con nome, categoria, durata e descrizione.

Usa lo shortcode `[scudo_cookie_table]` per inserire in qualsiasi pagina una tabella dei cookie organizzata per categoria e sempre aggiornata.

Il plugin include anche uno **scanner** che analizza la homepage del tuo sito e rileva automaticamente quali servizi di terze parti sono presenti.

### Privacy policy guidata

Un wizard ti guida nella creazione dell'informativa sulla privacy:

1. **Chi sei**: nome del titolare, indirizzo, email, PEC, DPO (se presente)
2. **Cosa fa il tuo sito**: e-commerce, blog, moduli di contatto, newsletter, analytics, marketing — il plugin rileva automaticamente i plugin installati
3. **Quali servizi usi**: scansione automatica dei servizi di terze parti
4. **Tema grafico**: scelta del tema del banner
5. **Finalizzazione**: il plugin crea automaticamente 3 pagine — Cookie Policy, Privacy Policy e Modulo Diritti

Usa lo shortcode `[scudo_privacy_policy]` per mostrare l'informativa dinamica generata dal wizard.

### Modulo per l'esercizio dei diritti (Artt. 15-22 GDPR)

Lo shortcode `[scudo_rights_form]` inserisce un modulo che permette ai visitatori di esercitare i propri diritti:

- **Art. 15** — Diritto di accesso
- **Art. 16** — Diritto di rettifica
- **Art. 17** — Diritto alla cancellazione (diritto all'oblio)
- **Art. 18** — Diritto di limitazione del trattamento
- **Art. 20** — Diritto alla portabilità dei dati
- **Art. 21** — Diritto di opposizione
- **Art. 22** — Diritto di non essere sottoposto a decisioni automatizzate

Le richieste vengono salvate nel database e notificate via email all'amministratore. Il plugin si integra con gli strumenti di privacy nativi di WordPress (esportazione e cancellazione dati).

### Self-hosting dei Google Fonts

Dal 2022, una sentenza del tribunale di Monaco ha stabilito che caricare i Google Fonts dal CDN di Google trasferisce l'IP dell'utente a Google senza consenso.

Scudo risolve il problema con un clic: scarica i font usati dal tuo tema in locale e riscrive automaticamente gli URL. Nessuna richiesta verso i server di Google.

### Checkbox consenso nei moduli

Scudo aggiunge automaticamente le checkbox di consenso al trattamento dei dati nei moduli di:

- **Contact Form 7**
- **WPForms**
- **Gravity Forms**
- **Commenti di WordPress**

La checkbox è obbligatoria, con link all'informativa privacy, e viene validata sia lato client che lato server.

### Supporto multilingua

Compatibile con **WPML** e **Polylang**. Tutte le stringhe del banner, del pannello preferenze, dei moduli e dell'informativa sono traducibili.

---

## Requisiti

- WordPress 5.8 o superiore
- PHP 7.4 o superiore

---

## Shortcode disponibili

| Shortcode | Cosa fa |
|---|---|
| `[scudo_cookie_table]` | Tabella dei cookie organizzata per categoria |
| `[scudo_privacy_policy]` | Informativa privacy dinamica generata dal wizard |
| `[scudo_rights_form]` | Modulo per l'esercizio dei diritti GDPR |

---

## Impostazioni

Dopo l'attivazione, trovi Scudo nel menu **Velocia** del pannello admin. Le impostazioni sono organizzate in schede:

- **Dashboard** — Statistiche sui consensi degli ultimi 30 giorni
- **Banner** — Testi, posizione, etichette delle categorie
- **Cookie** — Scanner, database cookie, esportazione CSV
- **Strumenti** — Download Google Fonts, reset, riavvio wizard
- **Tema** — Scuro, chiaro, personalizzato con color picker
- **Avanzate** — Durata consenso, logging, Consent Mode v2, pattern di blocco personalizzati

---

## Conformità normativa

Scudo è progettato per rispettare:

- **GDPR** (Regolamento UE 2016/679) — Articoli 4, 5, 6, 7, 12-14, 15-22, 30
- **Direttiva ePrivacy** (2002/58/CE) e **Codice Privacy italiano** (Art. 122)
- **Linee guida del Garante Privacy italiano** (giugno 2021) — uguale peso visivo dei pulsanti, X = rifiuto, nessun dark pattern, categorie granulari, rinnovo a 6 mesi, prova del consenso
- **Google Consent Mode v2** (obbligatorio da marzo 2024 per Google Ads nel SEE)

---

## Disclaimer — Esclusione di responsabilità

**L'utilizzo di questo plugin non garantisce di per sé la conformità al GDPR, alla direttiva ePrivacy, alle linee guida del Garante Privacy o a qualsiasi altra normativa sulla protezione dei dati personali.**

Scudo è uno **strumento tecnico** che facilita l'implementazione di alcune misure richieste dalla normativa, ma la conformità al GDPR è un processo complessivo che dipende da molti fattori specifici della tua attività, tra cui:

- La corretta identificazione di tutte le basi giuridiche del trattamento
- La completezza e l'accuratezza dell'informativa privacy
- L'adozione di misure di sicurezza adeguate
- La gestione dei rapporti con i responsabili del trattamento
- La tenuta del registro dei trattamenti
- La valutazione d'impatto (DPIA), se necessaria
- La nomina del DPO, se richiesta
- La gestione delle violazioni dei dati (data breach)

**La responsabilità della conformità al GDPR resta interamente in capo al titolare del trattamento**, ovvero a te o alla tua organizzazione. Ti consigliamo di consultare un professionista qualificato in materia di protezione dei dati personali per verificare che il tuo sito web sia pienamente conforme alla normativa applicabile.

Questo plugin è distribuito "così com'è" (**as is**), senza garanzie di alcun tipo, esplicite o implicite. L'autore non è responsabile per eventuali danni, sanzioni, contestazioni o perdite derivanti dall'utilizzo del plugin o dalla mancata conformità alla normativa.

---

## Licenza

GPL-2.0-or-later — Vedi il file [LICENSE](scudo/LICENSE) per i dettagli.

---

## Autore

Sviluppato da **Francesco Caruccio**.
