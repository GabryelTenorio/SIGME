<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSION_SLUGS = [
        'ambientes.criar',
        'categorias.criar',
    ];

    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', self::PERMISSION_SLUGS)
            ->pluck('id');
        $roleIds = DB::table('roles')
            ->where('slug', 'gestor')
            ->where('is_system', true)
            ->pluck('id');

        $assignments = $roleIds->flatMap(
            fn (int $roleId) => $permissionIds->map(fn (int $permissionId): array => [
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]),
        )->all();

        if ($assignments !== []) {
            DB::table('permission_role')->insertOrIgnore($assignments);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', self::PERMISSION_SLUGS)
            ->pluck('id');
        $roleIds = DB::table('roles')
            ->where('slug', 'gestor')
            ->where('is_system', true)
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $permissionIds)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }
};
