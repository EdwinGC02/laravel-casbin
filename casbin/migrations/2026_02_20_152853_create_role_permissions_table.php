<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection(config('casbin.connection', 'landlord'))
            ->create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->char('uuid', 26)->unique();
                $table->unsignedBigInteger('role_id');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('restrict')->cascadeOnUpdate();
                $table->unsignedBigInteger('permission_id');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('restrict')->cascadeOnUpdate();
                $table->char('status', 1)->default('1');
                $table->timestamps();
            });
    }

    public function down(): void
    {
        Schema::connection(config('casbin.connection', 'landlord'))
            ->dropIfExists('role_permissions');
    }
};
