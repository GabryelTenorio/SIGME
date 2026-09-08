<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION_SLUG = 'ocorrencias.visualizar_todas';

    /** @var list<string> */
    private const LEGACY_ROLE_SLUGS = [
        'administrador-rede',
        'administrador-escola',
        'gestor',
    ];

    public function up(): void
    {
        DB::table('permissions')
            ->where('slug', self::PERMISSION_SLUG)
            ->delete();
    }

    public function down(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'name' => 'Visualizar todas as ocorrências',
            'slug' => self::PERMISSION_SLUG,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('slug', self::PERMISSION_SLUG)
            ->value('id');

        $assignments = DB::table('roles')
            ->where('is_system', true)
            ->whereIn('slug', self::LEGACY_ROLE_SLUGS)
            ->pluck('id')
            ->map(fn (int $roleId): array => [
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ])
            ->all();

        if ($assignments !== []) {
            DB::table('permission_role')->insertOrIgnore($assignments);
        }
    }
};
