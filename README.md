# 🍽️ ViscosiGuide

Ein nostalgischer **Food- & Restaurant-Guide** für Viscosi Emmenbrücke (und überall
sonst). Interaktive Karte aller Food-Spots, Bewertungen (Rating · Preis · *Bang for
the Buck*), eigene Spots erfassen, Konten mit E-Mail-Login — und **Food-Buddies**:
sag, worauf du Lust hast, andere joinen oder schlagen einen Spot vor. Mit
E-Mail-Benachrichtigungen.

## Architektur

| Schicht    | Technologie                                                    |
|------------|----------------------------------------------------------------|
| Frontend   | **Astro** (statischer Build), TypeScript, Leaflet-Karte (gebundelt, kein CDN) |
| Backend    | **PHP 8** JSON-API unter `/api`, PDO Prepared Statements       |
| Datenbank  | **MariaDB/MySQL** (Produktion) · SQLite (lokale Entwicklung)   |
| Mail       | PHPMailer über SMTP (HostPoint) · Datei-Log im Dev-Modus       |
| Hosting    | HostPoint Shared Hosting (PHP + MariaDB)                        |
| Deployment | GitHub Actions → SFTP-Upload                                    |

Der statische Astro-Build und der `api/`-Ordner liegen zusammen im Web-Root.
`.htaccess` routet `/api/*` an `api/index.php` und liefert sonst die statischen Seiten.

```
frontend/   Astro-Projekt (Seiten, Komponenten-Skripte, Styles, API-Client)
api/        PHP-API (index.php Router, lib/ Kern, routes/ Endpunkte)
db/         schema.sql (MariaDB) + seed.sql (Demo-Daten)
bin/        Dev-Helfer (init-db.php, router.php)
.github/    Deploy-Workflow
```

## Lokale Entwicklung / Verifizierung

Voraussetzungen: PHP 8.1+ (mit `pdo_sqlite`), Node 20+, Composer.

```bash
# 1) Abhängigkeiten
composer install
(cd frontend && npm install)

# 2) Lokale SQLite-DB anlegen + Demo-Daten
APP_ENV=dev DB_DRIVER=sqlite php bin/init-db.php --seed

# 3) Frontend bauen
(cd frontend && npm run build)

# 4) App starten (PHP built-in server spielt Apache + statische Dateien)
APP_ENV=dev DB_DRIVER=sqlite php -S localhost:8000 -t frontend/dist bin/router.php
# → http://localhost:8000
```

Im Dev-Modus (`APP_ENV=dev`) werden **E-Mails in `api/var/mail.log` geschrieben**
statt versendet — dort findest du Verifizierungs- und Reset-Links sowie
Buddy-Benachrichtigungen.

Alternativ für schnelles Frontend-Iterieren: `cd frontend && npm run dev` und
`PUBLIC_API_BASE=http://localhost:8000/api` setzen (Cookies/CSRF funktionieren
wegen SameSite=Strict aber am besten über den kombinierten Server oben).

## Produktion auf HostPoint

1. **Datenbank:** In der HostPoint-Systemverwaltung eine MariaDB-Datenbank + Benutzer
   anlegen. `db/schema.sql` via phpMyAdmin importieren (optional `db/seed.sql`).
2. **E-Mail:** Ein Postfach (z. B. `noreply@deine-domain.ch`) erstellen. HostPoint-SMTP:
   `asmtp.mail.hostpoint.ch`, Port `587`, STARTTLS.
3. **SSH-Key:** In der HostPoint-Verwaltung SSH/SFTP aktivieren und den **öffentlichen
   Schlüssel** deines Deploy-Keys hinterlegen. Den **privaten** Schlüssel als
   GitHub-Secret `HOSTPOINT_SSH_PRIVATE_KEY` speichern.
