# Roadmap — RabegNet ISP Billing System

> Versi: 1.1 | Status: Production Active

---

## v1.x — Stabilization & Security (Current)

**Fokus:** Mengamankan sistem, memperbaiki celah keamanan, stabilisasi produksi.

| Item | Prioritas | Status |
|------|-----------|--------|
| Encrypt password MikroTik di database | High | ⏳ Pending |
| SSL verification enable (configurable) | High | ⏳ Pending |
| Tambah BelongsToTenant ke OdcPort & OdpPort | High | ⏳ Pending |
| Proteksi reset_data.php | High | ⏳ Pending |
| Hapus sensitive credentials dari git history | High | ⏳ Pending |
| Testing coverage existing features | Medium | ✅ Selesai |
| Bug fixes dari production | High | 🔄 Ongoing |

---

## v2.0 — Architecture Refactoring

**Fokus:** Refactoring arsitektur, API publik, testing coverage.

| Item | Prioritas |
|------|-----------|
| API publik untuk integrasi pihak ketiga | High |
| Testing coverage meningkat (>80%) | High |
| Code refactoring (clean up dead code BelongsToUser) | Medium |
| Performance optimasi query | Medium |
| Soft delete untuk data penting | Medium |
| Event/notification system | Low |

---

## v3.0 — Smart ISP Operations

**Fokus:** Otomatisasi cerdas untuk operasional ISP.

| Item | Prioritas |
|------|-----------|
| Auto-diagnosis jaringan (deteksi putus OLT/ODP) | High |
| Predictive maintenance (berdasarkan tren Rx power) | High |
| Smart scheduling untuk teknisi | Medium |
| Customer communication automation | Medium |
| Auto-ticket untuk trouble ticket | Low |

---

## v4.0 — Network Management (NMS)

**Fokus:** Fitur NMS (Network Management System).

| Item | Prioritas |
|------|-----------|
| Topology mapping otomatis | High |
| Real-time network health dashboard | High |
| Alert & notification system (email/WA/telegram) | High |
| Integration dengan SNMP devices | Medium |
| Network performance history & trending | Medium |

---

## v5.0 — Business Intelligence

**Fokus:** Analytics dan business intelligence.

| Item | Prioritas |
|------|-----------|
| Advanced analytics & forecasting | High |
| Churn prediction | Medium |
| Customer segmentation | Medium |
| Executive dashboard real-time | High |
| Automated report scheduling | Medium |

---

## v6.0 — Multi-Tenant Enterprise

**Fokus:** Mendukung multiple ISP dalam satu platform.

| Item | Prioritas |
|------|-----------|
| White-label untuk reseller ISP | High |
| Billing management untuk multiple ISP | High |
| Central monitoring console | High |
| Revenue sharing & commission | Medium |
| Tiered pricing & plan management | Medium |

---

## v7.0 — Ecosystem Expansion

**Fokus:** Ekosistem terbuka.

| Item | Prioritas |
|------|-----------|
| Plugin marketplace | High |
| Public API dengan dokumentasi lengkap | High |
| Integrasi dengan payment channel lain (GoPay, OVO, dll) | Medium |
| Mobile app (customer self-service) | Medium |
| Mobile app (teknisi field) | Medium |

---

## v8.0 — Enterprise Readiness

**Fokus:** Kesiapan enterprise.

| Item | Prioritas |
|------|-----------|
| High availability clustering | High |
| SLA management | High |
| Advanced security compliance (ISO 27001, PCI DSS) | High |
| Enterprise support & documentation | Medium |
| Multi-region deployment | Low |
