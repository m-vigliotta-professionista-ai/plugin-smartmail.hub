# Architettura Plugin SmartMail Hub

## Panoramica

`valore24-smartmail-hub` e un plugin WordPress custom che espone una web app operativa dentro una normale pagina WordPress tramite shortcode.

La pagina pubblica di riferimento e:

`https://mail.aioffice24.blog/smartmail-hub`

## Flusso di bootstrap

1. `valore24-smartmail-hub.php`
   Avvia il plugin, definisce costanti, registra activation/deactivation hook e richiama `V24_SMH_Plugin::instance()->boot()`.

2. `includes/Core/class-plugin.php`
   Carica i moduli PHP, registra hook WordPress, asset frontend/admin e namespace REST.

3. `public/class-shortcodes.php`
   Registra lo shortcode `[valore24_smartmail_hub]` e decide quale template mostrare:
   - `templates/login.php` se l'utente non e autenticato
   - `templates/forbidden.php` se mancano le capability
   - `templates/app.php` se l'utente puo usare l'app

4. `templates/app.php`
   Renderizza lo shell HTML dell'app SmartMail Hub.

5. `assets/js/app.js` e `assets/js/ai.js`
   Montano il comportamento client-side della UI e parlano con le REST API del plugin.

## Entry point UI

I file principali per lavorare sulla UI sono:

- `templates/app.php`
- `templates/login.php`
- `templates/forbidden.php`
- `assets/css/app.css`
- `assets/css/ai.css`
- `assets/js/app.js`
- `assets/js/ai.js`

## Moduli backend

Il plugin e organizzato in moduli sotto `includes/`:

- `AI/`
  Servizi e persistenza per le funzioni AI.
- `Accounts/`
  Gestione account collegati.
- `Calendar/`
  Calendari, eventi, ricorrenze e partecipanti.
- `Contacts/`
  Rubrica e servizi contatti.
- `Core/`
  Bootstrap, attivazione, disattivazione e wiring principale.
- `Jobs/`
  Job asincroni, cron e sync runner.
- `Logs/`
  Audit log e sync log.
- `Mail/`
  IMAP, SMTP, repository messaggi, parsing, allegati e compose.
- `REST/`
  Controller WordPress REST.
- `Rules/`
  Regole automatiche su messaggi e workflow.
- `Security/`
  Capability, cifratura e permessi.
- `Tasks/`
  Task e servizi correlati.
- `UI/`
  Tema utente e widget di navigazione.

## REST API

Namespace REST principale:

`/wp-json/v24-ai-office/v1/smartmail/`

Controller principali rilevati:

- `class-rest-accounts-controller.php`
- `class-rest-mail-controller.php`
- `class-rest-contacts-controller.php`
- `class-rest-calendar-controller.php`
- `class-rest-tasks-controller.php`
- `class-rest-rules-controller.php`
- `class-rest-ai-controller.php`
- `class-rest-ui-controller.php`

## Comportamento frontend

La UI pubblica non arriva dal tema WordPress, ma dal plugin.

La pagina `smartmail-hub` contiene solo lo shortcode:

`[valore24_smartmail_hub]`

Questo significa che:

- layout, toolbar, moduli e dock AI stanno nel plugin
- il tema influisce soprattutto sul contesto globale della pagina
- per modifiche vere alla UI conviene partire da `templates/` e `assets/`

## Dati e dipendenze

Molte parti del plugin dipendono da dati reali presenti nel database WordPress:

- account mail
- cartelle e messaggi sincronizzati
- rubrica
- task
- regole
- preferenze tema
- configurazioni AI

In locale la UI puo aprirsi anche senza tutti i dati, ma alcune aree resteranno vuote o non operative finche il database non e popolato.

## Nota ambiente

Esistono due copie server del plugin:

- copia pubblica usata dal dominio `mail.aioffice24.blog`
  ` /home/aioff24/public_html/mail/wp-content/plugins/valore24-smartmail-hub`
- copia staging piu vecchia
  ` /home/staging/public_html/mail/wp-content/plugins/valore24-smartmail-hub`

Per questo progetto locale e stata usata la copia pubblica, che al momento risulta alla versione `0.4.28`.

## File di backup

La copia remota contiene molti file `.bak-*` dentro root, `assets/`, `admin/`, `public/` e altre cartelle.

Sono stati mantenuti nel progetto locale per allineamento con il server sorgente.
