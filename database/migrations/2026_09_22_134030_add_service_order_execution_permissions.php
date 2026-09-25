<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'ordens_servico.atribuir' => 'Atribuir ordens de serviço',
        'ordens_servico.rejeitar' => 'Rejeitar ordens de serviço',
        'ordens_servico.cancelar' => 'Cancelar ordens de serviço',
        'ordens_servico.iniciar' => 'Iniciar ordens de serviço',
        'ordens_servico.atualizar' => 'Atualizar ordens de serviço',
        'ordens_servico.pausar' => 'Pausar ordens de serviço',
        'ordens_servico.concluir' => 'Concluir ordens de serviço',
        'ordens_servico.registrar_diagnostico' => 'Registrar diagnóstico',
        'ordens_servico.registrar_material' => 'Registrar material',
        'ordens_servico.registrar_tempo' => 'Registrar tempo trabalhado',
        'ordens_servico.registrar_custo' => 'Registrar custo',
        'ordens_servico.iniciar_emergencial' => 'Iniciar atendimento emergencial',
        'ocorrencias.encerrar' => 'Encerrar ocorrência resolvida',
        'ocorrencias.reabrir' => 'Reabrir ocorrência resolvida',
    ];

    /** @var list<string> */
    private const TECHNICIAN_PERMISSIONS = [
        'ordens_servico.iniciar',
        'ordens_servico.atualizar',
        'ordens_servico.pausar',
        'ordens_servico.concluir',
        'ordens_servico.registrar_diagnostico',
        'ordens_servico.registrar_material',
        'ordens_servico.registrar_tempo',
        'ordens_servico.registrar_custo',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], ['name' => $name]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_keys(self::PERMISSIONS))
            ->pluck('id', 'slug');

        DB::table('roles')
            ->where('is_system', true)
            ->whereIn('slug', ['administrador-rede', 'administrador-escola', 'gestor', 'tecnico'])
            ->get(['id', 'slug'])
            ->each(function (object $role) use ($permissionIds): void {
                $slugs = $role->slug === 'tecnico' ? self::TECHNICIAN_PERMISSIONS : array_keys(self::PERMISSIONS);
                $assignments = collect($slugs)->map(fn (string $slug): array => [
                    'permission_id' => $permissionIds[$slug],
                    'role_id' => $role->id,
                ])->all();

                DB::table('permission_role')->insertOrIgnore($assignments);
            });
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_keys(self::PERMISSIONS))
            ->pluck('id');
        $roleIds = DB::table('roles')
            ->where('is_system', true)
            ->whereIn('slug', ['administrador-rede', 'administrador-escola', 'gestor', 'tecnico'])
            ->pluck('id');

        DB::table('permission_role')
            ->whereIn('permission_id', $permissionIds)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }
};
