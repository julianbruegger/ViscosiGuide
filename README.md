# 🍽️ ViscosiGuide

Ein nostalgischer **Food- & Restaurant-Guide** für Viscosi Emmenbrücke (und überall
sonst). Interaktive Karte aller Food-Spots, Bewertungen (Rating · Preis · *Bang for
the Buck*), eigene Spots erfassen, Konten mit E-Mail-Login — und **Food-Buddies**:
sag, worauf du Lust hast, andere joinen oder schlagen einen Spot vor. Mit
E-Mail-Benachrichtigungen.

### Features

- **Restaurant-Index rund um Viscosi** — Demo-Seed mit Spots rund um die
  Viscosistrasse / Emmenbrücke. Jeder Spot hat ein **Emoji-Logo** und einen
  **Link zum Standort** (Google-Maps-Pin, oder eine eigene kuratierte URL).
  Logos sind Emoji/Text (kein `<img>`), damit die strikte CSP intakt bleibt;
  ohne Logo wird ein farbiges Monogramm aus den Initialen erzeugt.
- **Grill-Angebote mit Essensbestellung** — eine Food-Buddy-Anfrage kann vom Typ
  `lunch` oder `grill` sein. Bei einem Grill bestellt jede/r Teilnehmer/in
  **Rind, Schwein, Vegi oder etwas Eigenes** und kann das Flag „bringe ich selbst
  mit“ setzen. Der Host sieht eine Bestell-Übersicht (Zählung pro Sorte).
- **Angebote laufen ab** — Food-Buddy-Angebote verfallen automatisch am **Ende des
  Geschäftstags** (heute 23:59:59). Abgelaufene Angebote verschwinden aus der Liste
  und lassen sich nicht mehr joinen/bestellen.

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
   Für eine **bestehende** DB (aus einer früheren Version) einmalig die Migration
   `db/migrations/001_grill_logos_expiry.sql` einspielen — sie ergänzt Logos,
   Standort-Links, Grill-Typ/Bestellungen und die Ablauf-Spalte.
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
   Actions und deployt via **rsync über SSH** (SSH-User + Key, kein SFTP-Tool).

### Benötigte GitHub-Secrets (Settings → Secrets and variables → Actions)

**Deployment (SSH-User + Key, rsync):**

| Secret                      | Beschreibung                                        |
|-----------------------------|-----------------------------------------------------|
| `HOSTPOINT_SSH_HOST`        | SSH-Host (z. B. `deine-domain.ch`)                  |
| `HOSTPOINT_SSH_USER`        | SSH-Benutzername                                    |
| `HOSTPOINT_SSH_PRIVATE_KEY` | **Privater** SSH-Schlüssel (kompletter PEM-Inhalt)  |
| `HOSTPOINT_SSH_PORT`        | i. d. R. `22` (optional, Standard `22`)             |
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
