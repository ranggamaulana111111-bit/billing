# AGENTS.md — Login Page 34

**MikroTik Hotspot login page template** by Erik Sanjaya (eriksanjaya.com).

## Architecture

Static HTML/JS/CSS — **no build tool, no package manager, no tests, no CI**. Deploy by uploading files directly to a MikroTik router's hotspot directory.

### Entrypoints
- `login.html` — main login form (voucher / member / trial / QR scan)
- `status.html` — shows active session info (uptime, quota, etc.) with auto-refresh
- `logout.html` — session summary + re-login button
- `alogin.html` — AJAX login stub (thin wrapper, content populated by JS)
- `rlogin.html` / `redirect.html` — HTTP 302 redirect helpers for MikroTik
- `radvert.html` — advertisement/hotspot splash page
- `error.html` — generic hotspot error display
- `device-info.html` — client-side device info diagnostic

### MikroTik template variables
All HTML pages use `$(variable)` syntax evaluated server-side by the MikroTik hotspot:
`$(link-login-only)`, `$(username)`, `$(error)`, `$(chap-id)`, `$(chap-challenge)`, `$(link-orig)`, `$(mac-esc)`, `$(link-logout)`, `$(refresh-timeout-secs)`, etc.

### Error messages
`errors.txt` — MikroTik hotspot error message file (served server-side, not a plain text file).

## Customization (no code changes needed)

All user-facing configuration is in JS config files — **never edit `assets/app.min.js`** (obfuscated runtime).

### Data configs (`config/data/*.js`)
| File | Purpose |
|---|---|
| `header.js` | Logo, title, tagline, clock |
| `login.js` | Form modes, button/text labels, redirect, QR URL |
| `status.js` | Status/logout page labels |
| `navBar.js` | Bottom nav bar label text |
| `slider.js` | Promo slider images + delay |
| `internetPackage.js` | Package listings, prices, buy button |
| `faq.js` | FAQ Q&A items |
| `popup.js` | Welcome popup content |
| `chat.js` | Intergram + WhatsApp provider config |
| `expiredMikhmon.js` | Mikhmon expiry API integration |
| `license.js` | License key (do not alter) |

### Style configs (`config/style/*.js`)
Mirror the data config structure (`window.*Style` objects). Control colors, gradients, borders, shadows for every UI component. Colors reference a palette in `body.js:colorLibrary`.

### Entry point
`init.js` — runs on `load`, removes `.page-loading` class, adds `.page-ready`. Imports `assets/app.min.js`, `assets/md5.js`, `assets/siema.min.js`.

## Key conventions

- All HTML uses **Tailwind CSS** (pre-compiled in `assets/style.css`).
- Login sends CHAP-MD5 via `hexMD5("$(chap-id)" + user + "$(chap-challenge)")`.
- Hidden form `sendin` submits the actual login; visible form `login` pre-processes it.
- Voucher mode sets password = username; Member mode uses actual password.
- Footer nav has 4 tabs: Home, Paket, FAQ, Chat (switched via `switchTab()`).
- License check at runtime — `config/data/license.js` must remain valid.
- Do not remove or alter `config/creator.js` — contains attribution.
