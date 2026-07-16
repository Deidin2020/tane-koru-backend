<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\UpsertDailySummaryRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\DailyReportResource;
use App\Http\Resources\CompanyVisitResource;
use App\Http\Resources\ProjectVisitResource;
use App\Models\Client;
use App\Models\CompanyVisit;
use App\Models\DailyReport;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectVisit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $todayLocal = now($this->reportTimezone())->startOfDay();
        $last7Local = $todayLocal->copy()->subDays(6);
        $today = $todayLocal->format('Y-m-d');
        $last30 = $todayLocal->copy()->subDays(29)->format('Y-m-d');

        $stats = [
            'project_visits' => $this->visitsForReportDate(ProjectVisit::class, $today, $today)->count(),
            'company_visits' => $this->visitsForReportDate(CompanyVisit::class, $today, $today)->count(),
            'new_clients' => $this->clientsForReportDate($today, $today)->count(),
            'presentations' => $this->clientsForReportDate($today, $today)->where('presentation_completed', true)->count(),
        ];

        $pipelineTotals = [
            'follow_up' => Client::query()->where('status', 'follow_up')->count(),
            'reservation' => Client::query()->where('status', 'reservation')->count(),
            'deal' => Client::query()->where('status', 'deal')->count(),
        ];

        $distribution = collect(['new', 'presentation_completed', 'follow_up', 'reservation', 'deal', 'not_interested'])
            ->map(fn (string $status) => [
                'status' => $status,
                'count' => Client::query()->where('status', $status)->count(),
            ])->values();

        $weeklyActivity = collect(range(0, 6))->map(function (int $offset) use ($last7Local): array {
            $date = $last7Local->copy()->addDays($offset);
            $dateValue = $date->format('Y-m-d');
            return [
                'date' => $dateValue,
                'project_visits' => $this->visitsForReportDate(ProjectVisit::class, $dateValue, $dateValue)->count(),
                'company_visits' => $this->visitsForReportDate(CompanyVisit::class, $dateValue, $dateValue)->count(),
                'new_clients' => $this->clientsForReportDate($dateValue, $dateValue)->count(),
                'presentations' => $this->clientsForReportDate($dateValue, $dateValue)->where('presentation_completed', true)->count(),
            ];
        });

        $profiles = Profile::query()->with('user')->orderBy('full_name')->get();
        $clients = $this->clientsForReportDate($last30, $today)->get();
        $salespeople = $profiles->map(function (Profile $profile) use ($clients): array {
            $mine = $clients->where('assigned_salesperson_id', $profile->id);

            return [
                'salesperson_id' => $profile->id,
                'name' => $profile->full_name ?? $profile->user?->email ?? '—',
                'clients' => $mine->count(),
                'reservations' => $mine->where('status', 'reservation')->count(),
                'deals' => $mine->where('status', 'deal')->count(),
            ];
        })->filter(fn (array $row) => $row['clients'] > 0)
            ->sortByDesc('clients')
            ->take(8)
            ->values();

        return response()->json([
            'today' => $stats,
            'pipeline_totals' => $pipelineTotals,
            'pipeline_distribution' => $distribution,
            'weekly_activity' => $weeklyActivity,
            'salesperson_last_30d' => $salespeople,
        ]);
    }

    public function daily(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveDateRange($request);
        $project = Project::resolveDefault();
        $projectVisits = $this->visitsForReportDate(ProjectVisit::class, $from, $to, ['agency', 'salesRep.user']);
        $companyVisits = $this->visitsForReportDate(CompanyVisit::class, $from, $to, ['agency', 'salesRep.user']);
        $clients = $this->clientsForReportDate($from, $to)
            ->with(['agency', 'assignedSalesperson.user'])
            ->orderBy('created_at')
            ->get();

        $summaryRecord = null;
        $fromDate = Carbon::parse($from, $this->reportTimezone());
        $toDate = Carbon::parse($to, $this->reportTimezone());
        if ($project && $fromDate->isSameDay($toDate)) {
            $summaryRecord = DailyReport::query()
                ->where('project_id', $project->id)
                ->whereDate('report_date', $fromDate->format('Y-m-d'))
                ->first();
        }

        return response()->json([
            'range' => [
                'from' => $fromDate->format('Y-m-d'),
                'to' => $toDate->format('Y-m-d'),
            ],
            'project_visits' => [
                'total' => $projectVisits->count(),
                'agencies' => $projectVisits->pluck('agency.name')->filter()->unique()->values(),
                'sales_reps' => $projectVisits->map(fn (ProjectVisit $visit) => $visit->salesRep?->full_name ?? $visit->salesRep?->user?->email)->filter()->unique()->values(),
                'items' => ProjectVisitResource::collection($projectVisits)->resolve(),
            ],
            'company_visits' => [
                'total' => $companyVisits->count(),
                'categories' => $companyVisits->pluck('category')->filter()->unique()->values(),
                'sales_reps' => $companyVisits->map(fn (CompanyVisit $visit) => $visit->salesRep?->full_name ?? $visit->salesRep?->user?->email)->filter()->unique()->values(),
                'items' => CompanyVisitResource::collection($companyVisits)->resolve(),
            ],
            'presentations' => [
                'total' => $clients->where('presentation_completed', true)->count(),
                'client_names' => $clients->where('presentation_completed', true)->pluck('client_name')->values(),
                'items' => ClientResource::collection($clients->where('presentation_completed', true)->values())->resolve(),
            ],
            'clients' => ClientResource::collection($clients)->resolve(),
            'summary' => [
                'text' => $summaryRecord?->summary,
                'editable' => $fromDate->isSameDay($toDate) && $fromDate->isSameDay(now($this->reportTimezone())),
            ],
        ]);
    }

    public function upsertDailySummary(UpsertDailySummaryRequest $request): DailyReportResource|JsonResponse
    {
        $reportDate = Carbon::parse($request->string('report_date')->toString(), $this->reportTimezone());

        if (! $reportDate->isSameDay(now($this->reportTimezone()))) {
            return ApiError::forbidden('Daily summary can only be updated for today.');
        }

        $project = Project::resolveDefault();

        /** @var User $user */
        $user = $request->user();
        $report = DailyReport::query()
            ->where('project_id', $project->id)
            ->whereDate('report_date', $reportDate)
            ->first()
            ?? new DailyReport([
                'project_id' => $project->id,
                'report_date' => $reportDate->format('Y-m-d'),
            ]);

        if (! $report->exists) {
            $report->created_by = $user->id;
        } else {
            $report->updated_by = $user->id;
        }

        $report->summary = $request->input('summary');
        $report->save();

        return new DailyReportResource($report);
    }

    public function performance(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveDateRange($request, defaultRange: 'month');
        $profiles = Profile::query()->with('user')->orderBy('full_name')->get();
        $projectVisits = $this->visitsForReportDate(ProjectVisit::class, $from, $to);
        $companyVisits = $this->visitsForReportDate(CompanyVisit::class, $from, $to);
        $clients = $this->clientsForReportDate($from, $to)->get();

        $rows = $profiles->map(function (Profile $profile) use ($request, $projectVisits, $companyVisits, $clients): array|null {
            if ($request->filled('sales_rep_id') && (int) $request->query('sales_rep_id') !== $profile->id) {
                return null;
            }

            $clientRows = $clients->where('assigned_salesperson_id', $profile->id);
            $reservations = $clientRows->where('status', 'reservation')->count();
            $deals = $clientRows->where('status', 'deal')->count();

            return [
                'salesperson_id' => $profile->id,
                'name' => $profile->full_name ?? $profile->user?->email ?? '—',
                'project_visits' => $projectVisits->where('sales_rep_id', $profile->id)->count(),
                'company_visits' => $companyVisits->where('sales_rep_id', $profile->id)->count(),
                'clients' => $clientRows->count(),
                'reservations' => $reservations,
                'deals' => $deals,
                'client_to_reservation_pct' => $clientRows->count() > 0 ? (int) round(($reservations / $clientRows->count()) * 100) : 0,
                'reservation_to_deal_pct' => $reservations > 0 ? (int) round(($deals / $reservations) * 100) : 0,
            ];
        })->filter()->sortByDesc(fn (array $row) => $row['clients'] + $row['reservations'] + $row['deals'])->values();

        $totals = [
            'project_visits' => $rows->sum('project_visits'),
            'company_visits' => $rows->sum('company_visits'),
            'clients' => $rows->sum('clients'),
            'reservations' => $rows->sum('reservations'),
            'deals' => $rows->sum('deals'),
        ];

        return response()->json([
            'totals' => [
                ...$totals,
                'client_to_reservation_pct' => $totals['clients'] > 0 ? (int) round(($totals['reservations'] / $totals['clients']) * 100) : 0,
                'reservation_to_deal_pct' => $totals['reservations'] > 0 ? (int) round(($totals['deals'] / $totals['reservations']) * 100) : 0,
            ],
            'by_salesperson' => $rows,
        ]);
    }

    private function resolveDateRange(Request $request, string $defaultRange = 'today'): array
    {
        $timezone = $this->reportTimezone();

        if ($request->filled('date')) {
            $date = Carbon::parse((string) $request->query('date'), $timezone)->startOfDay();
            return [$date->format('Y-m-d'), $date->format('Y-m-d')];
        }

        if ($request->filled('from') || $request->filled('to')) {
            $today = now($timezone)->format('Y-m-d');
            $from = Carbon::parse((string) $request->query('from', $today), $timezone)->startOfDay();
            $to = Carbon::parse((string) $request->query('to', $today), $timezone)->startOfDay();
            return [$from->format('Y-m-d'), $to->format('Y-m-d')];
        }

        $today = now($timezone)->startOfDay();
        $range = (string) $request->query('range', $defaultRange);
        [$from, $to] = match ($range) {
            'week', 'this_week' => [$today->copy()->startOfWeek(), $today],
            'month', 'this_month' => [$today->copy()->startOfMonth(), $today],
            default => [$today, $today],
        };

        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }

    private function reportTimezone(): string
    {
        return (string) config('app.report_timezone', config('app.timezone', 'UTC'));
    }

    private function clientsForReportDate(string $from, string $to): Builder
    {
        return Client::query()->where(function (Builder $query) use ($from, $to): void {
            $query->where(function (Builder $withVisitDate) use ($from, $to): void {
                $withVisitDate->whereNotNull('visit_date')
                    ->whereDate('visit_date', '>=', $from)
                    ->whereDate('visit_date', '<=', $to);
            })->orWhere(function (Builder $withoutVisitDate) use ($from, $to): void {
                $withoutVisitDate->whereNull('visit_date')
                    ->whereDate('created_at', '>=', $from)
                    ->whereDate('created_at', '<=', $to);
            });
        });
    }

    /**
     * Match the calendar date in the reporting timezone. ISO timestamps sent
     * by the UI may be stored under the previous UTC date.
     *
     * @param class-string<ProjectVisit|CompanyVisit> $model
     * @param array<int, string> $with
     */
    private function visitsForReportDate(string $model, string $from, string $to, array $with = []): Collection
    {
        $candidateFrom = Carbon::parse($from)->subDay()->format('Y-m-d');
        $candidateTo = Carbon::parse($to)->addDay()->format('Y-m-d');

        return $model::query()->with($with)
            ->whereDate('visit_date', '>=', $candidateFrom)
            ->whereDate('visit_date', '<=', $candidateTo)
            ->orderBy('visit_date')
            ->get()
            ->filter(function (ProjectVisit|CompanyVisit $visit) use ($from, $to): bool {
                $reportDate = $visit->visit_date
                    ->copy()
                    ->setTimezone($this->reportTimezone())
                    ->format('Y-m-d');

                return $reportDate >= $from && $reportDate <= $to;
            })
            ->values();
    }

}
