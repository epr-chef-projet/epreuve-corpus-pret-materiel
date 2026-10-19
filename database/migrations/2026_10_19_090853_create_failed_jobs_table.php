<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique('failed_jobs_uuid_unique');
            $table->string('connection');
            $table->string('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestampTz('failed_at', 6)->useCurrent();
            $table->index(['connection', 'queue', 'failed_at'], 'failed_jobs_connection_queue_failed_at_index');
        });
    }

    public function down(): void
    {
        Schema::drop('failed_jobs');
    }
};
