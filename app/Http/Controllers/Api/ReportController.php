<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\UpsertDailySummaryRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\DailyReportResource;
use App\Models\Client;
use App\Models\CompanyVisit;
use App\Models\DailyReport;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ProjectVisit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $today = now()->startOfDay();
        $last7 = now()->startOfDay()->subDays(6);
        $last30 = now()->startOfDay()->subDays(29);

        $stats = [
            'project_visits' => ProjectVisit::query()->where('visit_date', '>=', $today)->count(),
            'company_visits' => CompanyVisit::query()->where('visit_date', '>=', $today)->count(),
            'new_clients' => Client::query()->where('created_at', '>=', $today)->count(),
            'presentations' => Client::query()->where('created_at', '>=', $today)->where('presentation_completed', true)->count(),
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

        $weeklyActivity = collect(range(0, 6))->map(function (int $offset) use ($last7): array {
            $date = $last7->copy()->addDays($offset);
            return [
                'date' => $date->format('Y-m-d'),
                'project_visits' => ProjectVisit::query()->whereDate('visit_date', $date)->count(),
                'company_visits' => CompanyVisit::query()->whereDate('visit_date', $date)->count(),
                'new_clients' => Client::query()->whereDate('created_at', $date)->count(),
                'presentations' => Client::query()->whereDate('created_at', $date)->where('presentation_completed', true)->count(),
            ];
        });

        $profiles = Profile::query()->with('user')->orderBy('full_name')->get();
        $clients = Client::query()->where('created_at', '>=', $last30)->get();
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
        $projectVisits = ProjectVisit::query()->with(['agency', 'salesRep.user'])
            ->whereBetween('visit_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();
        $companyVisits = CompanyVisit::query()->with(['agency', 'salesRep.user'])
            ->whereBetween('visit_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();
        $clients = Client::query()->with('agency')
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('created_at')
            ->get();

        $summaryRecord = null;
        if ($project && $from->equalTo($to)) {
            $summaryRecord = DailyReport::query()
                ->where('project_id', $project->id)
                ->whereDate('report_date', $from)
                ->first();
        }

        return response()->json([
            'range' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
            'project_visits' => [
                'total' => $projectVisits->count(),
                'agencies' => $projectVisits->pluck('agency.name')->filter()->unique()->values(),
                'sales_reps' => $projectVisits->map(fn (ProjectVisit $visit) => $visit->salesRep?->full_name ?? $visit->salesRep?->user?->email)->filter()->unique()->values(),
            ],
            'company_visits' => [
                'total' => $companyVisits->count(),
                'categories' => $companyVisits->pluck('category')->filter()->unique()->values(),
                'sales_reps' => $companyVisits->map(fn (CompanyVisit $visit) => $visit->salesRep?->full_name ?? $visit->salesRep?->user?->email)->filter()->unique()->values(),
            ],
            'presentations' => [
                'total' => $clients->where('presentation_completed', true)->count(),
                'client_names' => $clients->where('presentation_completed', true)->pluck('client_name')->values(),
            ],
            'clients' => ClientResource::collection($clients)->resolve(),
            'summary' => [
                'text' => $summaryRecord?->summary,
                'editable' => $from->equalTo($to) && $from->isToday(),
            ],
        ]);
    }

    public function upsertDailySummary(UpsertDailySummaryRequest $request): DailyReportResource|JsonResponse
    {
        $reportDate = Carbon::parse($request->string('report_date')->toString());

        if (! $reportDate->isToday()) {
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
        $projectVisits = ProjectVisit::query()->whereBetween('visit_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();
        $companyVisits = CompanyVisit::query()->whereBetween('visit_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();
        $clients = Client::query()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();

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
        if ($request->filled('date')) {
            $date = Carbon::parse((string) $request->query('date'));
            return [$date->copy()->startOfDay(), $date->copy()->startOfDay()];
        }

        if ($request->filled('from') || $request->filled('to')) {
            $from = Carbon::parse((string) $request->query('from', now()->format('Y-m-d')));
            $to = Carbon::parse((string) $request->query('to', now()->format('Y-m-d')));
            return [$from->copy()->startOfDay(), $to->copy()->startOfDay()];
        }

        return match ($defaultRange) {
            'month' => [now()->startOfMonth(), now()->startOfDay()],
            default => [now()->startOfDay(), now()->startOfDay()],
        };
    }
}
