# 🛡️ Enterprise ERP - Multi-Tenant Backend API

[![Framework](https://img.shields.io/badge/framework-Laravel_11-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://php.net)
[![Multi-Tenancy](https://img.shields.io/badge/architecture-Multi--Database_Tenancy-green.svg)](#multi-tenancy-architecture)
[![Testing](https://img.shields.io/badge/testing-Pest_PHP-purple.svg)](#-testing-qa-standards)

This is the central high-performance RESTful API for the Multi-Tenant Enterprise ERP system. Built on **Laravel 11**, it leverages **PostgreSQL** with a multi-database strategy via `stancl/tenancy` to offer physical database isolation per tenant, and uses **Laravel Passport** for secure, tenant-scoped OAuth2 authorization.

---

## 🏗️ Core Architecture & Design Patterns

1. **Physical Database Isolation**: Each tenant occupies a fully isolated database instance. Connection switching occurs dynamically at runtime based on the client request host or tenant header.
2. **Domain-Driven Modular Structure**: Business domains are separated into standalone modules located in `app/Tenants/Modules/` (e.g., `IAM`, `FMS`, `HRM`, `Sales`).
3. **Decoupled Service Layer**: Controllers are "thin" and only handle request validation and API resource transformation. All business logic is encapsulated in atomic `Services` with Database Transactions for multi-table operations.
4. **Strict Audit Trail**: Apply the `Auditable` trait on all business-critical models to record model history, actor details, and state transitions automatically.

---

## 🛠️ Prerequisites & Local Environment

Ensure your system meets these specifications:
- **PHP**: `^8.2` (PHP `8.3` recommended)
- **Composer**: `2.x`
- **PostgreSQL**: `15+` (Default port `5433` matches the local Docker configuration)
- **Mandatory PHP Extensions** (enable these in your `php.ini`):
  * `openssl` (required for Composer and SSL communication)
  * `pdo_pgsql` & `pgsql` (required for PostgreSQL database drivers)
  * `mbstring` (required for multibyte string operations)

---

## 🚀 Quick Start & Installation

Follow these steps to configure the backend API locally:

### 1. Initialize Configuration
Duplicate the environment template to create your local configurations:
```bash
cp .env.example .env
```

### 2. Install PHP Dependencies
Install Composer packages:
```bash
composer install
```
> [!TIP]
> **Windows/OpenSSL Troubleshooting:** If Composer throws OpenSSL or TLS connection errors, find your active `php.ini` using `php --ini`, open it in an editor, and uncomment `extension=openssl` and `extension_dir = "ext"`.

### 3. Generate Encryption & Auth Keys
Generate the Laravel application key and Passport client keys:
```bash
# Generate app key
php artisan key:generate

# Generate personal access client and OAuth keys for Passport
php artisan passport:keys
php artisan passport:client --personal
```

### 4. Configure Database Credentials
Verify that your `.env` contains the correct central database credentials matching your Docker instance:
```ini
# Central Landlord Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=erp_system
DB_USERNAME=erp_user
DB_PASSWORD=erp_secret
```

### 5. Run Migrations & Database Setup
Apply core tables to the landlord database:
```bash
# Run central landlord migrations
php artisan migrate
```

### 6. Run the Server
Start the local development server:
```bash
php artisan serve
```
The API is now running locally at `http://127.0.0.1:8000`.

---

## 🏘️ Multi-Tenancy Architecture

All tenant database migrations reside in `database/migrations/tenant`. The central database migrations reside in `database/migrations/central`.

### Useful Tenancy Artisan Commands
```bash
# Run migrations across all active tenant databases
php artisan tenants:migrate

# Rollback tenant migrations
php artisan tenants:rollback

# Seed global config configurations into the landlord database
php artisan db:seed --class=CentralSeeder
```

---

## 🧪 Testing & QA Standards (P0 Isolation)

We use **Pest PHP** for clean, declarative feature and unit tests.

> [!IMPORTANT]
> **P0 Database Connection Isolation:** Tests must **NEVER** run on active development (`develop`) or production (`production`) database connections. Running tests on those configurations will erase or corrupt permanent database tables.

### Testing Setup
1. Testing overrides are configured inside `phpunit.xml` to point automatically to `erp_system_test`.
2. Ensure you have a dedicated testing database matching the XML configuration:
   * **Database Connection**: `pgsql`
   * **Database Name**: `erp_system_test`
   * **Port**: `5433`
3. Execute the tenant testing migrations first:
   ```bash
   php artisan tenants:migrate --database=testing
   ```
4. Run your tests:
   ```bash
   php artisan test
   ```

---

## 📂 Core Directory Structure

```bash
├── 📁 app
│   ├── 📁 Http
│   │   └── 📁 Controllers   # Central landing routes and main health checks
│   ├── 📁 Models             # Core landlord models (Tenant, User, Domain)
│   ├── 📁 Services           # Global services (Auth, Logging)
│   └── 📁 Tenants
│       └── 📁 Modules        # 12 Standalone business modules
│           └── 📁 [Module]   # e.g., IAM, FMS, HRM, Sales, Inventory
│               ├── 📁 Controllers  # Thin resourceful API controllers
│               ├── 📁 Models       # Tenant-scoped Eloquent models (BelongsToTenant)
│               ├── 📁 Services     # Pure business logic and atomic DB transitions
│               └── 📁 Requests     # Strict validation forms
├── 📁 config                 # Laravel settings
├── 📁 database
│   ├── 📁 migrations
│   │   ├── 📁 central        # Central/Landlord schemas
│   │   └── 📁 tenant         # Isolated tenant schemas
│   └── 📁 seeders            # Global and tenant seed structures
├── 📁 routes
│   ├── 📄 api.php            # Central API endpoints (Tenant onboarding, etc.)
│   └── 📄 tenant.php         # Scoped tenant API endpoints (accessed via tenant hostname/header)
└── 📄 phpunit.xml            # PHPUnit/Pest environment and isolated test database settings
```

---

## 📝 API & Postman Documentation

All backend endpoints are continuously documented and versioned. 
- API version: `/v1/`
- Every tenant-scoped request must include the header **`X-Tenant-Handle`** or **`tenant: {{tenant_id}}`** so that the multi-tenancy middleware can properly bind and switch database contexts dynamically.
- The Postman Collection is maintained at `docs/postman/erp_collection.json`. When creating a new endpoint or updating an existing signature, you **must** update the corresponding postman contract.
