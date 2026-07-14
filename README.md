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
3. **Konfiguration:** `api/config.sample.php` → als `api/config.php` auf den Server
   legen und mit echten DB-/SMTP-Werten, `base_url` und `'env' => 'production'` füllen.
   **`config.php` niemals committen** (steht in `.gitignore`).
4. **Deployment:** Bei Push auf `main` baut GitHub Actions und lädt via SFTP hoch.

### Benötigte GitHub-Secrets (Settings → Secrets → Actions)

| Secret                        | Beschreibung                                  |
|-------------------------------|-----------------------------------------------|
| `HOSTPOINT_SFTP_SERVER`       | SFTP-Host (z. B. `ftp.deine-domain.ch`)       |
| `HOSTPOINT_SFTP_USERNAME`     | SFTP-Benutzername                             |
| `HOSTPOINT_SFTP_PASSWORD`     | SFTP-Passwort                                 |
| `HOSTPOINT_SFTP_PORT`         | i. d. R. `22`                                 |
| `HOSTPOINT_REMOTE_PATH`       | Ziel-Web-Root (z. B. `/home/…/www`)           |

Der Deploy schliesst `config.php` bewusst aus, damit die Server-Konfiguration
über Deployments hinweg bestehen bleibt.

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
