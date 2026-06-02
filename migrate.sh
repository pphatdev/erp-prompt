#!/bin/bash

# Exit immediately if a command exits with a non-zero status
set -e

# ANSI Color Codes
GREEN='\033[0;32m'
CYAN='\033[0;36m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Print helper functions
info() {
    echo -e "${CYAN}🚀 [INFO] $1${NC}"
}
success() {
    echo -e "${GREEN}✅ [SUCCESS] $1${NC}"
}
warn() {
    echo -e "${YELLOW}⚠️  [WARNING] $1${NC}"
}
error() {
    echo -e "${RED}❌ [ERROR] $1${NC}"
}
heading() {
    echo -e "\n${BOLD}${GREEN}============================================================${NC}"
    echo -e "${BOLD}${CYAN}   $1${NC}"
    echo -e "${BOLD}${GREEN}============================================================${NC}\n"
}

heading "Starting ERP Database Migrations"

# Ensure we are in the backend directory
if [ -d "backend" ]; then
    cd backend
else
    error "backend directory not found. Please run this script from the project root."
    exit 1
fi

# Ensure .env exists, copying from .env.example if missing
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        warn ".env file not found. Copying from .env.example..."
        cp .env.example .env
        php artisan key:generate
        success ".env file generated successfully!"
    else
        error ".env file is missing and .env.example does not exist inside the backend folder."
        exit 1
    fi
fi

# 1. Create central landlord tables
info "Migrating central landlord tables..."
php artisan migrate --path=database/migrations/central --force
success "Central landlord tables migrated successfully."

# 2. Run root-level shared migrations
info "Running root-level shared migrations..."
php artisan migrate --force
success "Shared migrations completed."

# 3. Seed the landlord DB (This creates the tenant databases)
info "Seeding the landlord DB..."
php artisan db:seed --force
success "Landlord DB seeded."

# 4. Migrate every tenant database
info "Migrating tenant databases..."
php artisan tenants:migrate --force
success "Tenant databases migrated."

# 5. Seed tenant-specific data
info "Seeding tenant databases..."
php artisan tenants:seed --force
success "Tenant databases seeded."

# 6. Generate Passport API Keys, then update the .env file with the new credentials
info "Generating Passport API Keys..."
php artisan passport:install --force
success "Passport keys generated."

info "Fetching new Passport client credentials from database..."
PERSONAL_ID=$(php artisan tinker --execute="echo \Laravel\Passport\Client::where('personal_access_client', 1)->latest()->first()->id ?? '';")
PERSONAL_SECRET=$(php artisan tinker --execute="echo \Laravel\Passport\Client::where('personal_access_client', 1)->latest()->first()->secret ?? '';")
PASSWORD_ID=$(php artisan tinker --execute="echo \Laravel\Passport\Client::where('password_client', 1)->latest()->first()->id ?? '';")
PASSWORD_SECRET=$(php artisan tinker --execute="echo \Laravel\Passport\Client::where('password_client', 1)->latest()->first()->secret ?? '';")

update_env() {
    local key=$1
    local value=$2
    if [ -n "$value" ]; then
        if grep -q "^${key}=" .env; then
            sed -i "s|^${key}=.*|${key}=${value}|" .env
        else
            echo "${key}=${value}" >> .env
        fi
    fi
}

info "Updating .env file with new keys..."
update_env "PASSPORT_PERSONAL_ACCESS_CLIENT_ID" "$PERSONAL_ID"
update_env "PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET" "$PERSONAL_SECRET"
update_env "PASSPORT_PASSWORD_CLIENT_ID" "$PASSWORD_ID"
update_env "PASSPORT_PASSWORD_CLIENT_SECRET" "$PASSWORD_SECRET"

heading "Database migration and seeding complete!"

info "Newly configured Passport Client credentials inside your .env:"
echo -e "  ${YELLOW}PASSPORT_PERSONAL_ACCESS_CLIENT_ID=${NC}${PERSONAL_ID}"
echo -e "  ${YELLOW}PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET=${NC}${PERSONAL_SECRET}"
echo -e "  ${YELLOW}PASSPORT_PASSWORD_CLIENT_ID=${NC}${PASSWORD_ID}"
echo -e "  ${YELLOW}PASSPORT_PASSWORD_CLIENT_SECRET=${NC}${PASSWORD_SECRET}"
echo ""
