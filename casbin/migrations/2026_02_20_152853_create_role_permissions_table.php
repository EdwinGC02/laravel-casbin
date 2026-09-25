<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos de un rol DENTRO de un tenant.
 *
 * La llave natural es el TRÍO (tenant, rol, permiso), no el par (rol, permiso):
 * `roles` es una tabla global —el mismo rol puede estar asociado a varios
 * tenants— y cada tenant contrata apps distintas, así que cada uno debe poder
 * darle al mismo rol un juego de permisos distinto. Sin `tenant_id` la fuente
 * de verdad es global y editar el rol desde un tenant reescribe lo que ese rol
 * tenía configurado en los demás.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::connection(config('casbin.connection', 'landlord'))
            ->create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->char('uuid', 26)->unique();
                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete()->cascadeOnUpdate();
                $table->unsignedBigInteger('role_id');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('restrict')->cascadeOnUpdate();
                $table->unsignedBigInteger('permission_id');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('restrict')->cascadeOnUpdate();
                $table->char('status', 1)->default('1');
                $table->timestamps();

                $table->unique(['tenant_id', 'role_id', 'permission_id'], 'role_permissions_unique');
                $table->index(['tenant_id', 'role_id'], 'role_permissions_tenant_role_idx');
            });
    }

    public function down(): void
    {
        Schema::connection(config('casbin.connection', 'landlord'))
            ->dropIfExists('role_permissions');
    }
};
