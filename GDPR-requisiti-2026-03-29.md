# Requisiti GDPR per siti web - Conformità italiana ed europea

**Data analisi: 29 marzo 2026**
**Ultimo aggiornamento normativo verificato: marzo 2026**

---

## Fonti normative di riferimento

- **GDPR** - Regolamento UE 2016/679
- **Direttiva ePrivacy** - 2002/58/CE (modificata dalla 2009/136/CE)
- **Codice Privacy italiano** - D.Lgs. 196/2003 (aggiornato dal D.Lgs. 101/2018)
- **Linee guida Garante Privacy** del 10 giugno 2021 (G.U. 9 luglio 2021, operative dal 9 gennaio 2022)
- **Provvedimento Garante su Google Analytics** del 9 giugno 2022
- **EDPB Guidelines 05/2020** sul consenso (versione 2.0, maggio 2020)
- **Sentenza CGUE Planet49** (C-673/17, 1 ottobre 2019)
- **EU-US Data Privacy Framework** (adottato 10 luglio 2023)
- **Google Consent Mode v2** (obbligatorio da 6 marzo 2024)

---

## 1. Consenso ai cookie

### Principi fondamentali
- Il consenso deve essere **libero, specifico, informato e inequivocabile** (Art. 4(11) GDPR)
- Richiede un **atto positivo chiaro** (Considerando 32 GDPR)
- Deve essere **granulare**: l'utente sceglie per singole categorie di cookie
- Deve essere **revocabile** con la stessa facilità con cui è stato prestato (Art. 7(3))
- **Silenzio, caselle preselezionate o inattività NON costituiscono consenso** (Considerando 32)

### Linee guida Garante Privacy (giugno 2021)
1. Al primo accesso deve comparire un banner ben visibile
2. Due livelli di informativa: breve (banner) + estesa (cookie policy completa)
3. Consenso raccolto separatamente per ogni finalità
4. **Nessun cookie non tecnico prima del consenso**
5. Rinnovo del consenso ogni **6 mesi**
6. Il titolare deve poter dimostrare di aver ottenuto il consenso valido

---

## 2. Privacy policy - Contenuto obbligatorio (Artt. 13-14 GDPR)

La privacy policy deve includere:

1. **Identità e dati di contatto** del titolare del trattamento
2. **Dati di contatto del DPO**, se nominato
3. **Finalità del trattamento** e base giuridica per ciascuna finalità
4. **Legittimi interessi** perseguiti (se base giuridica Art. 6(1)(f))
5. **Destinatari o categorie di destinatari** dei dati
6. **Trasferimenti verso paesi terzi** e garanzie appropriate (Art. 44-49)
7. **Periodo di conservazione** dei dati o criteri per determinarlo
8. **Diritti dell'interessato**:
   - Accesso (Art. 15)
   - Rettifica (Art. 16)
   - Cancellazione / "diritto all'oblio" (Art. 17)
   - Limitazione del trattamento (Art. 18)
   - Portabilità dei dati (Art. 20)
   - Opposizione (Art. 21)
   - Non essere sottoposto a decisioni automatizzate (Art. 22)
9. **Diritto di revocare il consenso** in qualsiasi momento
10. **Diritto di proporre reclamo** all'autorità di controllo (Garante Privacy)
11. **Natura obbligatoria o facoltativa** del conferimento dei dati
12. **Fonte dei dati** se non raccolti direttamente dall'interessato (Art. 14)
13. **Esistenza di processi decisionali automatizzati**, inclusa la profilazione

### Requisiti formali
- Linguaggio **semplice e chiaro** (Art. 12)
- **Facilmente accessibile** da ogni pagina (link nel footer)
- Formato **conciso, trasparente, intelligibile**
- **Datata** con l'ultimo aggiornamento
- Disponibile **in italiano** per utenti italiani

---

## 3. Cookie banner

### Primo livello (Banner)

Requisiti Garante 2021:

1. **Ben visibile** e distinguibile dal resto della pagina
2. Informativa breve con indicazione dell'uso di cookie tecnici e, previo consenso, cookie di profilazione/analytics di terze parti
3. **Link alla cookie policy completa**
4. Pulsante **"Accetta tutti"**
5. Pulsante **"Rifiuta tutti"** con **stessa prominenza visiva** del pulsante Accetta (requisito critico!)
6. Pulsante **"Personalizza"** per accesso al secondo livello
7. **X di chiusura** che equivale al rifiuto dei cookie non tecnici
8. **NON deve** impedire la navigazione del sito (no cookie wall)
9. **Nessun dark pattern** per influenzare la scelta

