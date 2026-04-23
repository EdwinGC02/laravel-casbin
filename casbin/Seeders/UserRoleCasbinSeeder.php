<?php

namespace App\Casbin\Seeders;

use App\Casbin\Services\CasbinSyncService;
use Illuminate\Database\Seeder;

class UserRoleCasbinSeeder extends Seeder
{
    public function run(): void
    {
        // Ejemplo: asigna rol en dominio tenant:1.
        (new CasbinSyncService())->assignRoleToUserByDomain(1, 'Admin', 1);
    }
}
