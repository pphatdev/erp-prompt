<?php

use App\Jobs\ReconcileAttendanceJob;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Schedule
|--------------------------------------------------------------------------
|
| Laravel 11 wires schedules through `routes/console.php` (referenced from
| bootstrap/app.php). Keep this file thin — fan out per-tenant work via
| jobs that resolve services through the container.
|
*/

// HRM — Attendance reconciliation at 01:00 daily.
//
// MULTI-TENANT NOTE: this default `Schedule::job(...)` dispatches against
// whatever DB connection is active. For a proper per-tenant fan-out add an
// artisan command that iterates Tenant::all() and dispatches the job inside
// each tenant context — track that as a follow-up (slice 4 ships with the
// single-tenant baseline first so the job + payroll plumbing can be tested).
Schedule::job(new ReconcileAttendanceJob())
    ->dailyAt('01:00')
    ->name('hrm-attendance-reconcile')
    ->withoutOverlapping();

// Sales — daily subscription expiry. Flips `active` rows whose end_date is
// in the past to `expired` across every tenant DB.
Schedule::command('subscriptions:expire')
    ->dailyAt('02:00')
    ->name('sales-subscription-expire')
    ->withoutOverlapping();

// Inventory — stock reservation expiry. Runs every 2 minutes so abandoned
// POS / eCommerce carts release their soft-holds within (TTL + ~2min).
Schedule::command('inventory:expire-reservations')
    ->everyTwoMinutes()
    ->name('inventory-reservation-expire')
    ->withoutOverlapping();
