#!/bin/bash

# Exit immediately if a command exits with a non-zero status
set -e

echo "Starting ERP Database Migrations..."

# Ensure we are in the backend directory
if [ -d "backend" ]; then
    cd backend
else
    echo "Error: backend directory not found. Please run this script from the project root."
    exit 1
fi

# 1. Create central landlord tables
echo "Migrating central landlord tables..."
php artisan migrate --path=database/migrations/central --force

# 2. Run root-level shared migrations
echo "Running root-level shared migrations..."
php artisan migrate --force

# 3. Seed the landlord DB (This creates the tenant databases)
echo "Seeding the landlord DB..."
php artisan db:seed --force

# 4. Migrate every tenant database
echo "Migrating tenant databases..."
php artisan tenants:migrate --force

# 5. Seed tenant-specific data
echo "Seeding tenant databases..."
php artisan tenants:seed --force

# 6. Generate Passport API Keys, then update the .env file with the new credentials
echo "Generating Passport API Keys..."
php artisan passport:install --force

echo "Fetching new Passport client credentials from database..."
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

echo "Updating .env file with new keys..."
update_env "PASSPORT_PERSONAL_ACCESS_CLIENT_ID" "$PERSONAL_ID"
update_env "PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET" "$PERSONAL_SECRET"
update_env "PASSPORT_PASSWORD_CLIENT_ID" "$PASSWORD_ID"
update_env "PASSPORT_PASSWORD_CLIENT_SECRET" "$PASSWORD_SECRET"
echo "==========================================================="
echo "Database migration and seeding complete!"
echo "==========================================================="
