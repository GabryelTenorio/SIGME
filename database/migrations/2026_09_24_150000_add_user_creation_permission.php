<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const ALLOWED_ROLE_SLUGS = [
        'administrador-rede',
        'administrador-escola',
    ];

    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('slug', 'usuarios.criar')
            ->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'Criar usuários',
                'slug' => 'usuarios.criar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permissions')
                ->where('id', $permissionId)
                ->update(['name' => 'Criar usuários', 'updated_at' => now()]);
        }

        $assignments = DB::table('roles')
            ->whereIn('slug', self::ALLOWED_ROLE_SLUGS)
            ->where('is_system', true)
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

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('slug', 'usuarios.criar')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->delete();
        DB::table('permissions')
            ->where('id', $permissionId)
            ->delete();
    }
};
