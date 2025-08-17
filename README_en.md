# Document Generator for amoCRM

This project integrates with amoCRM deals to generate `.docx` documents based on Word templates and deal/contact data. Two templates are supported: **work order** and **act**. Documents are generated on the server and saved in the `documents/` folder, with a link returned to the UI.

---

## 1) Architecture

```
amo_doc_generator/
├─ api/
│  ├─ generate.php        # Generate .docx from form data and amoCRM
│  └─ prefill.php         # Return last saved form state by lead_id
├─ config/
│  ├─ config.php          # Project settings and amoCRM OAuth data
│  └─ token.json          # Current OAuth tokens (auto-generated)
├─ data/
│  └─ cache/              # Cache for prefill by lead_id (auto-created)
├─ documents/             # Generated .docx documents (auto-created)
├─ logs/
│  ├─ generate.log        # Errors and exceptions
│  └─ debug_generate.log  # Debug logs
├─ public/
│  ├─ ui.html             # User interface
│  ├─ app.js              # Form logic, API calls
│  └─ main.css            # UI styles
├─ templates/
│  ├─ order_template.docx # Work order template
│  └─ act_template.docx   # Act template
├─ oauth.php              # Exchange `code` → tokens and save to config/token.json
├─ composer.json          # Dependencies (phpoffice/phpword)
└─ composer.phar          # Local Composer (system Composer can also be used)
```

Backend: PHP 7.4+ with `phpoffice/phpword`  
Frontend: `public/ui.html` + `public/app.js` (fetches API and renders form).

---

## 2) Server requirements

- PHP **7.4+**.
- PHP extensions: **zip**, **xml**, **mbstring**, **json**, **curl**.
- Write permissions for: `documents/`, `logs/`, `data/`, `data/cache/`.
- Composer (system `composer` or local `php composer.phar`).

Install dependencies (from project root):
```bash
composer install
# or
php composer.phar install
```

---

## 3) Deployment to a new domain/location

1. **Copy** the project folder to your hosting. Recommended base URL: `https://<your-domain>/<path>/amo_doc_generator`.
2. Grant write permissions for `documents/`, `logs/`, `data/`:
   ```bash
   chmod -R 775 documents logs data
   # or 777 in environments without proper web process ownership
   ```
3. In `public/app.js` **update the API base URL**:
   ```js
   // was:
   const API = 'https://apiport.ru/amo_doc_generator';
   // should point to your domain/path, e.g.:
   const API = 'https://<your-domain>/<path>/amo_doc_generator';
   // or use a relative path if UI and API are on the same host:
   // const API = '/amo_doc_generator';
   ```
4. Verify that `https://<your-domain>/<path>/amo_doc_generator/public/ui.html` opens without browser errors.

---

## 4) Connecting to a new amoCRM account (new integration)

> Goal: obtain `client_id`, `client_secret`, `redirect_uri` and save OAuth tokens to `config/token.json`.

### 4.1. Create a **private integration** in amoCRM
- In amoCRM UI, create a new private integration.
- Set **Redirect URI**:  
  `https://<your-domain>/<path>/amo_doc_generator/oauth.php`
- Save the generated **Client ID** and **Client Secret**.

### 4.2. Update `config/config.php`
```php
return [
  'client_id'     => 'YOUR_CLIENT_ID',
  'client_secret' => 'YOUR_CLIENT_SECRET',
  'redirect_uri'  => 'https://<your-domain>/<path>/amo_doc_generator/oauth.php',
  'base_domain'   => 'https://<subdomain>.amocrm.ru', // your amoCRM domain
  'subdomain'     => '<subdomain>',

  'template_path' => __DIR__.'/../templates/',
  'document_path' => __DIR__.'/../documents/',
  'temp_data_path'=> __DIR__.'/../data/',

  'prefill_ttl_days' => 5,
];
```

### 4.3. Authorize the integration
1. Follow the authorization link from the integration card in amoCRM **or** generate an authorization URL using the integration credentials.
2. After granting access, you will be redirected to `oauth.php` with `?code=...`.
3. The `oauth.php` script exchanges `code` for tokens and saves them in `config/token.json`.
4. Token refresh happens **automatically** inside `api/generate.php` on amoCRM 401 responses.

Verify: after authorization, `config/token.json` should exist with valid tokens.

---

## 5) Embedding into deal card

You can simply open the UI with `lead_id`:
```
https://<your-domain>/<path>/amo_doc_generator/public/ui.html?lead_id=<deal_ID>
```
Ways to embed:
- Button/link in the deal card pointing to this URL.
- Embed via your widget as an iframe with the same URL.

> `lead_id` is required for data prefill and cache to work.

---

## 6) Using the UI

