/**
 * Scudo — Banner & Consent Manager
 *
 * Vanilla JS, zero dipendenze, ~5KB minificato.
 */
(function () {
    'use strict';

    var C = window.scudoConfig || {};
    var COOKIE = C.cookieName || 'scudo_consent';
    var CID_COOKIE = C.cidCookieName || 'scudo_cid';
    var EXPIRY_DAYS = C.expiry || 180;

    /* ── Elementi DOM ────────────────────────────────────────────── */

    var banner = document.getElementById('scudo-banner');
    var prefs = document.getElementById('scudo-prefs');
    var reopen = document.getElementById('scudo-reopen');

    if (!banner) return;

    /* ── Utility cookie ──────────────────────────────────────────── */

    function getCookie(name) {
        var v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return v ? decodeURIComponent(v.pop()) : null;
    }

    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 86400000);
        document.cookie = name + '=' + encodeURIComponent(value)
            + ';expires=' + d.toUTCString()
            + ';path=/;SameSite=Lax;Secure';
    }

    function deleteCookie(name) {
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;SameSite=Lax;Secure';
    }

    /* ── UUID semplice ───────────────────────────────────────────── */

    function uuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    /* ── Leggi il consenso dal cookie ────────────────────────────── */

    function readConsent() {
        var raw = getCookie(COOKIE);
        if (!raw) return null;
        try {
            var data = JSON.parse(raw);
            if (!data || !data.choices) return null;
            // Controlla scadenza
            var maxAge = EXPIRY_DAYS * 86400;
            if ((Date.now() / 1000 - (data.timestamp || 0)) > maxAge) return null;
            // Controlla versione policy
            if (data.policy_version !== C.policyVersion) return null;
            return data.choices;
        } catch (e) {
            return null;
        }
    }

    /* ── Salva il consenso ───────────────────────────────────────── */

    function saveConsent(choices, actionType) {
        // Cookie consenso (tecnico: non richiede consenso)
        var cookieData = {
            choices: choices,
            timestamp: Math.floor(Date.now() / 1000),
            policy_version: C.policyVersion
        };
        setCookie(COOKIE, JSON.stringify(cookieData), EXPIRY_DAYS);

        // Cookie ID per il log
        var cid = getCookie(CID_COOKIE) || uuid();
        setCookie(CID_COOKIE, cid, EXPIRY_DAYS);

        // Google Consent Mode v2 update
        if (C.gcmEnabled && C.gcmUpdateJs) {
            try {
                new Function(C.gcmUpdateJs + ';scudoGcmUpdate(' + JSON.stringify(choices) + ');')();
            } catch (e) { /* silenzioso */ }
        }

        // Log AJAX (fire-and-forget)
        if (C.ajaxUrl && C.nonce) {
            var fd = new FormData();
            fd.append('action', 'scudo_save_consent');
            fd.append('nonce', C.nonce);
            fd.append('consent_action', actionType);
            fd.append('choices', JSON.stringify(choices));
            fetch(C.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
        }

        // Attiva gli script bloccati per le categorie consentite
        activateScripts(choices);
        activatePlaceholders(choices);

        // Dispatch evento custom per altri plugin
        document.dispatchEvent(new CustomEvent('scudoConsent', { detail: choices }));
    }

    /* ── Attiva script bloccati ──────────────────────────────────── */

    function activateScripts(choices) {
        var scripts = document.querySelectorAll('script[type="text/plain"][data-gdpr-category]');
        for (var i = 0; i < scripts.length; i++) {
            var cat = scripts[i].getAttribute('data-gdpr-category');
            if (choices[cat]) {
                var newScript = document.createElement('script');
                // Copia attributi
                for (var j = 0; j < scripts[i].attributes.length; j++) {
                    var attr = scripts[i].attributes[j];
                    if (attr.name === 'type') continue;
                    if (attr.name === 'data-gdpr-category') continue;
                    newScript.setAttribute(attr.name, attr.value);
                }
                newScript.type = 'text/javascript';
                // Se ha contenuto inline
                if (scripts[i].textContent) {
                    newScript.textContent = scripts[i].textContent;
                }
                scripts[i].parentNode.replaceChild(newScript, scripts[i]);
            }
        }
    }

    /* ── Attiva placeholder iframe ───────────────────────────────── */

    function activatePlaceholders(choices) {
        var placeholders = document.querySelectorAll('.scudo-placeholder[data-gdpr-category]');
        for (var i = 0; i < placeholders.length; i++) {
            var cat = placeholders[i].getAttribute('data-gdpr-category');
            if (choices[cat]) {
                restoreIframe(placeholders[i]);
            }
        }
    }

    function restoreIframe(placeholder) {
        var src = placeholder.getAttribute('data-gdpr-src');
        if (!src) return;
        var iframe = document.createElement('iframe');
        iframe.src = src;
        // Recupera attributi originali se possibile
        var attrsRaw = placeholder.getAttribute('data-gdpr-attrs') || '';
        var widthMatch = attrsRaw.match(/width\s*=\s*["']?(\d+)/);
        var heightMatch = attrsRaw.match(/height\s*=\s*["']?(\d+)/);
        if (widthMatch) iframe.width = widthMatch[1];
        if (heightMatch) iframe.height = heightMatch[1];
        iframe.style.width = placeholder.style.width || '100%';
        iframe.style.height = placeholder.style.height || '400px';
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('loading', 'lazy');
        placeholder.parentNode.replaceChild(iframe, placeholder);
    }

    /* ── Mostra / Nascondi ───────────────────────────────────────── */

    function showBanner() {
        banner.hidden = false;
        banner.setAttribute('aria-hidden', 'false');
        if (reopen) reopen.hidden = true;
        // Focus trap: focus sul primo pulsante
        var firstBtn = banner.querySelector('button');
        if (firstBtn) firstBtn.focus();
    }

    function hideBanner() {
        banner.hidden = true;
        banner.setAttribute('aria-hidden', 'true');
        if (reopen) reopen.hidden = false;
    }

    function showPrefs() {
        prefs.hidden = false;
        prefs.setAttribute('aria-hidden', 'false');
        // Imposta toggle in base al consenso corrente
        var consent = readConsent();
        var cats = ['analytics', 'marketing', 'preferences'];
        for (var i = 0; i < cats.length; i++) {
            var cb = prefs.querySelector('[data-gdpr-cat="' + cats[i] + '"]');
            if (cb) cb.checked = consent ? !!consent[cats[i]] : false;
        }
        // Focus sul pannello
        var closeBtn = prefs.querySelector('.scudo-prefs__close');
        if (closeBtn) closeBtn.focus();
        // Blocca scroll body
        document.body.style.overflow = 'hidden';
    }

    function hidePrefs() {
        prefs.hidden = true;
        prefs.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    /* ── Azioni ──────────────────────────────────────────────────── */

    function acceptAll() {
        var choices = { necessary: true, analytics: true, marketing: true, preferences: true };
        saveConsent(choices, 'accept_all');
        hideBanner();
        hidePrefs();
    }

    function rejectAll() {
        var choices = { necessary: true, analytics: false, marketing: false, preferences: false };
        saveConsent(choices, 'reject_all');
        hideBanner();
        hidePrefs();
    }

    function savePrefs() {
        var choices = { necessary: true };
        var cats = ['analytics', 'marketing', 'preferences'];
        for (var i = 0; i < cats.length; i++) {
            var cb = prefs.querySelector('[data-gdpr-cat="' + cats[i] + '"]');
            choices[cats[i]] = cb ? cb.checked : false;
        }
        saveConsent(choices, 'custom');
        hideBanner();
        hidePrefs();
    }

    /* ── Event delegation ────────────────────────────────────────── */

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-gdpr-action]');
        if (!btn) return;

        var action = btn.getAttribute('data-gdpr-action');
        switch (action) {
            case 'accept_all': acceptAll(); break;
            case 'reject_all': rejectAll(); break;
            case 'customize': showPrefs(); break;
            case 'save_prefs': savePrefs(); break;
            case 'close_prefs': hidePrefs(); break;
        }
    });

    // Tastiera: Escape chiude il pannello preferenze
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!prefs.hidden) {
                hidePrefs();
            } else if (!banner.hidden) {
                rejectAll();
            }
        }
    });

    // Widget riapertura
    if (reopen) {
        reopen.addEventListener('click', function () {
            showPrefs();
        });
    }

    /* ── Init: mostra banner se nessun consenso valido ───────────── */

    var consent = readConsent();
    if (consent === null) {
        showBanner();
    } else {
        // Consenso esistente: attiva script e mostra widget riapertura
        activateScripts(consent);
        activatePlaceholders(consent);
        if (reopen) reopen.hidden = false;
    }

})();
