# plugin-smartmail-hub

Repository locale del plugin WordPress `valore24-smartmail-hub`, costruito partendo dalla versione pubblica usata da `https://mail.aioffice24.blog/smartmail-hub`.

## Cosa contiene questo progetto

Questo progetto va trattato come root del plugin WordPress, non come installazione completa di WordPress.

Dentro trovi:

- bootstrap del plugin: `valore24-smartmail-hub.php`
- area admin del plugin: `admin/`
- codice PHP del plugin: `includes/`
- shortcode frontend: `public/`
- template frontend: `templates/`
- asset frontend/admin: `assets/`
- documentazione tecnica: `ARCHITETTURA-PLUGIN.md`
- README operativo locale: `README.md`
- README remoto di riferimento: `README.remote.md`
- ambiente WordPress locale separato: `local-wp/`
- script di sync plugin -> WordPress locale: `bin/sync-plugin.ps1`
- script one-shot per attivazione plugin + pagina test: `bin/bootstrap-local.ps1`
- script di ispezione account locali: `bin/inspect-local-accounts.ps1`
- script di creazione/riparazione account locali: `bin/save-local-account.ps1`

## Struttura del progetto

```text
plugin-smartmail-hub/
|-- admin/
|-- assets/
|-- bin/
|-- includes/
|-- public/
|-- templates/
|-- ARCHITETTURA-PLUGIN.md
|-- README.md
|-- README.remote.md
|-- uninstall.php
|-- valore24-smartmail-hub.php
`-- local-wp/
```

Nota: `local-wp/` resta locale ed e ignorata da Git. Nel repo va versionato solo il plugin.

## Nome plugin, slug tecnico e file principale

- nome plugin mostrato in WordPress: `Valore 24 AI Office - SmartMail Hub`
- slug/cartella plugin prevista in `wp-content/plugins/`: `valore24-smartmail-hub`
- file principale del plugin: `valore24-smartmail-hub.php`
- versione copiata dal server: `0.4.28`

## Pagina frontend e shortcode

- pagina frontend pubblica: `https://mail.aioffice24.blog/smartmail-hub`
- slug pagina WordPress: `smartmail-hub`
- shortcode principale: `[valore24_smartmail_hub]`

## Setup locale consigliato

### Modello di lavoro

Questo progetto segue la stessa logica usata per altri plugin locali:

1. il codice del plugin vive nella root del repo
2. WordPress locale vive separato in `local-wp/`
3. il plugin viene copiato dentro `local-wp/wp-content/plugins/valore24-smartmail-hub`
4. per riallineare il sito locale alle modifiche della repo si usa `.\bin\sync-plugin.ps1`

### Prerequisiti

- Docker Desktop
- DDEV
- PowerShell
- Git

Controlli utili:

```powershell
git --version
docker --version
ddev version
```

### Creare il sito WordPress locale

Entra nella cartella del progetto:

```powershell
cd "C:\Users\MicheleVigliotta\Desktop\Projects\plugin-smartmail-hub"
```

Entra nella cartella WordPress locale:

```powershell
cd .\local-wp
```

Configura DDEV:

```powershell
ddev config --project-name=smartmail-hub-local --project-type=wordpress --docroot="" --php-version=8.1 --webserver-type=nginx-fpm --database=mariadb:11.8 --web-environment="WP_ENVIRONMENT_TYPE=local"
```

Avvia l'ambiente:

```powershell
ddev start
```

Installa WordPress:

```powershell
ddev wp core download
ddev wp core install --url=https://smartmail-hub-local.ddev.site --title="SmartMail Hub Local" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
```

### Sincronizzare il plugin dentro WordPress locale

Torna nella root del progetto ed esegui:

```powershell
cd "C:\Users\MicheleVigliotta\Desktop\Projects\plugin-smartmail-hub"
.\bin\sync-plugin.ps1
```

Lo script copia il plugin nel path:

```text
local-wp/wp-content/plugins/valore24-smartmail-hub
```

### Attivare il plugin e creare una pagina test

Dalla root del progetto:

```powershell
cd "C:\Users\MicheleVigliotta\Desktop\Projects\plugin-smartmail-hub"
.\bin\bootstrap-local.ps1
```

Lo script:

1. verifica che DDEV risponda dentro `local-wp/`
2. sincronizza il plugin locale
3. attiva `valore24-smartmail-hub`
4. crea o aggiorna la pagina `smartmail-hub`
5. monta lo shortcode `[valore24_smartmail_hub]`

### URL completa per vedere il plugin in locale

Una volta completato il bootstrap locale, la pagina del plugin e:

