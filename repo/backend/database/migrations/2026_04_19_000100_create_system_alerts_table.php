<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 64)->index();
            $table->string('severity', 16)->default('warning');
            $table->string('message');
            $table->json('context')->nullable();
            $table->timestamp('observed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