1. Open `ui.html` with `lead_id` parameter.
2. If cached form data exists for the deal, it will be loaded from `api/prefill.php`.
3. Fill the table: **Name**, **Unit Price**, **Quantity**, **Row Discount %**.
4. Optionally enter **total discount** in rubles (right-side field).
5. Choose template: `order` (work order) or `act` (act).
6. Click **Generate**. The response will contain a `.docx` link from `documents/`.

Totals and amounts in words are calculated both on frontend and backend.

---

## 7) API

### `POST /api/generate.php`
Input (JSON):
```json
{
  "lead_id": 123456,
  "template": "order",          // or "act"
  "discount": 500,              // total discount in rubles
  "products": [
    {
      "name": "Diagnostics",
      "unit_price": 1500,
      "qty": 1,
      "discount_percent": 0
    }
  ]
}
```
Output:
```json
{ "url": "https://<domain>/<path>/amo_doc_generator/documents/<file>.docx" }
```
Error codes: `400 Bad JSON/Invalid lead_id or products`, `500 Internal Server Error`.

### `GET /api/prefill.php?lead_id=<id>`
Returns last saved form state for the deal:
```json
{
  "template": "order",
  "discount": 0,
  "products": [ ... ],
  "saved_at": 1710000000
}
```

---

## 8) Data fetched from amoCRM

`generate.php` fetches via amoCRM API:
- Deal: `leads/{id}?with=contacts`
- Linked contact (if exists).

Used fields:
- Name: priority is custom fields **"Last Name"**, **"First Name"**, **"Middle Name"** on the deal; otherwise from contact name.
- Phone: `PHONE` field of contact.
- Vehicle: **"Make"**, **"Model"**, **"VIN"**, **"Year of manufacture"** — must match **exact custom field names**.

> If your field names differ, update mapping in `api/generate.php` (`setValue(...)` and `$getCF` function).

---

## 9) Word templates and placeholders

In `templates/` there are two files: `order_template.docx` and `act_template.docx`.  
PhpWord `TemplateProcessor` placeholders used:

**Single fields**
```
${Number}
${Date}
${Phone}
${Make}
${Model}
${VIN}
${Year}
${LastName}
${FirstName}
${MiddleName}
${Total}
${Discount}
${TotalToPay}
${ItemsCount}
${AmountInWords}
```

**Table rows** (numbered `#1`, `#2`, ...):
```
${row_num#1}
${service_name#1}
${row_qty#1}
${row_price#1}
${row_discount#1}    // "10%" or "-", if no discount
${row_sum#1}
```
Rows are cloned according to the number of products.

**Modifying templates**
- Edit `.docx` only in MS Word/LibreOffice.
- Do not change placeholder keys without updating code.
- Adding a new template requires code changes (currently allowed: `order` and `act`).

---

## 10) Logs and debugging

- `logs/generate.log` — PHP errors/exceptions.
- `logs/debug_generate.log` — debug info (deal ID, found fields, etc.).
- On amoCRM 401, token is **automatically refreshed**. If refresh fails — check `config/config.php` and repeat **section 4.3**.

---

## 11) Migration to another amoCRM account / new integration

Checklist:
1. Copy project to target hosting, grant write permissions to `documents/`, `logs/`, `data/`.
2. Run `composer install`.
3. Create **new private integration** in target amoCRM account.
4. Update `config/config.php` (`client_id`, `client_secret`, `redirect_uri`, `base_domain`, `subdomain`).
5. Authorize and complete **section 4.3** to create `config/token.json`.
6. Update `public/app.js` → `API` constant to new domain/path.
7. Test UI at `public/ui.html?lead_id=<id>` and document generation.
8. Adjust custom field mapping if necessary (section 8).

---

## 12) Security

- Do not keep `config/` and `documents/` in public root without directory listing restrictions.
- Protect `config/token.json` from direct URL access.
- Exclude `config/token.json` from version control.

---

## 13) Known limitations

- Global discount — **in rubles**, row discount — **in percent**.
- Templates are fixed: `order` and `act`.
- Exact custom field names are expected (see section 8).

---

## 14) Quick smoke-test after installation

```bash
# 1) Check PHP dependencies
php -m | grep -E 'zip|xml|mbstring|curl|json'

# 2) Check UI
open https://<domain>/<path>/amo_doc_generator/public/ui.html?lead_id=TEST_ID

# 3) Test API
curl -X POST https://<domain>/<path>/amo_doc_generator/api/generate.php   -H 'Content-Type: application/json'   -d '{"lead_id":123456,"template":"order","discount":0,"products":[{"name":"Test","unit_price":1000,"qty":1,"discount_percent":0}]}'

# Expected {"url":"https://.../documents/<file>.docx"}
```