```text
http://smartmail-hub-local.ddev.site/smartmail-hub/
```

In molti casi DDEV espone anche la variante HTTPS:

```text
https://smartmail-hub-local.ddev.site/smartmail-hub/
```

### Prerequisito per collegare account IMAP reali

Il plugin usa l'estensione PHP IMAP. Nel clone locale DDEV va quindi abilitato il modulo `imap` nel container web.

Questo progetto e gia predisposto con:

```yaml
webimage_extra_packages: ["php8.1-imap"]
```

Se aggiorni o ricrei l'ambiente, esegui:

```powershell
cd "C:\Users\MicheleVigliotta\Desktop\Projects\plugin-smartmail-hub\local-wp"
ddev restart
```

Per verificare che il modulo sia attivo:

```powershell
ddev exec php -m | findstr /I imap
```

### Collegare una casella IMAP di test al plugin locale

Se hai gia una mailbox IMAP che puoi usare, non serve toccare staging. Devi solo configurarla nel backend locale del plugin.

Backend locale:

```text
https://smartmail-hub-local.ddev.site/wp-admin/admin.php?page=v24-smartmail-hub
```

Compila il form `Nuovo account email` con questi dati:

- `Etichetta account`: nome libero, per esempio `Test Gmail` o `Inbox Demo`
- `Email account`: la casella completa
- `Nome mittente`: nome visualizzato in uscita
- `Host IMAP`: host del provider IMAP
- `Porta IMAP`: di solito `993`
- `Crittografia IMAP`: di solito `SSL/TLS`
- `Host SMTP`: host SMTP del provider, oppure comunque il valore del provider se vuoi tenere anche l'invio reale
- `Porta SMTP`: di solito `465` o `587`
- `Crittografia SMTP`: di solito `SSL/TLS` su `465`, `TLS` su `587`
- `Username`: quasi sempre l'email completa
- `Password o app password`: password casella o app password
- `Visibilita account`: `Solo utente corrente` o `Condiviso`

Poi fai:

1. `Salva account`
2. `Test IMAP/SMTP`
3. `Sync ora`
4. apri `https://smartmail-hub-local.ddev.site/smartmail-hub/`

### Modalita consigliata per test UI senza inviare email reali

Se vuoi leggere e sincronizzare una casella reale ma non vuoi spedire email vere dal locale:

1. vai sempre nella pagina admin del plugin locale
2. in `Impostazioni SmartMail Hub`
3. imposta `Trasporto invio` su `WordPress/server locale`
4. salva

In questa modalita:

- IMAP continua a servire per leggere e sincronizzare la mailbox
- l'invio dal composer passa da WordPress locale
- le mail in uscita finiscono in Mailpit, non verso destinatari reali

Mailpit locale:

```text
https://smartmail-hub-local.ddev.site:8026
```

Questa e la modalita piu sicura per lavorare sulla UI e testare il composer.

### Come funziona davvero la mail sul server

Il plugin sul server pubblico non usa una logica speciale diversa dal clone locale. Il flusso reale e questo:

1. l'account viene salvato in `wp_v24_smh_accounts`
2. `includes/Accounts/class-account-service.php` valida i campi e cifra `username` e `secret`
3. la cifratura passa da `includes/Security/class-encryption.php`
4. la chiave deriva da `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY` e `siteurl`
5. quando fai `Test IMAP/SMTP`, `V24_SMH_IMAP_Client` decifra le credenziali e prova `imap_open()`
6. quando fai `Sync`, `includes/REST/class-rest-accounts-controller.php` chiama `includes/Mail/class-mail-sync-service.php`
7. il sync legge le cartelle da IMAP, poi salva cartelle, messaggi, destinatari e allegati nelle tabelle `wp_v24_smh_*`

In pratica:

- lettura mailbox = IMAP vero
- invio email = SMTP diretto oppure `wp_mail`, in base a `v24_smh_settings.outbound_transport`
- UI frontend = legge il contenuto sincronizzato dal DB locale del plugin

### Perche un account puo rompersi in locale anche se i parametri sono giusti

Le credenziali non sono salvate in chiaro. Sono salvate cifrate con una chiave legata all'installazione WordPress.

Questo significa che una riga account salvata quando cambia uno di questi elementi puo diventare non piu leggibile:

- chiavi/salts di WordPress
- `siteurl`
- contesto dell'installazione locale

Quando succede, l'account resta visibile nel DB ma il plugin non riesce piu a decifrare `encrypted_username` o `encrypted_secret`.

Il sintomo tipico e uno di questi:

- `Credenziali IMAP mancanti`
- `AUTHENTICATIONFAILED`

