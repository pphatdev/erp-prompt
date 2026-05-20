<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'ERP Backend API',
        'status' => 'operational',
        'version' => app()->version(),
        'environment' => app()->environment(),
    ]);
});
