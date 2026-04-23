<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection(config('casbin.connection', 'landlord'))
            ->create('permissions', function (Blueprint $table) {
                $table->id();
                $table->char('uuid', 26)->unique();
                $table->unsignedBigInteger('module_id');
                $table->foreign('module_id')->references('id')->on('modules_permissions')->onDelete('restrict')->cascadeOnUpdate();
                $table->string('action');
                $table->string('name');
                $table->timestamps();
            });
    }

    public function down(): void
    {
        Schema::connection(config('casbin.connection', 'landlord'))
            ->dropIfExists('permissions');
    }
};
