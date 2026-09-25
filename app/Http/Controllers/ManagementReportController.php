<?php

namespace App\Http\Controllers;

use App\Models\Occurrence;
use App\Models\School;
use App\Models\ServiceOrder;
use App\Support\DurationFormatter;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagementReportController extends Controller
{
    public function index(Request $request): View
    {
        [$schools, $schoolIds, $dateFrom, $dateTo] = $this->reportContext($request);
        $occurrences = $this->occurrenceQuery($schoolIds, $dateFrom, $dateTo)->get();
        $orders = $this->serviceOrderQuery($schoolIds, $dateFrom, $dateTo)->get();
        $completedOrders = $orders->where('status', 'CONCLUIDA');
        $totalCost = $orders->reduce(
            fn (BigDecimal $total, ServiceOrder $order): BigDecimal => $total->plus($order->totalCost()),
            BigDecimal::zero(),
        )->toScale(2);
        $averageResolutionMinutes = $completedOrders->isEmpty()
            ? 0
            : (int) round($completedOrders->average(fn (ServiceOrder $order): int => $order->requestElapsedMinutes()));

        return view('reports.index', [
            'schools' => $schools,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'occurrences' => $occurrences,
            'orders' => $orders,
            'metrics' => [
                ['label' => 'Ocorrências no período', 'value' => $occurrences->count(), 'hint' => 'Todos os estados', 'icon' => 'occurrence'],
                ['label' => 'Ocorrências em aberto', 'value' => $occurrences->whereNotIn('status', ['NAO_PROCEDE', 'DUPLICADA', 'RESOLVIDA', 'ENCERRADA'])->count(), 'hint' => 'Demandam acompanhamento', 'icon' => 'occurrence'],
                ['label' => 'OS concluídas', 'value' => $completedOrders->count(), 'hint' => $orders->count().' OS no período', 'icon' => 'service-order'],
                ['label' => 'OS vencidas', 'value' => $orders->filter(fn (ServiceOrder $order): bool => $order->due_date?->lt(today()) && ! in_array($order->status, ['CONCLUIDA', 'REJEITADA', 'CANCELADA'], true))->count(), 'hint' => 'Prazo anterior a hoje', 'icon' => 'service-order'],
                ['label' => 'Tempo médio de solução', 'value' => DurationFormatter::minutes($averageResolutionMinutes), 'hint' => 'Abertura até conclusão', 'icon' => 'chart'],
                ['label' => 'Custo registrado', 'value' => $totalCost, 'hint' => 'Materiais e demais custos', 'icon' => 'chart', 'currency' => true],
            ],
            'occurrenceStatuses' => $this->distribution($occurrences, 'status', Occurrence::STATUS_LABELS),
            'orderStatuses' => $this->distribution($orders, 'status', ServiceOrder::STATUS_LABELS),
            'priorities' => $this->distribution(
                $occurrences->map(fn (Occurrence $occurrence): array => ['priority' => $occurrence->priority()]),
                'priority',
                Occurrence::PRIORITIES,
            ),
            'topCategories' => $occurrences->groupBy('occurrence_category_id')->map(fn (Collection $items): array => [
                'label' => $items->first()->category->name,
                'value' => $items->count(),
            ])->sortByDesc('value')->take(5)->values(),
        ]);
    }

    public function exportOccurrences(Request $request): StreamedResponse
    {
        [, $schoolIds, $dateFrom, $dateTo] = $this->reportContext($request);
        $occurrences = $this->occurrenceQuery($schoolIds, $dateFrom, $dateTo)->get();

        return $this->csvResponse('ocorrencias-'.$dateFrom->toDateString().'-'.$dateTo->toDateString().'.csv', [
            ['Protocolo', 'Escola', 'Abertura', 'Estado', 'Prioridade', 'Categoria', 'Ambiente', 'Título'],
            ...$occurrences->map(fn (Occurrence $occurrence): array => [
                $occurrence->protocol,
                $occurrence->school->name,
                $occurrence->created_at->format('d/m/Y H:i'),
                Occurrence::STATUS_LABELS[$occurrence->status],
                Occurrence::PRIORITIES[$occurrence->priority()],
                $occurrence->category->name,
                $occurrence->environment->name,
                $occurrence->title,
            ])->all(),
        ]);
    }

    public function exportServiceOrders(Request $request): StreamedResponse
    {
        [, $schoolIds, $dateFrom, $dateTo] = $this->reportContext($request);
        $orders = $this->serviceOrderQuery($schoolIds, $dateFrom, $dateTo)->get();

        return $this->csvResponse('ordens-servico-'.$dateFrom->toDateString().'-'.$dateTo->toDateString().'.csv', [
            ['Código', 'Escola', 'Ocorrência', 'Estado', 'Prioridade', 'Responsável', 'Abertura', 'Conclusão', 'Tempo decorrido', 'Tempo trabalhado', 'Estimado', 'Materiais', 'Outros custos', 'Total'],
            ...$orders->map(fn (ServiceOrder $order): array => [
                $order->code,
                $order->school->name,
                $order->occurrence->protocol,
                ServiceOrder::STATUS_LABELS[$order->status],
                Occurrence::PRIORITIES[$order->priority_snapshot],
                $order->assignedUser?->name ?? 'A definir',
                $order->occurrence->created_at->format('d/m/Y H:i'),
                $order->completed_at?->format('d/m/Y H:i') ?? '',
                DurationFormatter::minutes($order->requestElapsedMinutes()),
                DurationFormatter::minutes($order->workedMinutes()),
                $order->estimated_cost,
                $order->materialTotal(),
                $order->additionalCostTotal(),
                $order->totalCost(),
            ])->all(),
        ]);
    }

    /**
     * @return array{0: Collection<int, School>, 1: Collection<int, int>, 2: Carbon, 3: Carbon}
     */
    private function reportContext(Request $request): array
    {
        $schools = $request->user()->accessibleSchools()
            ->filter(fn (School $school): bool => $request->user()->hasPermission('indicadores.visualizar', $school))
            ->values();
        abort_if($schools->isEmpty(), 403);

        $validated = $request->validate([
            'school_id' => ['nullable', 'integer', Rule::in($schools->pluck('id')->all())],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $dateFrom = isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : now()->subDays(29)->startOfDay();
        $dateTo = isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : now()->endOfDay();
        $schoolIds = isset($validated['school_id'])
            ? collect([(int) $validated['school_id']])
            : $schools->pluck('id');

        return [$schools, $schoolIds, $dateFrom, $dateTo];
    }

    /** @param Collection<int, int> $schoolIds */
    private function occurrenceQuery(Collection $schoolIds, Carbon $dateFrom, Carbon $dateTo): Builder
    {
        return Occurrence::query()
            ->with(['school', 'category', 'environment'])
            ->whereIn('school_id', $schoolIds)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest();
    }

    /** @param Collection<int, int> $schoolIds */
    private function serviceOrderQuery(Collection $schoolIds, Carbon $dateFrom, Carbon $dateTo): Builder
    {
        return ServiceOrder::query()
            ->with(['school', 'occurrence', 'assignedUser', 'materials', 'costs', 'workLogs', 'histories'])
            ->whereIn('school_id', $schoolIds)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest();
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @param  array<string, string>  $labels
     * @return Collection<int, array{label: string, value: int, percentage: int}>
     */
    private function distribution(Collection $items, string $key, array $labels): Collection
    {
        $total = max(1, $items->count());

        return $items->countBy($key)
            ->map(fn (int $value, string $status): array => [
                'label' => $labels[$status] ?? $status,
                'value' => $value,
                'percentage' => (int) round(($value / $total) * 100),
            ])
            ->sortByDesc('value')
            ->values();
    }

    /** @param list<list<string|int|float|null>> $rows */
    private function csvResponse(string $filename, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($stream, $row, ';', '"', '');
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
