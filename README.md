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
ddev wp core install --url=http://smartmail-hub-local.ddev.site --title="SmartMail Hub Local" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
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
