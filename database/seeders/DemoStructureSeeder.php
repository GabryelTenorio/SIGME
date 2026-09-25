<?php

namespace Database\Seeders;

use App\Models\Environment;
use App\Models\OccurrenceCategory;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\AccessCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoStructureSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $catalog = app(AccessCatalog::class);

            $independent = Organization::query()->updateOrCreate(
                ['slug' => 'escola-independente-demonstracao'],
                [
                    'name' => 'Escola Independente de Demonstração',
                    'mode' => 'single_school',
                    'approval_threshold' => 500,
                    'allows_student_representative' => true,
                    'is_active' => true,
                ],
            );
            $independentRoles = $catalog->provision($independent);
            $independentSchool = School::query()->updateOrCreate(
                ['organization_id' => $independent->id, 'code' => 'UNICA'],
                ['name' => 'Escola Independente de Demonstração', 'is_active' => true],
            );

            $network = Organization::query()->updateOrCreate(
                ['slug' => 'rede-escolar-demonstracao'],
                [
                    'name' => 'Rede Escolar de Demonstração',
                    'mode' => 'network',
                    'approval_threshold' => 1000,
                    'allows_student_representative' => false,
                    'is_active' => true,
                ],
            );
            $networkRoles = $catalog->provision($network);
            $networkSchools = collect([
                'CENTRO' => 'Escola Unidade Centro',
                'NORTE' => 'Escola Unidade Norte',
                'SUL' => 'Escola Unidade Sul',
            ])->map(fn (string $name, string $code) => School::query()->updateOrCreate(
                ['organization_id' => $network->id, 'code' => $code],
                ['name' => $name, 'is_active' => true],
            ));

            $this->seedEnvironments($independentSchool, [
                ['SALA-01', 'Sala 01', 'classroom'], ['SALA-02', 'Sala 02', 'classroom'],
                ['LAB-INF', 'Laboratório de Informática', 'laboratory'], ['SECRETARIA', 'Secretaria', 'office'],
                ['QUADRA', 'Quadra', 'court'],
            ]);
            $this->seedEnvironments($networkSchools['CENTRO'], [
                ['SALA-01', 'Sala 01', 'classroom'], ['LAB-01', 'Laboratório 01', 'laboratory'], ['BIBLIOTECA', 'Biblioteca', 'library'],
            ]);
            $this->seedEnvironments($networkSchools['NORTE'], [
                ['SALA-01', 'Sala 01', 'classroom'], ['SALA-02', 'Sala 02', 'classroom'], ['LAB-01', 'Laboratório 01', 'laboratory'],
            ]);

            $this->seedCategories($independent);
            $this->seedCategories($network);

            $independentManager = $this->createIllustrativeUser(
                $independent,
                'Gestora Fictícia',
                'gestora.independente@exemplo.invalid',
            );
            $independentManager->schools()->syncWithoutDetaching([$independentSchool->id]);
            RoleAssignment::query()->firstOrCreate([
                'user_id' => $independentManager->id,
                'role_id' => $independentRoles['gestor']->id,
                'school_id' => $independentSchool->id,
            ]);

            $networkAdmin = $this->createIllustrativeUser(
                $network,
                'Administrador Fictício da Rede',
                'administrador.rede@exemplo.invalid',
            );
            $networkAdmin->schools()->syncWithoutDetaching($networkSchools->pluck('id'));
            RoleAssignment::query()->firstOrCreate([
                'user_id' => $networkAdmin->id,
                'role_id' => $networkRoles['administrador-rede']->id,
                'school_id' => null,
            ]);

            $technician = $this->createIllustrativeUser(
                $network,
                'Técnica Fictícia',
                'tecnica.rede@exemplo.invalid',
            );
            $technician->schools()->syncWithoutDetaching($networkSchools->pluck('id'));
            foreach ($networkSchools as $school) {
                RoleAssignment::query()->firstOrCreate([
                    'user_id' => $technician->id,
                    'role_id' => $networkRoles['tecnico']->id,
                    'school_id' => $school->id,
                ]);
            }
        });
    }

    private function createIllustrativeUser(Organization $organization, string $name, string $email): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'organization_id' => $organization->id,
                'name' => $name,
                'password' => Str::password(32),
                'password_set_at' => now(),
                'is_platform_admin' => false,
                'is_active' => false,
            ],
        );
    }

    /** @param list<array{0: string, 1: string, 2: string}> $definitions */
    private function seedEnvironments(School $school, array $definitions): void
    {
        foreach ($definitions as [$code, $name, $type]) {
            Environment::query()->updateOrCreate(
                ['school_id' => $school->id, 'code' => $code],
                ['parent_id' => null, 'name' => $name, 'type' => $type, 'is_active' => true],
            );
        }
    }

    private function seedCategories(Organization $organization): void
    {
        $names = [
            'Elétrica', 'Hidráulica', 'Informática / TI', 'Equipamentos', 'Mobiliário',
            'Climatização', 'Infraestrutura Predial', 'Limpeza', 'Segurança', 'Outros',
        ];

        foreach ($names as $index => $name) {
            $identifier = Str::slug($name);
            $category = OccurrenceCategory::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'identifier' => $identifier],
                [
                    'name' => $name,
                    'description' => null,
                    'is_active' => true,
                    'display_order' => ($index + 1) * 10,
                    'is_fallback' => $name === 'Outros',
                ],
            );
            $category->schools()->syncWithoutDetaching($organization->schools()->pluck('id'));
        }
    }
}
