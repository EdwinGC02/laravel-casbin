<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection(config('casbin.connection', 'landlord'))
            ->create('casbin_rule', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('ptype')->nullable();
                $table->string('v0')->nullable();
                $table->string('v1')->nullable();
                $table->string('v2')->nullable();
                $table->string('v3')->nullable();
                $table->string('v4')->nullable();
                $table->string('v5')->nullable();
            });
    }

    public function down(): void
    {
        Schema::connection(config('casbin.connection', 'landlord'))
            ->dropIfExists('casbin_rule');
    }
};