Nel clone locale di questo progetto e stato verificato che:

- gli account `DevTeam` creati in precedenza non sono piu decifrabili
- un account creato nel contesto locale attuale viene invece cifrato e decifrato correttamente

Quindi il fix giusto non e copiare alla cieca la riga vecchia, ma ricreare o aggiornare l'account nel contesto chiave locale corrente.

### Script utili per capire e riparare il locale

Per vedere subito se gli account locali sono leggibili:

```powershell
cd "C:\Users\MicheleVigliotta\Desktop\Projects\plugin-smartmail-hub"
.\bin\inspect-local-accounts.ps1
```

Lo script mostra:

- `siteurl` e `home` del clone locale
- trasporto di invio attivo
- elenco account
- stato
- `username_decryptable`
- `secret_decryptable`
- eventuale `last_error`

Per riparare un account esistente mantenendo host e porte gia salvati, basta riscrivere le credenziali nel contesto locale corrente:

```powershell
cd "C:\Users\MicheleVigliotta\Desktop\Projects\plugin-smartmail-hub"
.\bin\save-local-account.ps1 -AccountId 1 -Username "dev.team@aioffice24.it" -Secret "LA_TUA_PASSWORD_O_APP_PASSWORD" -RunTest
```

Per creare o riallineare un account da email, aggiornando la riga piu recente e ripulendo i duplicati:

```powershell
cd "C:\Users\MicheleVigliotta\Desktop\Projects\plugin-smartmail-hub"
.\bin\save-local-account.ps1 `
  -EmailAddress "dev.team@aioffice24.it" `
  -Label "DevTeam" `
  -DisplayName "DevTeam" `
  -ImapHost "mail.aioffice24.it" `
  -ImapPort 993 `
  -ImapEncryption "ssl_tls" `
  -SmtpHost "mail.aioffice24.it" `
  -SmtpPort 465 `
  -SmtpEncryption "ssl_tls" `
  -Username "dev.team@aioffice24.it" `
  -Secret "LA_TUA_PASSWORD_O_APP_PASSWORD" `
  -OwnerType "shared" `
  -UpsertLatestByEmail `
  -DeleteOtherMatches `
  -RunTest
```

Se vuoi lanciare subito anche la sincronizzazione:

```powershell
.\bin\save-local-account.ps1 -AccountId 1 -Username "dev.team@aioffice24.it" -Secret "LA_TUA_PASSWORD_O_APP_PASSWORD" -RunTest -RunSync
```

Nota importante:

- questi script non recuperano la password dalla riga corrotta
- riscrivono solo nuove credenziali in modo compatibile con il clone locale attuale
- se la mailbox usa MFA o 2FA, serve una `app password`

Opzioni utili:

```powershell
.\bin\bootstrap-local.ps1 -SkipSync
.\bin\bootstrap-local.ps1 -SkipPage
```

### Workflow quotidiano

1. apri Docker Desktop
2. entra in `local-wp/`
3. esegui `ddev start`
4. modifica il codice nella root del repo
5. riesegui `.\bin\sync-plugin.ps1`
6. ricarica la pagina locale
7. quando hai finito, opzionale: `ddev stop`

## Note pratiche sul plugin

- la UI pubblica non arriva dal tema WordPress, ma dal plugin
- l'entry frontend parte da `public/class-shortcodes.php`
- i template chiave sono `templates/app.php`, `templates/login.php` e `templates/forbidden.php`
- i CSS chiave sono `assets/css/app.css` e `assets/css/ai.css`
- i JS chiave sono `assets/js/app.js` e `assets/js/ai.js`
- il namespace REST del plugin e `v24-ai-office/v1/smartmail`
- la pagina frontend usa utenti WordPress e capability custom per autorizzare l'accesso
- molti dati reali dipendono da tabelle e configurazioni del database WordPress
- nella copia remota sono presenti molti file `.bak-*`; sono stati mantenuti anche qui per allineamento con il server
- esiste anche una copia staging del plugin in `/home/staging/public_html/mail/wp-content/plugins/valore24-smartmail-hub`, ma e piu vecchia (`0.1.4`) e non rappresenta la UI pubblica corrente

## File locali da non committare

Sono ignorati o da lasciare fuori repo:

- `.env`
- `local-wp/`
- altre directory WordPress locali (`wordpress/`, `wp/`)
- zip, tar, log e file temporanei

## Origine del contenuto

Questa repo locale e stata costruita copiando il plugin da:

```text
/home/aioff24/public_html/mail/wp-content/plugins/valore24-smartmail-hub
```