### Secondo livello (Pannello preferenze)

1. Selezione **granulare per categorie**:
   - Cookie tecnici/necessari (non disattivabili)
   - Cookie analitici/statistici
   - Cookie di profilazione/marketing
   - Cookie di targeting/retargeting
   - Cookie di social media
2. **Finalità** indicata per ogni categoria
3. Elenco dei **singoli cookie** o categorie con relativi fornitori
4. Pulsante **"Salva preferenze"**
5. **Accessibile in qualsiasi momento** (widget/icona persistente o link nel footer)

---

## 4. Tipologie di cookie e trattamento

### Cookie tecnici/necessari
- **Non richiedono consenso** (Art. 122(1) Codice Privacy)
- Esempi: sessione, carrello, autenticazione, sicurezza, bilanciamento del carico
- Devono comunque essere **descritti nella cookie policy**
- Base giuridica: legittimo interesse o esecuzione del contratto

### Cookie analitici/statistici
- **Analytics di prima parte** (es. Matomo self-hosted): equiparabili ai tecnici SOLO SE:
  - Statistiche aggregate
  - IP anonimizzato (troncamento almeno ultimo ottetto)
  - Non incrociati con altri trattamenti
  - Il fornitore terzo si impegna contrattualmente a non arricchire i dati
- **Google Analytics**: richiede **SEMPRE consenso preventivo**
  - Dati trasferiti a Google (terza parte)
  - Google può incrociare dati con altri servizi
  - Provvedimento Garante del 9 giugno 2022 (caso Caffeina Media S.r.l.)
- **Analytics di terze parti**: **sempre consenso preventivo**

### Cookie di profilazione/marketing
- **SEMPRE consenso preventivo esplicito**
- Include: remarketing, retargeting, pubblicità personalizzata
- Consenso specifico per questa finalità
- Elencati in cookie policy con indicazione del fornitore

### Cookie di preferenza/funzionalità
- Se strettamente necessari: trattati come tecnici
- Se per migliorare l'esperienza senza essere necessari: richiedono consenso

---

## 5. Gestione del consenso

### Raccolta
1. **Opt-in esplicito**: azione positiva (click, toggle attivato)
2. **Granularità**: scelta per singola categoria/finalità
3. **Informazione preventiva**: informato PRIMA di esprimere il consenso
4. **Nessun pregiudizio**: servizio base accessibile anche senza consenso
5. **Nessun condizionamento**: consenso non è condizione per l'accesso

### Revoca
- Possibile **in qualsiasi momento**
- **Facile quanto prestare il consenso** (Art. 7(3))
- Meccanismo **facilmente accessibile** (widget, link nel footer, icona flottante)
- **Effettiva**: cookie rimossi o disattivati alla revoca
- Non pregiudica la liceità del trattamento precedente

### Prova del consenso
Il titolare deve registrare:
- **Data e ora** del consenso
- **Versione** della cookie policy/banner al momento del consenso
- **Scelte specifiche** (categorie accettate/rifiutate)
- **Identificativo** utente (cookie ID, user ID se autenticato)
- **Modalità** di raccolta del consenso
- Registri conservati per durata del trattamento + periodo di prescrizione

### Rinnovo
- Almeno ogni **6 mesi** (indicazione Garante)
- Rinnovo obbligatorio in caso di **modifiche sostanziali** alla cookie policy
- Banner riappare dopo scadenza del periodo di conservazione

---

## 6. Trattamento dei dati

### Basi giuridiche (Art. 6 GDPR)
1. **Consenso** dell'interessato
2. **Esecuzione di un contratto**
3. **Obbligo legale**
4. **Interessi vitali**
5. **Compito di interesse pubblico**
6. **Legittimo interesse** del titolare o di terzi

### Principi del trattamento (Art. 5 GDPR)
1. **Liceità, correttezza e trasparenza**
2. **Limitazione della finalità**
3. **Minimizzazione dei dati**
4. **Esattezza**
5. **Limitazione della conservazione**
6. **Integrità e riservatezza**
7. **Responsabilizzazione** (accountability)

