# Documentation Index — RabegNet ISP Billing System

> Portal navigasi untuk seluruh dokumentasi teknis RabegNet.

---

## Project

| Dokumen | Deskripsi |
|---------|-----------|
| [DESCRIPTION.md](00_PROJECT/DESCRIPTION.md) | Gambaran umum proyek (18 seksi) |
| [PRD.md](00_PROJECT/PRD.md) | Product Requirement Document |
| [CHANGELOG.md](00_PROJECT/CHANGELOG.md) | Riwayat versi |
| [ROADMAP.md](00_PROJECT/ROADMAP.md) | Peta pengembangan |

## Architecture

| Dokumen | Deskripsi |
|---------|-----------|
| [ARCHITECTURE.md](01_ARCHITECTURE/ARCHITECTURE.md) | OLT Driver Pattern, Multi-Tenancy, Isolir |
| [SYSTEM_DESIGN.md](01_ARCHITECTURE/SYSTEM_DESIGN.md) | Layer arsitektur, alur request, pola desain |
| [DATABASE.md](01_ARCHITECTURE/DATABASE.md) | ERD 28 tabel, relasi per grup |
| [API.md](01_ARCHITECTURE/API.md) | Endpoint external & internal |
| [SECURITY.md](01_ARCHITECTURE/SECURITY.md) | Isu keamanan & rekomendasi |
| [DEPLOYMENT.md](01_ARCHITECTURE/DEPLOYMENT.md) | Deploy Vercel & Railway |

## Business

| Dokumen | Deskripsi |
|---------|-----------|
| [BUSINESS_PROCESS.md](02_BUSINESS/BUSINESS_PROCESS.md) | 5 proses bisnis end-to-end |
| [BUSINESS_RULES.md](02_BUSINESS/BUSINESS_RULES.md) | 20 aturan bisnis (BR-01 s/d BR-20) |
| [MODULES.md](02_BUSINESS/MODULES.md) | Inventarisasi 15 modul inti & pendukung |
| [USER_FLOW.md](02_BUSINESS/USER_FLOW.md) | Alur pengguna (Customer, Admin, Teknisi) |

## Development

| Dokumen | Deskripsi |
|---------|-----------|
| [CODING_STANDARD.md](03_DEVELOPMENT/CODING_STANDARD.md) | Konvensi kode, routing, naming |
| [CONTRIBUTING.md](03_DEVELOPMENT/CONTRIBUTING.md) | Setup, workflow, commit/branch |
| [FOLDER_STRUCTURE.md](03_DEVELOPMENT/FOLDER_STRUCTURE.md) | Struktur direktori lengkap |
| [TESTING.md](03_DEVELOPMENT/TESTING.md) | PHPUnit: 55 test methods |
| [UI_GUIDELINE.md](03_DEVELOPMENT/UI_GUIDELINE.md) | Bootstrap 5.3 tokens, komponen |

## Implementation (Detail Teknis)

| Dokumen | Deskripsi |
|---------|-----------|
| [MODELS.md](05_IMPLEMENTATION/MODELS.md) | 19 model: atribut, casts, relationships |
| [DATABASE_SCHEMA.md](05_IMPLEMENTATION/DATABASE_SCHEMA.md) | 28 tabel: kolom per kolom |
| [MIGRATIONS.md](05_IMPLEMENTATION/MIGRATIONS.md) | 48 migrasi: kronologi skema |
| [SEEDERS.md](05_IMPLEMENTATION/SEEDERS.md) | 5 seeder: data, dependensi |
| [SERVICES.md](05_IMPLEMENTATION/SERVICES.md) | 11 services: method, parameter, flow |
| [CONTROLLERS.md](05_IMPLEMENTATION/CONTROLLERS.md) | 36 controller: route, validasi, flow |
| [JOBS_MAIL.md](05_IMPLEMENTATION/JOBS_MAIL.md) | 2 jobs + 2 mail: trigger, behavior |

## AI

| Dokumen | Deskripsi |
|---------|-----------|
| [AGENTS.md](04_AI/AGENTS.md) | Panduan referensi untuk AI agent |
| [AI_WORKFLOW.md](04_AI/AI_WORKFLOW.md) | Workflow AI agent |
| [PROMPTS.md](04_AI/PROMPTS.md) | Template prompt AI |

---

## Quick Reference

- **AGENTS.md** (root) — Ringkasan stack, commands, conventions untuk developer
- **README.md** (root) — Dokumentasi utama 1.182 baris
- **checker.md** — ⚠️ Mengandung sensitive tokens, jangan commit
