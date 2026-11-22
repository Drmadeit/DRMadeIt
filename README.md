# drmadeit.com

Link hub, tool lists, file shop and shop calculators for D.R. Made It.
Plain PHP 8.2+ and SQLite. No framework, no build step, no monthly fees.

## Layout

```
index.php        router (every request goes through here)
.htaccess        sends requests to index.php, blocks app/ and views/
app/             code: db + schema, auth, image resizing, admin
views/           page templates (views/admin = admin screens)
assets/          css, js, default logo + header photo
```

**Data lives outside the repo**, in `drmadeit-data/` next to `public_html`:
the SQLite database, uploaded photos, download files, sessions and backups.
A deploy can never overwrite it. Change the location with the `DRM_DATA_DIR` env var.

## Deploy (Hostinger)

Hostinger → site → Advanced → Git: this repo, branch `main`, install path blank.
Turn on Auto Deployment and add its webhook in GitHub. PHP 8.2+.

## First login

1. Open `/admin`. It asks for a setup code.
2. Hostinger → File Manager → `drmadeit-data/SETUP-CODE.txt` has the code.
3. Enter it, pick a username and password (12+ characters). The code file is then deleted.

Login locks for 15 minutes after 5 wrong tries.

## Run locally

```
DRM_DATA_DIR=/tmp/drm-data php -S 127.0.0.1:8000 index.php
```

## What's here

| Public | Admin (`/admin`) |
|---|---|
| `/` link hub (wood header, logo, socials, buttons) | Dashboard: downloads, new emails, top files |
| `/about` | Links: add, photo, drag order, hide, feature |
| `/tools`, `/tools/<list>` Amazon tool lists | Tools: lists + items with photos and Amazon links |
| `/files`, `/files/<item>` CNC / 3D files, free (email) or paid (buy link) | Files: photos, download file, price, buy link, draft/live |
| `/videos` latest YouTube + pinned | Videos: channel, pin, hide, show on home |
| `/calc` shop calculators + DXF polyline fixer | Emails: list, CSV export, delete |
| | Settings: profile, socials, about, email (SMTP), Amazon tag, licenses, backup, password |

- **Free downloads:** visitor enters email → gets a link good for 5 downloads / 48 hours. Files are served by PHP from `drmadeit-data/files`, never by direct URL.
- **Email:** Settings → Email. Hostinger: `smtp.hostinger.com`, port `465`, the mailbox address and password. Use "Send test email".
- **Big files:** upload with Hostinger File Manager into `drmadeit-data/files`, then pick it on the file's admin page.
- **Backups:** one a day automatically (keeps 14) in `drmadeit-data/backups`, plus "Download backup now" in Settings.
- **YouTube:** public RSS feed, cached 1 hour. No API key.
- **Calculators** run in the browser (`assets/js/calc.js`, `assets/js/dxf.js`). Nothing is uploaded.

## Build phases

- [x] 1. Foundation + link hub
- [x] 2. Tool lists
- [x] 3. File shop, email-gated downloads, subscriber CSV
- [x] 4. Videos, backups, share previews
- [x] Shop calculators + DXF polyline fixer
- [ ] Later: sheet-goods cut list optimizer, login code by email
