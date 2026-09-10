<?php

namespace App\Http\Controllers;

use App\Http\Requests\OccurrenceRequest;
use App\Models\Environment;
use App\Models\Occurrence;
use App\Models\OccurrenceCategory;
use App\Models\OccurrenceHistory;
use App\Models\OccurrenceSequence;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class OccurrenceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Occurrence::class);
        $request->validate(['date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d']]);
        $user = $request->user();
        $accessible = $user->accessibleSchools();
        $broadIds = $accessible->filter(fn (School $school) => $user->hasPermission('ocorrencias.visualizar_escola', $school) || $user->hasPermission('ocorrencias.triar', $school) || $user->hasPermission('ocorrencias.visualizar_rede'))->pluck('id');
        $forwardedIds = $accessible->filter(fn (School $school) => $user->hasPermission('ocorrencias.visualizar_encaminhadas', $school))->pluck('id');

        $occurrences = Occurrence::query()->with(['school', 'environment', 'category', 'reporter'])
            ->whereIn('school_id', $accessible->pluck('id'))
            ->when(! $user->is_platform_admin, fn (Builder $query) => $query->where(function (Builder $scope) use ($user, $broadIds, $forwardedIds): void {
                $scope->where('reporter_id', $user->id)->orWhereIn('school_id', $broadIds)
                    ->orWhere(fn (Builder $forwarded) => $forwarded->whereIn('school_id', $forwardedIds)->where('status', 'ENCAMINHADA'));
            }))
            ->when($request->integer('school_id'), fn (Builder $query) => $query->where('school_id', $request->integer('school_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->integer('category_id'), fn (Builder $query) => $query->where('occurrence_category_id', $request->integer('category_id')))
            ->when($request->integer('environment_id'), fn (Builder $query) => $query->where('environment_id', $request->integer('environment_id')))
            ->when($request->filled('impact'), fn (Builder $query) => $query->where('impact', $request->string('impact')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->when($request->filled('priority'), fn (Builder $query) => $query->where(fn (Builder $p) => $p->where('confirmed_priority', $request->string('priority'))->orWhere(fn (Builder $s) => $s->whereNull('confirmed_priority')->where('suggested_priority', $request->string('priority')))))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where(fn (Builder $q) => $q->where('protocol', 'like', $search)->orWhere('title', 'like', $search));
            })
            ->latest()->get();

        return view('occurrences.index', [
            'occurrences' => $occurrences, 'schools' => $accessible,
            'categories' => OccurrenceCategory::query()->whereIn('organization_id', $accessible->pluck('organization_id'))->orderBy('name')->get(),
            'environments' => Environment::query()->whereIn('school_id', $accessible->pluck('id'))->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Occurrence::class);
        $schools = $request->user()->accessibleSchools()->filter(fn (School $school) => $request->user()->hasPermission('ocorrencias.criar', $school))->values();
        abort_if($schools->isEmpty(), 403);
        $schoolId = $schools->pluck('id')->contains($request->integer('school_id')) ? $request->integer('school_id') : $schools->first()->id;
        $school = $schools->firstWhere('id', $schoolId);

        return view('occurrences.create', [
            'schools' => $schools, 'selectedSchoolId' => $schoolId,
            'environments' => Environment::query()->availableForNewOccurrences()->where('school_id', $schoolId)->orderBy('name')->get(),
            'categories' => OccurrenceCategory::query()->availableForSchool($school)->orderBy('display_order')->orderBy('name')->get(),
        ]);
    }

    public function store(OccurrenceRequest $request): RedirectResponse
    {
        $storedPaths = [];

        try {
            $occurrence = DB::transaction(function () use ($request, &$storedPaths): Occurrence {
                $school = School::query()->findOrFail($request->integer('school_id'));
                $year = now()->year;
                DB::table('occurrence_sequences')->insertOrIgnore(['school_id' => $school->id, 'year' => $year, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $counter = OccurrenceSequence::query()->where('school_id', $school->id)->where('year', $year)->lockForUpdate()->sole();
                $sequence = $counter->next_number;
                $counter->update(['next_number' => $sequence + 1]);
                $data = $request->validated();
                $attachments = $data['attachments'] ?? [];
                unset($data['attachments']);
                $data += [
                    'organization_id' => $school->organization_id, 'reporter_id' => $request->user()->id,
                    'protocol_year' => $year, 'protocol_sequence' => $sequence,
                    'protocol' => sprintf('SIG-%s-%d-%06d', $school->code, $year, $sequence),
                    'status' => 'ABERTA', 'suggested_priority' => Occurrence::suggestedPriority($data['impact'], $data['perceived_urgency']),
                ];
                $occurrence = Occurrence::query()->create($data);

                foreach ($attachments as $file) {
                    $path = $file->store('occurrences/'.$occurrence->id, 'local');
                    if ($path === false) {
                        throw new RuntimeException('Não foi possível armazenar o anexo privado.');
                    }
                    $storedPaths[] = $path;
                    $occurrence->attachments()->create([
                        'uploaded_by' => $request->user()->id,
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => (int) $file->getSize(),
                    ]);
                }

                OccurrenceHistory::query()->create(['occurrence_id' => $occurrence->id, 'actor_id' => $request->user()->id, 'event_type' => 'created', 'new_values' => ['status' => 'ABERTA', 'suggested_priority' => $occurrence->suggested_priority, 'attachment_count' => count($attachments)]]);

                return $occurrence;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }

        return redirect()->route('occurrences.show', $occurrence)->with('success', 'Ocorrência registrada. Protocolo: '.$occurrence->protocol.'. Sua solicitação foi encaminhada para análise.');
    }

    public function show(Occurrence $occurrence): View
    {
        $this->authorize('view', $occurrence);

        return view('occurrences.show', [
            'occurrence' => $occurrence->load(['school.organization', 'environment', 'category', 'reporter', 'triageResponsible', 'duplicateOf', 'attachments', 'histories.actor', 'serviceOrders.assignedUser']),
            'duplicateCandidates' => Occurrence::query()->where('school_id', $occurrence->school_id)->whereKeyNot($occurrence->id)->latest()->limit(100)->get(),
        ]);
    }
}
