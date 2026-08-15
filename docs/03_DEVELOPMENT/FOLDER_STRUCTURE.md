# Folder Structure — RabegNet ISP Billing System

```
e-billing/
│
├── AGENTS.md                       # Petunjuk development untuk AI Agent
├── DESCRIPTION.md                  # Dokumentasi utama proyek (root → pindah ke docs/)
├── PRD.md                          # Product Requirement Document (root → pindah ke docs/)
├── README.md                       # README Laravel default
├── composer.json
├── package.json
├── vite.config.js
├── phpunit.xml
├── vercel.json                     # Deployment Vercel
├── railway.json                    # Deployment Railway.app
│
├── docs/                           # 📁 Dokumentasi terstruktur
│   ├── 00_PROJECT/
│   │   ├── DESCRIPTION.md
│   │   ├── PRD.md
│   │   ├── ROADMAP.md
│   │   └── CHANGELOG.md
│   ├── 01_ARCHITECTURE/
│   │   ├── SYSTEM_DESIGN.md
│   │   ├── ARCHITECTURE.md
│   │   ├── DATABASE.md
│   │   ├── API.md
│   │   ├── SECURITY.md
│   │   └── DEPLOYMENT.md
│   ├── 02_BUSINESS/
│   │   ├── BUSINESS_PROCESS.md
│   │   ├── MODULES.md
│   │   ├── USER_FLOW.md
│   │   └── BUSINESS_RULES.md
│   ├── 03_DEVELOPMENT/
│   │   ├── CODING_STANDARD.md
│   │   ├── FOLDER_STRUCTURE.md
│   │   ├── UI_GUIDELINE.md
│   │   ├── TESTING.md
│   │   └── CONTRIBUTING.md
│   └── 04_AI/
│       ├── AGENTS.md
│       ├── PROMPTS.md
│       └── AI_WORKFLOW.md
│
├── app/
│   ├── Console/
│   │   └── Commands/               # 8 Artisan commands
│   │       ├── AutoIsolir.php
│   │       ├── BillingProcess.php
│   │       ├── ImportHotspotFiles.php
│   │       ├── MikrotikSetupIsolir.php
│   │       ├── PollOlt.php
│   │       ├── SyncCustomerOnu.php
│   │       ├── SyncIsolirIps.php
│   │       └── SyncVoucherMikrotik.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── MikrotikHotspotController.php
│   │   │   │   ├── OdpruteController.php
│   │   │   │   └── PortController.php
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── RegisterController.php
│   │   │   │   └── SocialiteController.php
│   │   │   ├── BackupController.php
│   │   │   ├── CronController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── DistributionController.php
│   │   │   ├── ExportController.php
│   │   │   ├── InvoiceController.php
│   │   │   ├── IsolirController.php
│   │   │   ├── LogController.php
│   │   │   ├── MidtransController.php
│   │   │   ├── MikrotikController.php
│   │   │   ├── MikrotikRouterController.php
│   │   │   ├── OdcController.php
│   │   │   ├── OdpController.php
│   │   │   ├── OltController.php
│   │   │   ├── PackageController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── PortalController.php
│   │   │   ├── PublicVoucherController.php
│   │   │   ├── ReportController.php
│   │   │   ├── SettingController.php
│   │   │   ├── SitemapController.php
│   │   │   ├── VoucherController.php
│   │   │   ├── VoucherProfileController.php
│   │   │   ├── VoucherReportController.php
│   │   │   └── VoucherTemplateController.php
│   │   │
│   │   └── Middleware/
│   │       ├── IsAdmin.php
│   │       └── IsTeknisiOrAdmin.php
│   │
│   ├── Jobs/                       # Queue jobs
│   │   └── PollOltJob.php
│   │
│   ├── Mail/                       # Email Mailable
│   │   ├── InvoiceReminder.php
│   │   └── PaymentConfirmation.php
│   │
│   ├── Models/                     # 19 Models + 2 Traits
│   │   ├── Traits/
│   │   │   ├── BelongsToTenant.php
│   │   │   └── BelongsToUser.php (⚠️ legacy/dead code)
│   │   ├── ActivityLog.php
│   │   ├── Customer.php
│   │   ├── Invoice.php
│   │   ├── MikrotikRouter.php
│   │   ├── Odc.php
│   │   ├── OdcPort.php
│   │   ├── Odp.php
│   │   ├── OdpPoint.php
│   │   ├── OdpPort.php
│   │   ├── OdpRoute.php
│   │   ├── Olt.php
│   │   ├── OltPort.php
│   │   ├── Onu.php
│   │   ├── Package.php
│   │   ├── Payment.php
│   │   ├── Setting.php
│   │   ├── Tenant.php
│   │   ├── User.php
│   │   ├── Voucher.php
│   │   ├── VoucherProfile.php
│   │   └── VoucherTemplate.php
│   │
│   └── Services/
│       ├── MidtransService.php
│       ├── MikrotikService.php      # 652 baris — REST API wrapper
│       └── Olt/                     # Driver Pattern
│           ├── Contracts/
│           │   └── OltConnector.php
│           ├── Drivers/
│           │   ├── CDataConnector.php
│           │   ├── FiberHomeConnector.php
│           │   ├── HuaweiConnector.php
│           │   ├── JumpHostConnector.php
│           │   ├── MikrotikSshProxyConnector.php
│           │   └── ZteConnector.php
│           ├── Factory/
│           │   └── OltConnectorFactory.php
│           └── SshTunnel.php
│
├── bootstrap/                      # Laravel bootstrap
├── config/                         # Konfigurasi Laravel
│
├── database/
│   ├── database.sqlite             # Local fallback
│   ├── factories/                  # 5 factories
│   ├── migrations/                 # 46 migrations (28 tables)
│   └── seeders/                    # 5 seeders
│
├── public/
│   ├── build/                      # Asset compiled (Vite)
│   ├── hotspot/                    # HTML hotspot pages
│   └── index.php
│
├── resources/
│   ├── css/
│   │   └── app.css                 # ~1570 baris custom CSS
│   ├── js/
│   │   ├── app.js
│   │   └── bootstrap.js
│   └── views/                      # ~58 blade files
│       ├── auth/
│       ├── backups/
│       ├── customer/
│       ├── distribution/
│       ├── emails/
│       ├── invoices/
│       ├── isolir/
│       ├── layouts/
│       ├── logs/
│       ├── midtrans/
│       ├── mikrotik/
│       ├── mikrotik-routers/
│       ├── odc/
│       ├── odp/
│       ├── olt/
│       ├── packages/
│       ├── payments/
│       ├── portal/
│       ├── reports/
│       ├── settings/
│       ├── voucher-profiles/
│       ├── voucher-templates/
│       ├── vouchers/
│       ├── dashboard.blade.php
│       └── welcome.blade.php
│
├── routes/
│   ├── web.php                     # ~141 routes
│   ├── api.php                     # 3 API routes
│   └── console.php                 # 5 scheduled commands
│
├── storage/                        # Logs, cache, backups
│
└── tests/
    ├── Feature/                    # 7 test classes (49 methods)
    │   ├── AuthTest.php
    │   ├── CustomerTest.php
    │   ├── DistributionTest.php
    │   ├── ExampleTest.php
    │   ├── InvoiceTest.php
    │   ├── PackageTest.php
    │   └── SitemapTest.php
    └── Unit/                       # 1 test class (1 method)
        └── ExampleTest.php
```