### Registro dei trattamenti (Art. 30 GDPR)
- Obbligatorio per organizzazioni con >250 dipendenti
- Obbligatorio anche per organizzazioni più piccole se il trattamento presenta rischi, non è occasionale, o include dati particolari
- In pratica, **quasi tutti i siti web** devono mantenere un registro

---

## 7. Servizi di terze parti

### Google Analytics
- Provvedimento Garante 9 giugno 2022: uso dichiarato illecito per trasferimento dati USA
- EU-US Data Privacy Framework (luglio 2023): nuova base per trasferimenti verso aziende USA certificate
- Resta comunque necessario il **consenso preventivo** (cookie analitico di terze parti)
- Requisiti per GA4 conforme: consenso preventivo, anonimizzazione IP, disattivazione condivisione dati, retention minima, Google Consent Mode v2

### Google Fonts
- Caricamento da CDN trasferisce IP a Google
- Tribunale di Monaco (20 gennaio 2022): uso da CDN senza consenso viola il GDPR
- **Soluzione conforme: self-hosting locale dei font**

### YouTube embeds
- Caricamento standard installa cookie di profilazione Google
- Usare **youtube-nocookie.com** (privacy-enhanced mode)
- Anche con nocookie, al click: serve consenso o **placeholder**
- Alternativa: thumbnail statica con link

### Social media widgets
- I pulsanti social installano cookie di tracciamento al caricamento
- **Richiedono consenso preventivo**
- Soluzione: sistema a **due click** o blocco fino al consenso
- Alternativa: link semplici ai profili social

### Google Maps
- Trasferisce dati a Google, richiede consenso preventivo
- Soluzione: **immagine statica** con link, oppure consenso prima dell'iframe

### Facebook Pixel, LinkedIn Insight Tag, altri tracker
- Cookie di **profilazione/marketing**: **sempre consenso esplicito preventivo**
- Mai caricati prima del consenso

---

## 8. Legittimo interesse

### Quando si applica (Art. 6(1)(f))
- Necessario per il perseguimento di un interesse legittimo del titolare
- A condizione che non prevalgano diritti e libertà dell'interessato

### Test di bilanciamento (LIA)
1. **Test di finalità**: identificare l'interesse legittimo
2. **Test di necessità**: verificare che non ci siano alternative meno invasive
3. **Test di bilanciamento**: bilanciare interesse del titolare vs diritti dell'interessato

### Nel contesto web
- **Cookie tecnici**: base generalmente valida
- **Sicurezza del sito**: protezione da attacchi, log di sicurezza
- **Analytics di base**: SOLO se anonimizzati e di prima parte
- **Cookie di profilazione/marketing**: legittimo interesse **MAI sufficiente** - serve consenso
- **Marketing diretto a clienti esistenti**: possibile soft opt-in per prodotti simili (Art. 130(4) Codice Privacy), con possibilità di opt-out facile

### Posizione del Garante italiano
- Generalmente **restrittivo** per cookie e tracciamento online
- La Direttiva ePrivacy prevale: consenso obbligatorio per accesso/memorizzazione sul terminale (salvo cookie tecnici)

---

## 9. Form di contatto e raccolta dati

### Informativa
- Ogni form deve avere un **link alla privacy policy** o estratto dell'informativa
- Indicare **quali dati** e **per quale finalità**
- Indicare la **base giuridica**

### Consenso specifico
- Finalità diverse dall'evasione della richiesta: **consenso separato** per ogni finalità
- Consenso newsletter **distinto** dal consenso per richiesta di contatto
- **Double opt-in** per newsletter: fortemente raccomandato

### Checkbox
- **Non preselezionate** (Considerando 32, Sentenza Planet49)
- **Separata per ogni finalità** distinta
- Accettazione privacy policy ≠ consenso al trattamento (è conferma di lettura)

### Minimizzazione
- **Solo dati strettamente necessari** (Art. 5(1)(c))
- Non rendere obbligatori campi non necessari

### Sicurezza
- Trasmissione sicura (**HTTPS**)
- Anti-spam rispettoso della privacy (attenzione: Google reCAPTCHA trasferisce dati a Google)
- Alternative privacy-friendly: hCaptcha, Turnstile (Cloudflare), honeypot fields

---

## 10. Conservazione dei dati