4. **Konfiguration:** Alle DB- und Mail-Werte werden als GitHub-Secrets hinterlegt
   (siehe unten). Der Deploy generiert daraus beim Upload automatisch die
   `api/config.php` auf dem Server — **kein manuelles Editieren nötig, keine Secrets
   im Repo**. (`api/config.sample.php` bleibt als Referenz / für lokale Nutzung.)
5. **Deployment:** Bei Push auf `main` (oder manuell via *Run workflow*) baut GitHub
   Actions und lädt via **SFTP über SSH-Key** hoch.

### Benötigte GitHub-Secrets (Settings → Secrets and variables → Actions)

**Deployment (SFTP über SSH-Key):**

| Secret                      | Beschreibung                                        |
|-----------------------------|-----------------------------------------------------|
| `HOSTPOINT_SFTP_SERVER`     | SSH/SFTP-Host (z. B. `deine-domain.ch`)             |
| `HOSTPOINT_SFTP_USERNAME`   | SSH-Benutzername                                    |
| `HOSTPOINT_SSH_PRIVATE_KEY` | **Privater** SSH-Schlüssel (kompletter PEM-Inhalt)  |
| `HOSTPOINT_SFTP_PORT`       | i. d. R. `22`                                       |
| `HOSTPOINT_REMOTE_PATH`     | Ziel-Web-Root (z. B. `/home/…/www`)                 |

**App-Konfiguration (wird in `api/config.php` gebacken):**

| Secret             | Beschreibung                                      |
|--------------------|---------------------------------------------------|
| `APP_BASE_URL`     | Öffentliche URL, z. B. `https://viscosiguide.ch`  |
| `DB_HOST`          | DB-Host (meist `localhost`)                       |
| `DB_PORT`          | DB-Port (optional, Standard `3306`)               |
| `DB_NAME`          | Datenbankname                                     |
| `DB_USER`          | Datenbank-Benutzer                                |
| `DB_PASSWORD`      | Datenbank-Passwort                                |

**Mail-Einstellungen (SMTP):**

| Secret              | Beschreibung                                          |
|---------------------|-------------------------------------------------------|
| `MAIL_FROM_EMAIL`   | Absenderadresse, z. B. `noreply@viscosiguide.ch`      |
| `MAIL_FROM_NAME`    | Absendername (optional, Standard `ViscosiGuide`)      |
| `SMTP_HOST`         | SMTP-Server, z. B. `asmtp.mail.hostpoint.ch`          |
| `SMTP_PORT`         | SMTP-Port (optional, Standard `587`)                  |
| `SMTP_ENCRYPTION`   | `tls` (STARTTLS, Standard) oder `ssl`                  |
| `SMTP_USERNAME`     | Postfach-Login (meist = Absenderadresse)              |
| `SMTP_PASSWORD`     | Postfach-Passwort                                     |

Die generierte `api/config.php` wird durch `.htaccess` vor Web-Zugriff geschützt und
steht nie im Git-Repo. Sonderzeichen in Passwörtern werden sicher via `var_export`
escaped (siehe `bin/make-config.php`).

## Sicherheit

- PDO Prepared Statements durchgängig, keine String-SQL.
- Passwörter mit **Argon2id** gehasht; Login/Registrierung/Reset **rate-limited**.
- Sessions mit `HttpOnly; Secure; SameSite=Strict`, ID-Regenerierung beim Login,
  Idle-Timeout.
- **CSRF-Token** (Header `X-CSRF-Token`, konstant-zeitlicher Vergleich) für alle
  schreibenden Requests.
- Verify-/Reset-Token zufällig, **gehasht gespeichert**, einmalig, ablaufend.
  Registrierung & Passwort-Reset sind **enumeration-sicher** (generische Antworten).
- Strikte Security-Header inkl. **CSP** (`script-src 'self'`), `X-Frame-Options: DENY`,
  HSTS. Leaflet & Assets selbst gehostet — keine Dritt-CDNs.
- Sensible Pfade (`config.php`, `api/lib`, `api/routes`, `db/`, `bin/`) per `.htaccess`
  gesperrt.
