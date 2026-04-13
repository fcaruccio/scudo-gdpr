# Review WordPress.org — Scudo Plugin
**Data:** 13 aprile 2026
**Review ID:** AUTOPREREVIEW TRM scudo/francescocaruccio/13Apr26/T1

---

## Riepilogo

WordPress ha identificato 4 problemi da risolvere prima dell'approvazione.

---

## 1. NOME E SLUG — DA CAMBIARE

**Problema:** Il nome "Scudo" è troppo generico e potenzialmente confondibile con marchi registrati (Fiat Scudo, Scudo Consulting, ecc.).

**Cosa fare:**
- Scegliere un nuovo display name e slug
- Suggerimento di WordPress: "Scudo Cookie & Privacy" / `scudo-cookie-privacy`
- Aggiornare il nome in `readme.txt` e nell'header del plugin (`scudo.php`)
- Aggiornare lo slug in tutte le funzioni di internazionalizzazione (text domain)
- Rispondere all'email chiedendo la prenotazione del nuovo slug
- Caricare la nuova versione tramite "Add your plugin"

**File coinvolti:**
- `scudo.php` (header plugin)
- `readme.txt`
- Tutti i file che usano il text domain `'scudo'` nelle funzioni `__()`, `_e()`, `esc_html__()`, ecc.

---

## 2. INLINE JS/CSS — USARE wp_enqueue

**Problema:** Ci sono tag `<script>` e `<style>` inline nel codice PHP. WordPress richiede di usare le funzioni di enqueue.

**File e righe da correggere:**
- `includes/class-rights.php:112` — `<script>` inline
- `includes/class-scanner.php:381` — `<style>` inline
- `includes/class-privacy-policy.php:232` — `<script>` inline
- `admin/class-admin.php:442` — `<script>` inline
- `admin/class-admin.php:644` — `<script>` inline
- `admin/class-admin.php:146` — `<style>` inline
- `admin/class-wizard.php:548` — `<script>` inline
- `admin/class-wizard.php:299` — `<style>` inline

**Cosa fare:**
- Sostituire gli `<script>` inline con `wp_add_inline_script()`
- Sostituire gli `<style>` inline con `wp_add_inline_style()`
- Per JS/CSS statici, usare `wp_enqueue_script()` / `wp_enqueue_style()`
- Per le pagine admin: hook `admin_enqueue_scripts`
- Per le pagine frontend: hook `wp_enqueue_scripts`

---

## 3. CHIAMATE REMOTE — LINK A CLOUDFLARE

**Problema:** Il plugin contiene un link remoto alla privacy policy di Cloudflare nella privacy policy generata.

**File:** `includes/class-privacy-policy.php:449`
```
https://www.cloudflare.com/privacypolicy/
```

**Cosa fare:**
- Se è un link informativo nella privacy policy generata (testo, non risorsa caricata), chiarire nella risposta all'email che è un link testuale nella policy, non un offloading di risorse
- Se effettivamente carica risorse remote, includere il file localmente

---

## 4. SERVIZI DI TERZE PARTI NON DOCUMENTATI

**Problema:** Il plugin blocca/gestisce servizi di terze parti (Google Analytics, Facebook Pixel, ecc.) ma non documenta nel `readme.txt` quali servizi esterni vengono coinvolti.

**File:** `includes/class-blocker.php:21` e tutto il DOMAIN_MAP

**Cosa fare:**
- Aggiungere una sezione `== External services ==` nel `readme.txt`
- Per ogni servizio gestito, documentare:
  - Cos'è e a cosa serve
  - Quali dati vengono inviati e quando
  - Link ai termini di servizio e privacy policy del servizio
- Il plugin non chiama direttamente questi servizi (li blocca!), ma WordPress vuole comunque che siano documentati nel readme
- Documentare anche il self-hosting dei Google Fonts (il plugin scarica font da fonts.googleapis.com)

---

## Priorità di intervento

1. **Nome/slug** — Decidere il nuovo nome PRIMA di tutto, perché impatta tutto il codice
2. **wp_enqueue** — Intervento tecnico su 8 punti nel codice
3. **readme.txt** — Aggiungere sezione servizi esterni
4. **Cloudflare** — Chiarire o rimuovere

---

## Prossimi passi

1. Scegliere il nuovo nome definitivo
2. Applicare tutte le modifiche al codice
3. Aggiornare readme.txt con sezione servizi esterni
4. Testare il plugin (attivazione, wizard, banner, tutte le funzionalità)
5. Caricare la nuova versione su WordPress.org
6. Rispondere all'email chiedendo la prenotazione del nuovo slug
