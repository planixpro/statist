# Statist

Self-hosted, privacy-first web analytics. Drop one `<script>` tag on your site — get a clean dashboard with visitors, pages, countries, referrers and session timelines. No cookies, no third parties, no data leaving your server.

---

## Features

- **~1 KB tracker** — single script tag (`assets/js/s.js`), no dependencies, async, heartbeat-based session validation
- **No cookies** — sessions via `localStorage` UUID, GDPR-friendly by default
- **Dark mode** — full dark theme, toggle in the top bar, persisted via `localStorage`
- **Bot filtering** — UA blocklist + scoring engine + datacenter ASN/CIDR ranges. Suspicious sessions are flagged, not deleted. Auto-block after repeated bot activity from same IP
- **IP / subnet / ASN blocking** — manual and automatic rules manageable from Settings
- **Geo data** — country, city and ASN via local MaxMind GeoLite2 database, no external API calls
- **Multi-site** — one installation, unlimited tracked domains
- **Session timeline** — full per-visitor event trail: pages, clicks, duration, referrer, screen, language
- **Role-based access** — `admin`, `viewer`, `site_viewer` (per-site read access)
- **Multilingual UI** — 12 languages built-in: English, Russian, Bulgarian, Ukrainian, German, Spanish, French, Italian, Portuguese, Chinese, Japanese, Hindi. Add any language by dropping a JSON file into `lang/`
- **Remember me** — persistent login via secure token stored in `user_sessions` table
- **Web installer** — 4-step wizard sets up the database, creates the admin account, patches the tracker URL and self-destructs

---

## Quick start

### 1. Upload files

Clone or download the repository and upload everything to your server.

```bash
git clone https://github.com/planixpro/statist.git
```

### 2. Create a database

```sql
CREATE DATABASE stats CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'stats_usr'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON stats.* TO 'stats_usr'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Run the installer

Open `https://your-domain.com/install.php` in a browser and follow the 4-step wizard:

| Step | What happens |
|------|-------------|
| **Requirements** | Checks PHP version, required extensions, directory write permissions |
| **Database** | Enter credentials — connection is tested live before proceeding |
| **Admin account** | Choose login and password for the first admin user |
| **Install** | Writes `inc/db.php`, imports schema, creates admin, patches `s.js` with your domain, writes lock file |

After finishing, click **Delete installer & go to dashboard**. The file removes itself. If it can't, delete `install.php` manually — `storage/installed.lock` prevents re-running regardless.

### 4. Add the tracker to your site

The snippet is shown in the dashboard under **Sites**. It looks like:

```html
<script src="https://your-domain.com/assets/js/s.js" defer></script>
```

Paste it before `</body>` on every page you want to track. That's it.

---

## Manual installation (without the wizard)

```bash
# 1. Import schema
mysql -u stats_usr -p stats < install.sql

# 2. Copy and edit the database config
cp inc/db.php.example inc/db.php
# edit inc/db.php with your credentials

# 3. Set tracker endpoint in assets/js/s.js
# Change the `endpoint` constant to your domain
```

Default admin credentials after `install.sql`: **login** `admin` / **password** `changeme` — change immediately.

---

## GeoIP setup (optional)

Without this, country and city data won't appear in the dashboard.

