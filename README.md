# Statist

Self-hosted, privacy-first web analytics. Drop one `<script>` tag on your site — get a clean dashboard with visitors, pages, countries, referrers and session timelines. No cookies, no third parties, no data leaving your server.

---

## Features

- **~1 KB tracker** — single script tag, no dependencies, async
- **No cookies** — sessions via `localStorage` UUID, GDPR-friendly by default
- **Bot filtering** — UA blocklist + heartbeat validation + datacenter IP ranges (Vultr, DigitalOcean, Alibaba Cloud SG and others). Suspicious sessions are flagged, not deleted
- **Geo data** — country and city via local MaxMind GeoLite2 database, no external API calls
- **Multi-site** — one installation, unlimited tracked domains
- **Session timeline** — full per-visitor event trail: pages, clicks, duration, referrer, screen, language
- **Role-based access** — `admin`, `viewer`, `site_viewer` (per-site read access)
- **Multilingual UI** — English, Russian, French out of the box. Add any language by dropping a JSON file into `lang/`
- **Web installer** — 4-step wizard sets up the database, creates the admin account, patches the tracker URL and self-destructs

---

## Quick start

### 1. Upload files

Clone or download the repository and upload everything to your server.

```bash
git clone https://github.com/yourname/statist.git
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
| **Install** | Writes `inc/db.php`, imports schema, creates admin, patches `tracker.js` with your domain, writes lock file |

After finishing, click **Delete installer & go to dashboard**. The file removes itself. If it can't, delete `install.php` manually — `storage/installed.lock` prevents re-running regardless.

### 4. Add the tracker to your site

The snippet is shown in the dashboard under **Sites**. It looks like:

```html
<script src="https://your-domain.com/tracker.js" async></script>
```

Paste it before `</body>` on every page you want to track. That's it.

---

## Manual installation (without the wizard)

If you prefer to configure things yourself:

```bash
# 1. Import schema
mysql -u stats_usr -p stats < install.sql

# 2. Copy and edit the database config
cp inc/db.php.example inc/db.php
# edit inc/db.php with your credentials

# 3. Set tracker endpoint in tracker.js
# Change ENDPOINT to your domain
```

Default admin credentials after `install.sql`: **login** `admin` / **password** `changeme` — change immediately.

---

## GeoIP setup (optional)

Without this, country and city data won't appear in the dashboard.

1. Register free at [maxmind.com](https://www.maxmind.com/en/geolite2/signup)
2. Download **GeoLite2-City.mmdb**
3. Place it at `storage/geo/GeoLite2-City.mmdb`

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
    location = /tracker.js          { try_files $uri =404; }
    location ~* ^/storage/flags/    { try_files $uri =404; }

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

Manage users at `/list/users.php`. Manage tracked sites (add, rename, delete, assign users, copy snippet) at `/list/sites.php`.

---

## Bot filtering

Statist flags suspicious traffic as `is_bot = 1` and hides it from all dashboard metrics. Flagged sessions are kept in the database — you can query them manually anytime.

A session gets flagged when **any** of these conditions are met:

- The user-agent string matches a known bot, crawler, or tool pattern
- The session lasted ≤ 1 second with no heartbeat received (`is_valid = 0`)
- The source IP falls within a known datacenter CIDR range

Currently blocked datacenter ranges include Vultr Singapore, DigitalOcean, Linode/Akamai, OVH SG, Alibaba Cloud SG, and Tencent Cloud SG. To extend the list or add custom UA patterns, edit `app/BotDetector.php`.

---

## Adding a language

1. Copy `lang/en.json` to `lang/{code}.json` (e.g. `lang/de.json`)
2. Translate all values — keys must stay in English
3. The new language appears automatically in the login page switcher and in user settings

The display name for the language selector is defined in `inc/Lang.php` under `NAMES`. Add your language code there if it's not already listed.

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
├── index.php              # Tracker endpoint (POST) / install redirect (GET)
├── tracker.js             # ~1 KB client-side tracker
├── install.php            # Web installer (self-deletes after setup)
├── install.sql            # Full schema for fresh installs
├── migrate.sql            # Safe migration for existing installs
├── .htaccess              # Apache rewrite rules
│
├── app/
│   ├── BotDetector.php    # Two-stage bot detection
│   ├── GeoService.php     # MaxMind GeoLite2 wrapper
│   └── SessionService.php # Core tracking and session logic
│
├── inc/
│   ├── db.php             # PDO connection (auto-generated by installer)
│   ├── db.php.example     # Template for manual setup
│   ├── Lang.php           # i18n singleton
│   └── flags.php          # Flag image helpers
│
├── lang/
│   ├── en.json            # English
│   ├── ru.json            # Russian
│   └── fr.json            # French
│
├── list/                  # Admin panel
│   ├── index.php
│   ├── auth.php           # Login (DB-backed, language-aware)
│   ├── dashboard.php      # Analytics overview
│   ├── session.php        # Per-session event timeline
│   ├── sites.php          # Site management
│   ├── users.php          # User management
│   ├── settings.php       # Language & password settings
│   └── logout.php
│
├── lib/
│   └── geoip/             # MaxMind GeoIP2 PHP library
│
└── storage/
    ├── flags/             # Your flag .webp files go here
    ├── geo/               # GeoLite2-City.mmdb goes here
    └── logs/              # statist.log (auto-created)
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
- [ ] Dark mode
- [ ] Weekly digest emails
- [ ] IPv6 datacenter ranges in BotDetector
- [ ] Automated GeoLite2 update script
- [ ] API token access

---

## License

MIT
