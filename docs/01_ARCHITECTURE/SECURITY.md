# Security — RabegNet ISP Billing System

---

## Known Issues (To Fix)

| # | Issue | Severity | File/Location | Status |
|---|-------|----------|---------------|--------|
| 1 | Password MikroTik disimpan plaintext | **High** | `mikrotik_routers.password` | ⏳ Pending |
| 2 | SSL verification disabled untuk REST API | **High** | `MikrotikService.php` — `withoutVerifying()` | ⏳ Pending |
| 3 | `OdcPort` & `OdpPort` tanpa tenant scope | **Medium** | `app/Models/OdcPort.php`, `OdpPort.php` | ⏳ Pending |
| 4 | `reset_data.php` tanpa proteksi | **High** | `public/reset_data.php` | ⏳ Pending |
| 5 | Credentials di file publik | **High** | `.env`, `vercel.json`, `checker.md` di git history | ⏳ Pending |
| 6 | Tidak ada rate limiting | **Medium** | Login, API endpoints | ⏳ Pending |

---

## Security Measures (Already Implemented)

### Authentication
- Session-based authentication (Laravel default)
- Password hashing with bcrypt
- Google OAuth 2.0 (Laravel Socialite)
- CSRF protection (Laravel default)
- Session expired after browser close

### Authorization
- Role-based access: `admin` dan `teknisi`
- Middleware `IsAdmin` — admin-only routes
- Middleware `IsTeknisiOrAdmin` — authenticated routes
- Route-level authorization di web.php

### Data Isolation
- Multi-tenant via `BelongsToTenant` global scope
- Setiap query otomatis `WHERE tenant_id = ?`
- Tenant data terisolasi secara logis

### OLT Password
- Password di-encrypt menggunakan Laravel `encrypted` cast
- Berbeda dengan MikroTik yang plaintext

### Input Validation
- Form request validation di controller
- XSS protection via Blade escaping (`{{ }}`)
- SQL injection prevention via Eloquent ORM

### Logging
- Activity log untuk semua operasi CRUD penting
- Login/logout tercatat
- Error logging via Laravel log

---

## Security Recommendations

### Immediate (v1.x)
1. **Encrypt MikroTik passwords** — gunakan `encrypted` cast seperti OLT
2. **Enable SSL verification** — buat configurable, default `true`
3. **Add BelongsToTenant** ke OdcPort & OdpPort
4. **Protect reset_data.php** — tambah auth check atau hapus
5. **Clean git history** — hapus file dengan credentials
6. **Add rate limiting** — ke route login dan API

### Short-term (v2.0)
7. **Implement 2FA** — untuk admin accounts
8. **Add audit trail** — untuk semua perubahan sensitive
9. **Add IP whitelist** — untuk akses API eksternal
10. **Implement CORS** — untuk API publik

### Long-term (v3.0+)
11. **Penetration testing**
12. **Security headers** (HSTS, CSP, X-Frame-Options)
13. **Encryption at rest** untuk sensitive data
14. **GDPR compliance** (data privacy)
