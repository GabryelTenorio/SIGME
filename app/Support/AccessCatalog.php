<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AccessCatalog
{
    /** @return array<string, string> */
    public static function permissions(): array
    {
        return [
            'escolas.gerenciar' => 'Gerenciar escolas',
            'usuarios.gerenciar' => 'Gerenciar usuários',
            'configuracoes.gerenciar' => 'Gerenciar configurações',
            'ambientes.visualizar' => 'Visualizar ambientes',
            'ambientes.criar' => 'Criar ambientes',
            'ambientes.editar' => 'Editar ambientes',
            'ambientes.desativar' => 'Desativar ambientes',
            'ambientes.gerenciar' => 'Gerenciar ambientes',
            'categorias.visualizar' => 'Visualizar categorias de ocorrência',
            'categorias.criar' => 'Criar categorias de ocorrência',
            'categorias.editar' => 'Editar categorias de ocorrência',
            'categorias.desativar' => 'Desativar categorias de ocorrência',
            'categorias.gerenciar_disponibilidade' => 'Gerenciar disponibilidade de categorias',
            'ocorrencias.criar' => 'Criar ocorrências',
            'ocorrencias.visualizar_proprias' => 'Visualizar ocorrências próprias',
            'ocorrencias.visualizar_escola' => 'Visualizar ocorrências da escola',
            'ocorrencias.visualizar_rede' => 'Visualizar ocorrências da rede',
            'ocorrencias.visualizar_encaminhadas' => 'Visualizar ocorrências encaminhadas',
            'ocorrencias.triar' => 'Realizar triagem de ocorrências',
            'ocorrencias.confirmar_prioridade' => 'Confirmar prioridade de ocorrências',
            'ocorrencias.solicitar_informacoes' => 'Solicitar informações da ocorrência',
            'ocorrencias.encaminhar' => 'Encaminhar ocorrência para manutenção',
            'ocorrencias.marcar_duplicada' => 'Marcar ocorrência como duplicada',
            'ocorrencias.marcar_nao_procede' => 'Marcar ocorrência como não procede',
            'ocorrencias.encerrar' => 'Encerrar ocorrência resolvida',
            'ocorrencias.reabrir' => 'Reabrir ocorrência resolvida',
            'ordens_servico.criar' => 'Criar ordens de serviço',
            'ordens_servico.visualizar' => 'Visualizar ordens de serviço',
            'ordens_servico.aprovar' => 'Aprovar ordens de serviço e custos',
            'ordens_servico.executar' => 'Executar ordens de serviço',
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
            'indicadores.visualizar' => 'Visualizar indicadores',
        ];
    }

    /** @return array<string, array{name: string, scope: string, permissions: list<string>}> */
    public static function roles(): array
    {
        return [
            'administrador-rede' => [
                'name' => 'Administrador da rede',
                'scope' => 'organization',
                'permissions' => array_keys(self::permissions()),
            ],
            'administrador-escola' => [
                'name' => 'Administrador da escola',
                'scope' => 'school',
                'permissions' => array_keys(self::permissions()),
            ],
            'gestor' => [
                'name' => 'Direção / Gestor',
                'scope' => 'school',
                'permissions' => [
                    'ambientes.visualizar',
                    'categorias.visualizar',
                    'ocorrencias.criar',
                    'ocorrencias.visualizar_proprias',
                    'ocorrencias.visualizar_escola',
                    'ocorrencias.triar',
                    'ocorrencias.confirmar_prioridade',
                    'ocorrencias.solicitar_informacoes',
                    'ocorrencias.encaminhar',
                    'ocorrencias.marcar_duplicada',
                    'ocorrencias.marcar_nao_procede',
                    'ordens_servico.criar',
                    'ordens_servico.visualizar',
                    'ordens_servico.aprovar',
                    'ordens_servico.atribuir', 'ordens_servico.rejeitar', 'ordens_servico.cancelar', 'ordens_servico.iniciar_emergencial',
                    'ocorrencias.encerrar', 'ocorrencias.reabrir',
                    'indicadores.visualizar',
                ],
            ],
            'solicitante' => [
                'name' => 'Solicitante',
                'scope' => 'school',
                'permissions' => ['ambientes.visualizar', 'ocorrencias.criar', 'ocorrencias.visualizar_proprias'],
            ],
            'tecnico' => [
                'name' => 'Técnico / Manutenção',
                'scope' => 'school',
                'permissions' => [
                    'ambientes.visualizar',
                    'ocorrencias.criar',
                    'ocorrencias.visualizar_proprias',
                    'ocorrencias.visualizar_encaminhadas',
                    'ordens_servico.visualizar',
                    'ordens_servico.executar',
                    'ordens_servico.iniciar', 'ordens_servico.atualizar', 'ordens_servico.pausar', 'ordens_servico.concluir',
                    'ordens_servico.registrar_diagnostico', 'ordens_servico.registrar_material', 'ordens_servico.registrar_tempo', 'ordens_servico.registrar_custo',
                ],
            ],
            'representante-aluno' => [
                'name' => 'Representante de aluno',
                'scope' => 'school',
                'permissions' => ['ambientes.visualizar', 'ocorrencias.criar', 'ocorrencias.visualizar_proprias'],
            ],
        ];
    }

    /** @return Collection<string, Role> */
    public function provision(Organization $organization): Collection
    {
        return DB::transaction(function () use ($organization): Collection {
            $permissions = collect(self::permissions())->mapWithKeys(
                fn (string $name, string $slug) => [
                    $slug => Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name]),
                ],
            );

            return collect(self::roles())->mapWithKeys(function (array $definition, string $slug) use ($organization, $permissions) {
                $role = Role::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'slug' => $slug],
                    ['name' => $definition['name'], 'scope' => $definition['scope'], 'is_system' => true],
                );
                $role->permissions()->sync(
                    collect($definition['permissions'])->map(fn (string $permission) => $permissions[$permission]->id),
                );

                return [$slug => $role];
            });
        });
    }
}