### Principio (Art. 5(1)(e))
Dati conservati **solo per il tempo necessario** alle finalità.

### Periodi tipici
| Tipo di dato | Periodo |
|---|---|
| Cookie tecnici di sessione | Chiusura browser |
| Cookie di consenso | 6-12 mesi |
| Dati form di contatto | Fino a evasione + periodo ragionevole |
| Dati fatturazione/contratti | 10 anni (obbligo fiscale italiano) |
| Dati marketing/newsletter | Fino a revoca consenso |
| Log di sicurezza | 6-12 mesi |
| Dati Google Analytics | 2 mesi (minimo GA4) |
| Registri del consenso | Durata trattamento + prescrizione |

### Obblighi
- Definire e documentare le **policy di retention**
- Implementare **meccanismi automatici** di cancellazione/anonimizzazione
- Comunicare i periodi nella privacy policy

---

## 11. DPIA (Valutazione d'impatto)

### Quando è obbligatoria (Art. 35)
1. Valutazione sistematica e globale di aspetti personali (profilazione)
2. Trattamento su larga scala di dati particolari (Art. 9) o giudiziari (Art. 10)
3. Sorveglianza sistematica di zona accessibile al pubblico su larga scala

### Nel contesto web
- Per la maggior parte dei siti standard: **non obbligatoria**
- **Necessaria** se: profilazione sistematica su larga scala, dati sanitari/sensibili su larga scala, tecnologie innovative di tracciamento, sistemi di scoring/decisioni automatizzate

---

## 12. Cookie wall

### Regola generale: VIETATO
- EDPB (Guidelines 05/2020): il consenso non è libero se condizionato all'accettazione dei cookie
- Garante 2021: vietato in linea di principio

### Eccezione limitata
- Ammissibile SOLO se offerta un'**alternativa equivalente** (es. "accetta i cookie OPPURE abbonati")
- Modello "consent or pay": molto dibattuto, parere EDPB aprile 2024 restrittivo per piattaforme dominanti
- **Raccomandazione**: evitare e permettere sempre la navigazione base senza consenso

---

## 13. Scroll-as-consent: NON VALIDO

- Sentenza CGUE Planet49: il consenso richiede atto positivo chiaro
- EDPB Guidelines 05/2020: lo scrolling non è azione chiara e inequivocabile
- Garante 2021: **esplicitamente escluso** come meccanismo valido
- Il banner deve restare attivo fino ad azione esplicita (click accetta/rifiuta/chiudi)

---

## 14. Caselle preselezionate: NON CONSENTITE

- Sentenza CGUE Planet49: non costituiscono consenso valido
- Considerando 32 GDPR: "il silenzio, l'inattività o la preselezione di caselle non dovrebbero costituire consenso"
- Toggle nel pannello preferenze: **disattivati per default** (eccetto tecnici)
- Checkbox nei form: **non selezionate per default**
- Ogni categoria non tecnica: **opt-in, mai opt-out**

---

## 15. Direttiva ePrivacy e GDPR

### Rapporto
- ePrivacy è **lex specialis** rispetto al GDPR per comunicazioni elettroniche
- Art. 5(3) ePrivacy (Art. 122 Codice Privacy): regola accesso/memorizzazione sul terminale
- GDPR si applica in via sussidiaria

### Regola ePrivacy per i cookie
- **Consenso obbligatorio** per memorizzare/accedere a informazioni sul terminale
- **Eccezione**: non serve consenso se strettamente necessario per servizio esplicitamente richiesto dall'utente (cookie tecnici)

### Regolamento ePrivacy
- Ancora in fase di negoziazione legislativa (2026)
- Fino all'adozione: Direttiva ePrivacy resta in vigore

---

## 16. Requisiti specifici italiani

### Linee guida Garante 2021 - Punti specifici
1. Chiusura banner (X) = **rifiuto** dei cookie non tecnici
2. Pulsanti accetta/rifiuta con **pari evidenza grafica**
3. Divieto di **dark patterns**
4. Cookie analytics prima parte con IP anonimizzato e senza incrocio: equiparati ai tecnici
5. Cookie analytics terze parti: sempre consenso
6. Banner non si ripresenta se utente ha già scelto; si ripresenta dopo **6 mesi**
7. Le linee guida si applicano anche al **device fingerprinting**

