<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection(config('casbin.connection', 'landlord'))
            ->create('modules_permissions', function (Blueprint $table) {
                $table->id();
                $table->char('uuid', 26)->unique();
                $table->string('code');
                $table->string('name');
                $table->char('status', 1)->default('1');
                $table->timestamps();
            });
    }

    public function down(): void
    {
        Schema::connection(config('casbin.connection', 'landlord'))
            ->dropIfExists('modules_permissions');
    }
};
