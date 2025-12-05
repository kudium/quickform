# QuickForm — Simple, Secure, No‑DB Forms

QuickForm is a tiny, file‑based form backend and UI. Collect submissions from a public form or a JSON API, view them in a dashboard, and export CSV — all without a database.

![Dashboard](docs/dashboard.png)

## Features

- File‑based storage: per‑user folders, no DB to run
- Public form: share a link, collect responses
- JSON API: submit programmatically (supports base64 file uploads)
- Dashboard: view submissions, download CSV
- Auth built‑in: per‑user access to forms/data
- Runs anywhere PHP runs (shared hosting friendly)

## Quick Start

Requirements
- PHP 8.1+
- Writable project directory (to create `users/` etc.)

Run locally
1) Clone and start a server
   git clone https://github.com/kudium/quickform
   cd quickform
   php -S localhost:8000 -t public public/router.php   # pretty routes
   # or: php -S localhost:8000 -t public

2) Open http://localhost:8000 and create an account
3) Create a form from the dashboard

## API

- Endpoint: `POST /public/api/submit` (pretty routes) or `POST /api_submit.php` (direct script)
- JSON body
  {
    "user": "<username>",
    "form": "<form-slug>",
    "api_key": "<form-api-key>",
    "data": { "fullName": "Jane Doe", "avatar": "data:image/png;base64,..." }
  }
- Minimal cURL
  curl -X POST \
    -H "Content-Type: application/json" \
    -d '{"user":"<u>","form":"<slug>","api_key":"<key>","data":{"fullName":"Jane"}}' \
    http://localhost:8000/api_submit.php

Notes
- File fields accept raw base64 or data URLs; files are saved to `uploads/` and linked in CSV.

## Storage

- users/
  - <username>/
    - config.php
    - forms/
      - <slug>/
        - form.json
        - data.csv
        - uploads/

## Security

- Encrypted at rest: CSV lines are AES‑encrypted with a key derived from user credentials
- Decrypt on view: dashboard decrypts after login; direct CSV stays encrypted
- Export options: download encrypted CSV or decrypted CSV (requires auth)

## Deploy

- Webroot: point to `public/`
- Apache: use provided `public/.htaccess` for pretty routes
- No rewrites? Use direct script endpoints like `/api_submit.php`

## Contributing

PRs welcome — please keep changes focused and consistent with the simple, file‑based approach.

## License

MIT — see `LICENSE`.