### Provvedimento Google Analytics (9 giugno 2022)
- Uso di GA Universal dichiarato illecito per trasferimento dati USA senza garanzie
- Concessi 90 giorni per conformarsi
- Impatto significativo su tutto il mercato italiano

### Sanzioni penali (Codice Privacy)
- Trattamento illecito: reclusione 6 mesi - 3 anni (Art. 167)
- Comunicazione/diffusione illecita: reclusione 1 - 6 anni (Art. 167-bis)
- Acquisizione fraudolenta: reclusione 1 - 4 anni (Art. 167-ter)
- Falsità nelle dichiarazioni al Garante: reclusione 6 mesi - 3 anni (Art. 168)
- Inosservanza provvedimenti Garante: reclusione 3 mesi - 2 anni (Art. 170)

---

## 17. Google Consent Mode v2

### Obbligo (da 6 marzo 2024)
Necessario per:
- Raccogliere dati per campagne Google Ads nell'EEA
- Funzionalità di remarketing e audience per utenti EEA
- Misurazione delle conversioni

### Parametri
| Parametro | Descrizione |
|---|---|
| `ad_storage` | Consenso per cookie pubblicitari |
| `ad_user_data` | Consenso invio dati utente a Google per advertising (NUOVO v2) |
| `ad_personalization` | Consenso personalizzazione annunci (NUOVO v2) |
| `analytics_storage` | Consenso cookie analitici |
| `functionality_storage` | Consenso cookie funzionali |
| `personalization_storage` | Consenso cookie personalizzazione |
| `security_storage` | Cookie sicurezza (sempre attivi) |

### Modalità operative
- **Basic mode** (raccomandato): nessun tag Google prima del consenso; segnali solo dopo il consenso
- **Advanced mode**: tag caricati senza cookie, invio "ping" anonimi anche senza consenso (possibili problemi di conformità)

### Implementazione nel plugin
- Default su **"denied"** nel data layer prima del caricamento tag
- Aggiornamento a **"granted"** solo dopo il consenso per la rispettiva categoria
- Integrazione con Google Tag Manager via `gtag('consent', 'update', {...})`

---

## 18. IAB TCF (Transparency and Consent Framework)

### TCF v2.2 (versione corrente, maggio 2023)
- Rimozione del legittimo interesse come base per pubblicità personalizzata
- Informazioni più dettagliate per gli utenti
- Elenco fornitori più trasparente

### Quando è necessario
- Siti con **pubblicità programmatica** (AdSense, header bidding)
- Google richiede CMP certificato TCF per Ad Manager/AdSense/AdMob nell'EEA
- **Non obbligatorio per legge**, standard di mercato

### Per un plugin WordPress
- **Non necessariamente** da implementare
- Rilevante solo per publisher con pubblicità programmatica
- Per siti aziendali/blog/e-commerce standard: generalmente non necessario
- Se implementato: CMP deve essere registrato presso IAB Europe

---

## 19. Sanzioni e multe

### Sanzioni GDPR (Art. 83)

**Livello inferiore** (Art. 83(4)):
- Fino a **10 milioni EUR** o **2% fatturato mondiale** (il maggiore)
- Per: obblighi titolare/responsabile, organismo di certificazione

**Livello superiore** (Art. 83(5)):
- Fino a **20 milioni EUR** o **4% fatturato mondiale** (il maggiore)
- Per: principi base, condizioni consenso, diritti interessati, trasferimenti paesi terzi

### Esempi di sanzioni notevoli
- Amazon (Lussemburgo, 2021): 746 milioni EUR
- Meta/Facebook (Irlanda, 2023): 1,2 miliardi EUR
- TIM (Italia, 2020): 27,8 milioni EUR
- Clearview AI (Italia, 2022): 20 milioni EUR
- Douglas Italia (2023): 50.000 EUR per gestione cookie
- Numerose sanzioni 10.000-100.000 EUR per PMI italiane

### Rischi pratici
- Il Garante conduce **indagini sistematiche** (sweep) su siti web
- Segnalazioni da **qualunque utente**
- Oltre alle sanzioni: ordine di cessazione del trattamento, danno reputazionale

---

## 20. Cosa deve gestire il plugin WordPress

### A. Cookie banner (ESSENZIALE)
1. Banner primo livello conforme Garante 2021 (accetta/rifiuta pari evidenza, X = rifiuto, no dark pattern)
2. Pannello preferenze secondo livello (categorie granulari, toggle, descrizione finalità, elenco cookie)
3. Widget/link per riapertura preferenze in qualsiasi momento

