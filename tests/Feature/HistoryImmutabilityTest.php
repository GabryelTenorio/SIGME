<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\Occurrence;
use App\Models\OccurrenceCategory;
use App\Models\OccurrenceHistory;
use App\Models\Organization;
use App\Models\School;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderHistory;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class HistoryImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_occurrence_and_service_order_histories_are_append_only_through_models(): void
    {
        [$occurrence, $actor] = $this->occurrenceAndActor();
        $serviceOrder = $this->serviceOrder($occurrence, $actor);
        $occurrenceHistory = OccurrenceHistory::query()->create([
            'occurrence_id' => $occurrence->id,
            'actor_id' => $actor->id,
            'event_type' => 'created',
            'new_values' => ['status' => 'ABERTA'],
        ]);
        $serviceOrderHistory = ServiceOrderHistory::query()->create([
            'service_order_id' => $serviceOrder->id,
            'actor_id' => $actor->id,
            'event_type' => 'created',
            'new_values' => ['status' => 'APROVADA'],
        ]);

        $this->assertHistoryIsAppendOnly($occurrenceHistory, 'occurrence_histories');
        $this->assertHistoryIsAppendOnly($serviceOrderHistory, 'service_order_histories');
    }

    public function test_database_restricts_parent_deletion_that_would_erase_history(): void
    {
        [$occurrence, $actor] = $this->occurrenceAndActor();
        $occurrenceHistory = OccurrenceHistory::query()->create([
            'occurrence_id' => $occurrence->id,
            'actor_id' => $actor->id,
            'event_type' => 'created',
        ]);

        $this->assertDeletionIsRestricted(
            fn (): int => DB::table('occurrences')->where('id', $occurrence->id)->delete(),
            'occurrences',
            $occurrence->id,
            'occurrence_histories',
            $occurrenceHistory->id,
        );

        [$secondOccurrence, $secondActor] = $this->occurrenceAndActor();
        $serviceOrder = $this->serviceOrder($secondOccurrence, $secondActor);
        $serviceOrderHistory = ServiceOrderHistory::query()->create([
            'service_order_id' => $serviceOrder->id,
            'actor_id' => $secondActor->id,
            'event_type' => 'created',
        ]);

        $this->assertDeletionIsRestricted(
            fn (): int => DB::table('service_orders')->where('id', $serviceOrder->id)->delete(),
            'service_orders',
            $serviceOrder->id,
            'service_order_histories',
            $serviceOrderHistory->id,
        );
    }

    public function test_service_order_schema_has_one_cost_source_and_history_starts_restrictive(): void
    {
        $columns = Schema::getColumnListing('service_orders');

        $this->assertNotContains('external_cost', $columns);
        $this->assertNotContains('other_cost', $columns);
        $this->assertNotContains('external_cost', (new ServiceOrder)->getFillable());
        $this->assertNotContains('other_cost', (new ServiceOrder)->getFillable());

        $migration = file_get_contents(database_path('migrations/2026_08_27_170121_create_service_order_histories_table.php'));

        $this->assertIsString($migration);
        $this->assertStringContainsString("foreignId('service_order_id')->constrained()->restrictOnDelete()", $migration);
        $this->assertStringNotContainsString("foreignId('service_order_id')->constrained()->cascadeOnDelete()", $migration);
    }

    private function assertHistoryIsAppendOnly(OccurrenceHistory|ServiceOrderHistory $history, string $table): void
    {
        $originalEventType = $history->event_type;

        try {
            $history->update(['event_type' => 'tampered']);
            $this->fail("{$table} permitiu atualização de um evento existente.");
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $history->refresh();
        $this->assertSame($originalEventType, $history->event_type);

        try {
            $history->delete();
            $this->fail("{$table} permitiu exclusão de um evento existente.");
        } catch (LogicException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas($table, [
            'id' => $history->id,
            'event_type' => $originalEventType,
        ]);
    }

    private function assertDeletionIsRestricted(
        callable $deleteParent,
        string $parentTable,
        int $parentId,
        string $historyTable,
        int $historyId,
    ): void {
        try {
            $deleteParent();
            $this->fail("{$parentTable} foi excluída junto com seu histórico.");
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas($parentTable, ['id' => $parentId]);
        $this->assertDatabaseHas($historyTable, ['id' => $historyId]);
    }

    /** @return array{Occurrence, User} */
    private function occurrenceAndActor(): array
    {
        $organization = Organization::factory()->create(['mode' => 'network']);
        $school = School::factory()->for($organization)->create();
        $environment = Environment::factory()->for($school)->create();
        $category = OccurrenceCategory::factory()->for($organization)->create();
        $category->schools()->attach($school);
        $actor = User::factory()->for($organization)->create();
        $actor->schools()->attach($school);
        $occurrence = Occurrence::factory()->create([
            'organization_id' => $organization->id,
            'school_id' => $school->id,
            'environment_id' => $environment->id,
            'occurrence_category_id' => $category->id,
            'reporter_id' => $actor->id,
        ]);

        return [$occurrence, $actor];
    }

    private function serviceOrder(Occurrence $occurrence, User $actor): ServiceOrder
    {
        return ServiceOrder::factory()->create([
            'organization_id' => $occurrence->organization_id,
            'school_id' => $occurrence->school_id,
            'occurrence_id' => $occurrence->id,
            'created_by' => $actor->id,
        ]);
    }
}
