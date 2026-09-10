<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;
use Tests\Support\AuditScenario;

class AuditSeeder extends Seeder
{
    use AuditScenario;

    public function run(): void
    {
        if (! app()->environment('testing') || config('database.connections.mysql.database') !== 'sigme_audit_ui' || config('database.connections.mysql.url')) {
            throw new RuntimeException('Este seeder só pode executar na base isolada sigme_audit_ui, em testing.');
        }
        $s = $this->auditScenario();
        $this->command?->info(json_encode(['organization' => $s['organization']->id, 'school' => $s['school']->id, 'environment' => $s['environment']->id, 'category' => $s['category']->id, 'occurrence' => $s['occurrence']->id, 'order' => $s['order']->id, 'users' => array_map(fn ($user) => $user->id, $s['users'])], JSON_THROW_ON_ERROR));
    }
}
