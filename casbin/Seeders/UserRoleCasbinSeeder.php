<?php

namespace App\Casbin\Seeders;

use App\Casbin\Services\CasbinSyncService;
use Illuminate\Database\Seeder;

class UserRoleCasbinSeeder extends Seeder
{
    public function run(): void
    {
        // Ejemplo: asigna rol en el tenant 1. El servicio se resuelve del
        // contenedor porque recibe el writer de políticas por constructor.
        app(CasbinSyncService::class)->assignRoleToUserByDomain(1, 'Admin', 1);
    }
}