1. Register free at [maxmind.com](https://www.maxmind.com/en/geolite2/signup)
2. Download **GeoLite2-City.mmdb** and **GeoLite2-ASN.mmdb**
3. Place them at `storage/geo/`

ASN data is used both for geo display and for bot/datacenter detection.

---

## Nginx config

Apache is configured via `.htaccess`. For nginx:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/statist;
    index index.php;

    # Static files
    location ~* ^/assets/              { try_files $uri =404; }
    location ~* ^/storage/flags/       { try_files $uri =404; }

    # Admin panel
    location ^~ /list/ {
        try_files $uri $uri/ /list/index.php?$query_string;
    }

    # Tracker endpoint
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## User roles

| Role | Dashboard | Scope | Manage users & sites |
|------|-----------|-------|----------------------|
| `admin` | ✅ | All sites | ✅ |
| `viewer` | ✅ | All sites | ❌ |
| `site_viewer` | ✅ | Assigned sites only | ❌ |

Manage users at `/list/users`. Manage tracked sites (add, rename, delete, assign users, copy snippet) at `/list/sites`.

---

## Bot filtering

Statist uses a two-stage bot detection pipeline.

**Stage 1 — Realtime block** (before the session is saved): requests from known bot user-agents or headless browsers are rejected immediately.

**Stage 2 — Scoring** (after each event): sessions accumulate a bot score based on UA analysis, fingerprint quality, screen dimensions, session duration, event count, and datacenter ASN. Sessions scoring ≥ 15 are marked `is_bot = 1` and hidden from all dashboard metrics. Scores 8–14 are marked `is_suspicious`. Flagged sessions are kept in the database and visible in the sessions table.

**Auto-block**: if 10+ bot sessions arrive from the same IP within 3 days, the IP is automatically added to `blocked_ips`.

To extend UA patterns or adjust score weights, edit `app/BotDetector.php`.

---

## Block rules

Admins can manage block rules from **Settings → Block rules**:

- **IP** — block a single IPv4 or IPv6 address
- **Subnet** — block a CIDR range (e.g. `203.0.113.0/24`)
- **ASN** — block an entire autonomous system (e.g. `AS14061` for DigitalOcean)

Rules can also be added manually from the session detail page (block IP or entire /24 subnet with one click).

---

## Adding a language

1. Copy `lang/en.json` to `lang/{code}.json` (e.g. `lang/tr.json`)
2. Translate all values — keys must stay in English
3. The new language appears automatically in the login page switcher and in user settings

The display name for the language selector is defined in `inc/Lang.php` under `NAMES`. Add your language code there if it's not already listed.

**Built-in languages:** `en` · `ru` · `bg` · `uk` · `de` · `es` · `fr` · `it` · `pt` · `zh` · `ja` · `hi`

---

## Country flags

Flags are shown next to countries in the dashboard and on session detail pages. They are **not included** in the repository — add your own `.webp` files:

```
storage/flags/de.webp
storage/flags/us.webp
storage/flags/ru.webp
...
```

Files are named by **lowercase ISO 3166-1 alpha-2** country code. Language codes that differ from country codes are mapped automatically (`en` → `gb`, `zh` → `cn`, `ja` → `jp`, etc.) — see `inc/flags.php` for the full map.

If a flag file is missing, a two-letter code badge is shown as fallback.

---

## Project structure

```
/
├── index.php              # Tracker endpoint (POST /api/collect) / install redirect (GET)
├── install.php            # Web installer (self-deletes after setup)
├── install.sql            # Full schema for fresh installs
├── .htaccess              # Apache rewrite rules
│
├── app/
│   ├── BotDetector.php    # Two-stage bot detection and scoring
│   ├── BlockService.php   # IP / subnet / ASN block rule engine
│   ├── GeoService.php     # MaxMind GeoLite2 wrapper (city + ASN)
│   └── SessionService.php # Core tracking, session lifecycle, auto-block
│
├── assets/
│   ├── css/
│   │   ├── app.css        # Global layout, sidebar, dark theme variables
│   │   ├── auth.css       # Login page
│   │   ├── dashboard.css  # Dashboard-specific styles
│   │   ├── session.css    # Session detail page
│   │   ├── settings.css   # Settings page
│   │   ├── sites.css      # Sites management page
│   │   ├── stats.css      # Stats drill-down page
│   │   └── users.css      # Users management page
│   ├── img/
│   │   └── favicons/      # Cached referrer favicons (auto-fetched)
│   └── js/
│       ├── app.js         # Sidebar, theme toggle, auto-refresh
│       ├── dashboard.js   # Chart.js rendering
│       └── s.js           # ~1 KB client-side tracker
│
├── inc/
│   ├── db.php             # PDO connection (auto-generated by installer)
│   ├── csrf.php           # CSRF token helpers
│   ├── Lang.php           # i18n singleton
│   ├── flags.php          # Flag image helpers
│   └── helpers.php        # e(), admin_url(), is_valid_domain_name(), etc.
│
├── lang/
│   ├── en.json            # English
│   ├── ru.json            # Russian
│   ├── bg.json            # Bulgarian
│   ├── uk.json            # Ukrainian
│   ├── de.json            # German
│   ├── es.json            # Spanish
│   ├── fr.json            # French
│   ├── it.json            # Italian
│   ├── pt.json            # Portuguese
│   ├── zh.json            # Chinese
│   ├── ja.json            # Japanese
│   └── hi.json            # Hindi
│
├── lib/
│   └── geoip/             # MaxMind GeoIP2 PHP library (vendored)
│
├── list/                  # Admin panel
│   ├── index.php          # Redirect to dashboard
│   ├── auth.php           # Login, remember-me, rate limiting
│   ├── dashboard.php      # Analytics overview with period/country filters
│   ├── session.php        # Per-session event timeline + block actions
│   ├── sites.php          # Site management (add, rename, delete, assign users)
│   ├── stats.php          # Drill-down stats (pages, countries, browsers, etc.)
│   ├── users.php          # User management
│   ├── settings.php       # Language, password, block rules
│   └── logout.php
│
├── views/                 # View templates (included by list/ controllers)
│   ├── layout.php
│   ├── auth.view.php
│   ├── dashboard.view.php
│   ├── session.view.php
│   ├── settings.view.php
│   ├── sites.view.php
│   ├── stats.view.php
│   └── users.view.php
│
└── storage/
    ├── flags/             # Your flag .webp files go here
    ├── geo/               # GeoLite2-City.mmdb and GeoLite2-ASN.mmdb go here
    └── logs/              # statist.log and error.log (auto-created)
```

---

## Requirements

- PHP 7.4+
- MySQL 8.0+ or MariaDB 10.4+
- Apache with `mod_rewrite`, or nginx
- Extensions: `pdo_mysql`, `json`, `mbstring`

---

## Contributing

PRs and issues welcome. Ideas for future versions:

- [ ] Bounce rate
- [ ] CSV / JSON data export
- [ ] Weekly digest emails
- [ ] IPv6 datacenter ranges in BotDetector
- [ ] Automated GeoLite2 update script
- [ ] API token access

---

## License

MIT