### B. Blocco preventivo servizi (ESSENZIALE)
4. Blocco di tutti gli script/cookie non tecnici PRIMA del consenso:
   - Google Analytics / GA4
   - Google Ads / remarketing
   - Facebook Pixel
   - YouTube embeds (sostituzione con placeholder)
   - Google Maps (sostituzione con immagine statica)
   - Widget social media
   - Google Fonts da CDN (o self-hosting automatico)
   - Qualsiasi script terze parti non tecnico
5. Metodi di blocco: modifica type script, sostituzione src con data-src, rimozione iframe, output buffer PHP, mutation observers JS

### C. Gestione consenso (ESSENZIALE)
6. Registrazione: cookie tecnico di consenso + log con timestamp, scelte, versione policy, identificativo, user agent
7. Scadenza e rinnovo: default 6 mesi, rinnovo se policy modificata
8. Revoca: modifica preferenze in qualsiasi momento, cancellazione effettiva cookie, disattivazione script

### D. Cookie policy (ESSENZIALE)
9. Scansione cookie del sito, classificazione automatica, tabella cookie (nome, dominio, durata, tipo, descrizione), template personalizzabile

### E. Google Consent Mode v2 (ESSENZIALE)
10. Default su "denied", aggiornamento dopo consenso, supporto tutti i parametri, Basic mode raccomandato, integrazione GTM

### F. Placeholder contenuti bloccati (ESSENZIALE)
11. Placeholder informativi per contenuti bloccati con spiegazione e pulsante consenso

### G. Self-hosting risorse (IMPORTANTE)
12. Download e hosting locale di Google Fonts

### H. Privacy policy (IMPORTANTE)
13. Template conforme Artt. 13-14 GDPR, campi personalizzabili, sezioni precompilate per servizi comuni

### I. Integrazione form (IMPORTANTE)
14. Checkbox consenso automatiche per CF7, WPForms, Gravity Forms; checkbox separate per finalità; registrazione consenso; link privacy

### L. Diritti degli interessati (IMPORTANTE)
15. Form esercizio diritti, integrazione export/cancellazione dati WordPress, notifiche al titolare

### M. Performance (ESSENZIALE)
16. Caricamento asincrono, CSS/JS minimizzati, compatibilità plugin di caching, zero impatto su Core Web Vitals

### N. Compatibilità (IMPORTANTE)
17. Temi principali, page builder (Elementor, Divi, Gutenberg), plugin di caching (LiteSpeed, W3TC), CDN, multilingue (WPML, Polylang)

### O. Accessibilità (IMPORTANTE)
18. WCAG 2.1 AA: navigazione tastiera, screen reader, contrasto adeguato, focus management

### P. Sicurezza (ESSENZIALE)
19. Protezione dati consenso, sanitizzazione input, protezione CSRF/XSS, nessuna vulnerabilità injection

### Q. IAB TCF v2.2 (OPZIONALE)
20. Solo per siti con pubblicità programmatica

### R. Reportistica (OPZIONALE)
21. Statistiche consenso, report cookie, alert cookie non categorizzati, log audit, export registri

---

## Riepilogo priorità

### ESSENZIALI (must-have per conformità)
1. Cookie banner conforme (pari evidenza accetta/rifiuta, X = rifiuto)
2. Blocco preventivo script/cookie non tecnici
3. Consenso granulare per categorie
4. Registrazione e prova del consenso
5. Meccanismo di revoca facilmente accessibile
6. Cookie policy con elenco cookie
7. Scadenza e rinnovo consenso (6 mesi)
8. Google Consent Mode v2 (Basic mode)
9. Placeholder per contenuti bloccati
10. Nessun dark pattern, no pre-checked boxes, no scroll-as-consent

### IMPORTANTI (fortemente raccomandati)
11. Scansione automatica cookie
12. Self-hosting Google Fonts
13. Integrazione form di contatto
14. Supporto multilingue
15. Compatibilità caching
16. Accessibilità WCAG 2.1

### AVANZATI (nice-to-have)
17. IAB TCF v2.2
18. Template privacy policy
19. Gestione diritti interessati
20. Dashboard con statistiche
21. Modalità avanzata Google Consent Mode v2
