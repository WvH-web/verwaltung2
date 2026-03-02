# WvH Abrechnungssystem – Installationsanleitung

## System-Anforderungen
- PHP 8.1+
- MySQL 8.0 (Percona)
- Apache mit mod_rewrite, mod_headers

---

## Installation (Schritt für Schritt)

### 1. Datenbank einrichten
1. Öffne **phpMyAdmin** in Plesk
2. Wähle die Datenbank `wvhdata1`
3. Importiere **zuerst**: `config/schema.sql` (Haupt-Schema)
4. Importiere **danach**: `config/setup_auth.sql` (Login-Tabelle + Admin-User)

### 2. Dateien hochladen
Lade den gesamten Inhalt dieses Ordners hoch nach:
```
https://deutsche-online-schule.com/schooltools/teachersbilling/
```

**Verzeichnisstruktur auf dem Server:**
```
teachersbilling/
├── .htaccess          ← unbedingt hochladen!
├── config/
├── src/
├── templates/
├── public/            ← alle .php Seiten
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── assets/
│   ├── admin/
│   └── profile/
├── uploads/           ← muss beschreibbar sein (chmod 755)
└── logs/              ← muss beschreibbar sein (chmod 755)
```

### 3. Verzeichnis-Berechtigungen
In Plesk / SSH:
```bash
chmod 755 uploads/ logs/
```

### 4. Erster Login
- URL: `https://deutsche-online-schule.com/schooltools/teachersbilling/`
- E-Mail: `admin@wvh-online.com`
- Passwort: `WvH@dmin2026!`

**⚠️ SOFORT nach dem ersten Login das Passwort ändern!**

---

## Verzeichnis-Erklärung

| Ordner | Beschreibung |
|--------|-------------|
| `config/` | Datenbank- & App-Konfiguration, SQL-Dateien |
| `src/Auth/` | Login, Session, Rollenprüfung |
| `src/Helpers/` | Hilfsfunktionen (escape, formatierung, etc.) |
| `templates/layouts/` | Header/Footer Templates |
| `public/` | Alle öffentlich erreichbaren PHP-Seiten |
| `public/assets/` | CSS, JS, Bilder |
| `uploads/` | Lehrer-Dokumente (nicht öffentlich) |
| `logs/` | PHP-Fehlerlog (nicht öffentlich) |

---

## Rollen

| Rolle | Berechtigung |
|-------|-------------|
| **Administrator** | Vollzugriff, Benutzerverwaltung |
| **Verwaltung** | Abrechnung, Deputate, Wise-Export |
| **Lehrer/in** | Eigener Stundenplan, Vertretungen, Abrechnung einsehen |

---

## Sicherheit
- Passwörter: bcrypt (cost=12)
- Sessions: httponly, secure, SameSite=Strict
- CSRF-Schutz auf allen Formularen
- Brute-Force-Schutz (5 Versuche → 15 Min Sperre)
- Audit-Log aller Änderungen
- .htaccess sperrt config/, src/, logs/, uploads/
