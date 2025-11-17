# amo_doc_generator

This repository contains a lightweight PHP integration that pulls amoCRM deal/contact data and renders `.docx` documents (orders, acts, etc.) using PhpWord templates. Everything (OAuth, API requests, template filling) runs inside a shared-hosting friendly project without background workers or queues.

## Features

- amoCRM OAuth flow handled via `oauth.php`, refresh logic built into `api/generate.php`.
- PhpWord `TemplateProcessor` replaces all placeholders inside `templates/*.docx`.
- Prefill cache (`api/prefill.php`) stores the last list of products per deal to speed up repeat document creation.
- Filesystem layout suits FTP/shared hosting: generated docs live in `documents/`, cached data in `data/`, logs in `logs/`.

## Project structure

- `api/` — HTTP endpoints that talk to amoCRM and produce docs or cached payloads.
- `public/` — small UI to trigger generation (`ui.html`, `app.js`, `main.css`).
- `src/` — reusable helpers (builders, formatters) tested with PHPUnit.
- `templates/` — `.docx` Word templates with placeholders.
- `documents/`, `logs/`, `data/` — writable directories for runtime artifacts.
- `tests/` — PHPUnit suite (smoke + pure logic tests).

## Requirements

- PHP 7.4+ with `curl`, `intl`, `mbstring`, `zip`.
- Composer (system or local `composer.phar`).
- Writable directories: `documents/`, `logs/`, `data/`, `data/cache/`.

## Configuration checklist

1. Edit `config/config.php`: fill amoCRM `client_id`, `client_secret`, `redirect_uri`, `base_domain`, paths if you deploy outside repo root, and optionally `hmac_secret`.
2. Run the amoCRM auth flow (`oauth.php?code=...`) to create `config/token.json`.
3. Ensure `public/app.js` points to your domain (update the `API` constant if necessary).
4. Verify that `public/ui.html?lead_id=<ID>` opens and hits your API endpoints.

## Deployment / usage

- Upload the repo to hosting, keep `config/`, `documents/`, `logs/`, `data/` out of public reach or tighten web-server rules.
- Serve the UI from `public/` or link `ui.html` inside your amoCRM widget with `lead_id` query arg.
- `POST /api/generate.php` expects `lead_id`, `template`, `discount`, `products[]`; it downloads deal/contact details, fills the template, saves `.docx` to `documents/` and posts a note back to amoCRM.
- `GET /api/prefill.php?lead_id=` returns cached payload so the UI can restore the previous basket.

## Local run & tests

Install dependencies and run PHPUnit (commands below are for Windows PowerShell; on Linux/macOS drop the `.\` prefix).

```powershell
PS D:\Coding projects\Projects php\amo_doc_generator> composer install
PS D:\Coding projects\Projects php\amo_doc_generator> .\vendor\bin\phpunit
```

## Documentation

For more detailed documentation (setup walkthrough, API examples, template placeholders) see `docs/README_en.md`.  
Для более подробной документации см. `docs/README_ru.md`.
