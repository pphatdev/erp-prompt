<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->uuid('employee_id')->nullable()->after('referrer_employee_id');
            $table->string('location')->nullable()->after('applicant_phone');
            $table->string('linkedin_url')->nullable()->after('location');
            $table->json('work_experience')->nullable()->after('cover_letter');
            $table->json('education')->nullable()->after('work_experience');
            $table->json('skills')->nullable()->after('education');

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn([
                'employee_id',
                'location',
                'linkedin_url',
                'work_experience',
                'education',
                'skills'
            ]);
        });
    }
};
