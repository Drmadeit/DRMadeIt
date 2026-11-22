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

## Build phases

- [x] 1. Foundation + link hub: home, about, admin login, links (photos, drag order, hide, feature), settings
- [ ] 2. Tool lists (`/tools`)
- [ ] 3. File shop (`/files`): free email-gated downloads, buy links, subscriber CSV
- [ ] 4. Videos (`/videos`), backups, polish
- [ ] Shop calculators (`/calc`) + DXF polyline fixer
