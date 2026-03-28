<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Party;
use App\Models\Ride;
use App\Models\RideExpense;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * ReportController
 *
 * Handles all 11 reporting endpoints for the Transport Management System.
 *
 * PROFIT FORMULA (applied consistently across all reports):
 *   profit = booking_amount
 *           - personal_expenses  (ride_expenses WHERE expense_type = 'personal')
 *           + party_expenses     (ride_expenses WHERE expense_type = 'party')
 *
 * WHY this formula:
 *   - personal expenses are costs the company absorbs → subtract from profit
 *   - party expenses are billed to the client → they add to effective revenue
 */
class ReportController extends Controller
{
    // =========================================================================
    // HELPER: Shared date-range validation rules
    // =========================================================================

    /**
     * Returns the common validation rules used across multiple reports.
     * Centralised so changes only need to be made in one place.
     */
    private function dateRangeRules(): array
    {
        return [
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ];
    }

    /**
     * Applies optional date-range filter to a query scoped on `rides.start_date`.
     */
    private function applyDateRange($query, Request $request): void
    {
        if ($request->filled('start_date')) {
            $query->where('rides.start_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('rides.start_date', '<=', $request->end_date);
        }
    }

    /**
     * Calculates profit figures for a collection of rides that have already
     * had their ride_expenses eager-loaded.
     *
     * Returns an array: [booking_amount, personal_expenses, party_expenses, profit]
     *
     * WHY a helper: this exact calculation is needed in 7+ reports. A single
     * function prevents drift between implementations.
     */
    private function calcProfit(float $bookingAmount, float $personalExpenses, float $partyExpenses): float
    {
        return $bookingAmount - $personalExpenses + $partyExpenses;
    }

    // =========================================================================
    // 1. PER VEHICLE REPORT
    // =========================================================================

    /**
     * GET /api/reports/vehicle
     *
     * Returns per-vehicle summary: total rides, revenue, expenses, profit.
     *
     * FILTERS: start_date, end_date, vehicle_id
     *
     * HOW profit is built:
     *   For each vehicle, we group its rides and sum:
     *     - booking_amount          → total revenue
     *     - personal ride_expenses  → company cost (subtracted)
     *     - party ride_expenses     → billed to client (added)
     */
    public function vehicleReport(Request $request)
    {
        $validator = Validator::make($request->all(), array_merge($this->dateRangeRules(), [
            'vehicle_id' => 'nullable|exists:vehicles,id',
        ]));

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Build the base query joining rides → ride_expenses
        // We use LEFT JOINs so vehicles with 0 expenses still appear
        $query = Vehicle::query()
            ->select([
                'vehicles.id',
                'vehicles.vehicle_number',
                'vehicles.type',
                'vehicles.status',
                DB::raw('COUNT(DISTINCT rides.id) as total_rides'),
                DB::raw('COALESCE(SUM(rides.booking_amount), 0) as total_booking_amount'),
                DB::raw('COALESCE(SUM(CASE WHEN re.expense_type = "personal" THEN re.expense_amount ELSE 0 END), 0) as total_personal_expenses'),
                DB::raw('COALESCE(SUM(CASE WHEN re.expense_type = "party" THEN re.expense_amount ELSE 0 END), 0) as total_party_expenses'),
            ])
            ->leftJoin('rides', function ($join) use ($request) {
                $join->on('rides.vehicle_id', '=', 'vehicles.id');
                // Date range applied inside the join so ride counts are also scoped
                if ($request->filled('start_date')) {
                    $join->where('rides.start_date', '>=', $request->start_date);
                }
                if ($request->filled('end_date')) {
                    $join->where('rides.start_date', '<=', $request->end_date);
                }
            })
            ->leftJoin('ride_expenses as re', 're.ride_id', '=', 'rides.id')
            ->groupBy('vehicles.id', 'vehicles.vehicle_number', 'vehicles.type', 'vehicles.status');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicles.id', $request->vehicle_id);
        }

        $vehicles = $query->orderBy('total_booking_amount', 'desc')->get();

        // Compute profit as a derived value after fetching
        // WHY post-fetch: DB-level CASE expressions above already give us the
        // three components; adding profit in PHP avoids a nested subquery.
        $vehicles->transform(function ($v) {
            $v->net_profit = $this->calcProfit(
                (float) $v->total_booking_amount,
                (float) $v->total_personal_expenses,
                (float) $v->total_party_expenses
            );
            return $v;
        });

        $totals = [
            'total_rides'             => $vehicles->sum('total_rides'),
            'total_booking_amount'    => round($vehicles->sum('total_booking_amount'), 2),
            'total_personal_expenses' => round($vehicles->sum('total_personal_expenses'), 2),
            'total_party_expenses'    => round($vehicles->sum('total_party_expenses'), 2),
            'net_profit'              => round($vehicles->sum('net_profit'), 2),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Vehicle report retrieved successfully',
            'data'    => [
                'vehicles' => $vehicles,
                'totals'   => $totals,
            ],
        ], 200);
    }

    // =========================================================================
    // 2. VEHICLE MONTHLY REPORT
    // =========================================================================

    /**
     * GET /api/reports/vehicle/monthly
     *
     * Monthly breakdown per vehicle: rides, revenue, expenses, profit.
     *
     * FILTERS: vehicle_id (optional), year (optional, defaults to current year)
     *
     * HOW: We group by vehicle_id + MONTH(start_date) + YEAR(start_date).
     * This gives one row per vehicle per month with aggregated financials.
     */
    public function vehicleMonthlyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'year'       => 'nullable|integer|min:2000|max:2100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $year = $request->input('year', now()->year);

        $query = DB::table('rides')
            ->join('vehicles', 'vehicles.id', '=', 'rides.vehicle_id')
            ->leftJoin('ride_expenses as re', 're.ride_id', '=', 'rides.id')
            ->select([
                'vehicles.id as vehicle_id',
                'vehicles.vehicle_number',
                'vehicles.type as vehicle_type',
                DB::raw('MONTH(rides.start_date) as month'),
                DB::raw('YEAR(rides.start_date) as year'),
                DB::raw('COUNT(DISTINCT rides.id) as total_rides'),
                DB::raw('COALESCE(SUM(rides.booking_amount), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(CASE WHEN re.expense_type = "personal" THEN re.expense_amount ELSE 0 END), 0) as personal_expenses'),
                DB::raw('COALESCE(SUM(CASE WHEN re.expense_type = "party" THEN re.expense_amount ELSE 0 END), 0) as party_expenses'),
            ])
            ->whereYear('rides.start_date', $year)
            ->groupBy('vehicles.id', 'vehicles.vehicle_number', 'vehicles.type', DB::raw('MONTH(rides.start_date)'), DB::raw('YEAR(rides.start_date)'));

        if ($request->filled('vehicle_id')) {
            $query->where('vehicles.id', $request->vehicle_id);
        }

        if ($request->filled('start_date')) {
            $query->where('rides.start_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('rides.start_date', '<=', $request->end_date);
        }

        $rows = $query->orderBy('vehicles.id')->orderBy('year')->orderBy('month')->get();

        // Attach profit and month label to each row
        $rows->transform(function ($row) {
            $row->profit     = round($this->calcProfit($row->total_revenue, $row->personal_expenses, $row->party_expenses), 2);
            $row->month_name = date('F', mktime(0, 0, 0, $row->month, 1));
            return $row;
        });

        // Group by vehicle for a structured response the frontend can render as tabs/accordion
        $grouped = $rows->groupBy('vehicle_id')->map(function ($monthRows, $vehicleId) {
            $first = $monthRows->first();
            return [
                'vehicle_id'     => $vehicleId,
                'vehicle_number' => $first->vehicle_number,
                'vehicle_type'   => $first->vehicle_type,
                'months'         => $monthRows->values(),
                'yearly_totals'  => [
                    'total_rides'        => $monthRows->sum('total_rides'),
                    'total_revenue'      => round($monthRows->sum('total_revenue'), 2),
                    'personal_expenses'  => round($monthRows->sum('personal_expenses'), 2),
                    'party_expenses'     => round($monthRows->sum('party_expenses'), 2),
                    'profit'             => round($monthRows->sum('profit'), 2),
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle monthly report retrieved successfully',
            'data'    => [
                'year'     => $year,
                'vehicles' => $grouped,
            ],
        ], 200);
    }

    // =========================================================================
    // 3. RIDE REPORT
    // =========================================================================

    /**
     * GET /api/reports/rides
     *
     * Detailed record of each ride with route, vehicle type, booking amount,
     * per-ride expenses, and per-ride profit.
     *
     * FILTERS: start_date, end_date, vehicle_id, party_id, ride_type, is_completed
     * PAGINATION: per_page (default 15)
     *
     * HOW: Eager-load rideExpenses so we can split personal/party in PHP
     * without N+1. The profit formula is applied per ride.
     */
    public function rideReport(Request $request)
    {
        $validator = Validator::make($request->all(), array_merge($this->dateRangeRules(), [
            'per_page'     => 'nullable|integer|min:1|max:100',
            'vehicle_id'   => 'nullable|exists:vehicles,id',
            'party_id'     => 'nullable|exists:parties,id',
            'ride_type'    => 'nullable|in:personal,partner',
            'is_completed' => 'nullable|boolean',
        ]));

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $perPage = $request->input('per_page', 15);

        $query = Ride::with([
            'vehicle:id,vehicle_number,type',
            'party:id,name',
            'partner:id,name',
            'rideExpenses:id,ride_id,expense_amount,expense_type,expense_note',
        ]);

        if ($request->filled('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('start_date', '<=', $request->end_date);
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }
        if ($request->filled('party_id')) {
            $query->where('party_id', $request->party_id);
        }
        if ($request->filled('ride_type')) {
            $query->where('ride_type', $request->ride_type);
        }
        if ($request->has('is_completed')) {
            $query->where('is_completed', $request->boolean('is_completed'));
        }

        $paginated = $query->orderBy('start_date', 'desc')->paginate($perPage);

        // Attach per-ride financial summary
        $rides = collect($paginated->items())->map(function ($ride) {
            $personalExp = $ride->rideExpenses->where('expense_type', 'personal')->sum('expense_amount');
            $partyExp    = $ride->rideExpenses->where('expense_type', 'party')->sum('expense_amount');

            $ride->financial_summary = [
                'booking_amount'      => (float) $ride->booking_amount,
                'personal_expenses'   => round($personalExp, 2),
                'party_expenses'      => round($partyExp, 2),
                'total_expenses'      => round($personalExp + $partyExp, 2),
                'profit'              => round($this->calcProfit($ride->booking_amount, $personalExp, $partyExp), 2),
            ];

            return $ride;
        });

        return response()->json([
            'success' => true,
            'message' => 'Ride report retrieved successfully',
            'data'    => [
                'rides'      => $rides,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'from'         => $paginated->firstItem(),
                    'to'           => $paginated->lastItem(),
                ],
            ],
        ], 200);
    }

    // =========================================================================
    // 4. MARKET VS OWN VEHICLE REPORT
    // =========================================================================

    /**
     * GET /api/reports/vehicle-comparison
     *
     * Compares own vehicles (ride_type = 'personal') vs market/rented vehicles
     * (ride_type = 'partner').
     *
     * FILTERS: start_date, end_date
     *
     * WHY we split by ride_type:
     *   - 'personal' = company-owned vehicle → full profit stays with company
     *   - 'partner'  = rented/market vehicle  → partner costs are factored in
     */
    public function marketVsOwnReport(Request $request)
    {
        $validator = Validator::make($request->all(), $this->dateRangeRules());

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $buildSummary = function (string $rideType) use ($request): array {
            $query = Ride::query()
                ->where('ride_type', $rideType)
                ->with('rideExpenses:id,ride_id,expense_amount,expense_type');

            if ($request->filled('start_date')) {
                $query->where('start_date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->where('start_date', '<=', $request->end_date);
            }

            $rides = $query->get();

            $bookingAmount   = $rides->sum('booking_amount');
            $personalExpenses = 0;
            $partyExpenses    = 0;

            foreach ($rides as $ride) {
                $personalExpenses += $ride->rideExpenses->where('expense_type', 'personal')->sum('expense_amount');
                $partyExpenses    += $ride->rideExpenses->where('expense_type', 'party')->sum('expense_amount');
            }

            return [
                'total_rides'        => $rides->count(),
                'total_revenue'      => round($bookingAmount, 2),
                'personal_expenses'  => round($personalExpenses, 2),
                'party_expenses'     => round($partyExpenses, 2),
                'total_expenses'     => round($personalExpenses + $partyExpenses, 2),
                'profit'             => round($this->calcProfit($bookingAmount, $personalExpenses, $partyExpenses), 2),
            ];
        };

        $ownVehicles    = $buildSummary('personal');
        $marketVehicles = $buildSummary('partner');

        // Profit difference: positive means own vehicles are more profitable
        $profitDifference = round($ownVehicles['profit'] - $marketVehicles['profit'], 2);

        return response()->json([
            'success' => true,
            'message' => 'Market vs Own vehicle report retrieved successfully',
            'data'    => [
                'own_vehicles'      => $ownVehicles,
                'market_vehicles'   => $marketVehicles,
                'comparison'        => [
                    'profit_difference'          => $profitDifference,
                    'own_more_profitable'         => $profitDifference > 0,
                    'combined_total_rides'        => $ownVehicles['total_rides'] + $marketVehicles['total_rides'],
                    'combined_total_revenue'      => round($ownVehicles['total_revenue'] + $marketVehicles['total_revenue'], 2),
                    'combined_profit'             => round($ownVehicles['profit'] + $marketVehicles['profit'], 2),
                ],
            ],
        ], 200);
    }

    // =========================================================================
    // 5. PARTY / CLIENT REPORT
    // =========================================================================

    /**
     * GET /api/reports/party
     *
     * Per-client summary: total rides, billing amount, paid/unpaid invoices,
     * profit generated per client.
     *
     * FILTERS: start_date, end_date, party_id
     *
     * HOW profit per party:
     *   Sum profit across all rides belonging to that party using same formula.
     */
    public function partyReport(Request $request)
    {
        $validator = Validator::make($request->all(), array_merge($this->dateRangeRules(), [
            'party_id' => 'nullable|exists:parties,id',
        ]));

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Aggregate invoice data per party via a subquery for efficiency
        $invoiceData = DB::table('invoices')
            ->select(
                'party_id',
                DB::raw('COALESCE(SUM(amount), 0) as total_billed'),
                DB::raw('COALESCE(SUM(CASE WHEN payment_status = "paid" THEN amount ELSE 0 END), 0) as total_paid'),
                DB::raw('COALESCE(SUM(CASE WHEN payment_status = "unpaid" THEN amount ELSE 0 END), 0) as total_unpaid'),
                DB::raw('COUNT(*) as total_invoices'),
                DB::raw('SUM(CASE WHEN payment_status = "paid" THEN 1 ELSE 0 END) as paid_invoices'),
                DB::raw('SUM(CASE WHEN payment_status = "unpaid" THEN 1 ELSE 0 END) as unpaid_invoices')
            )
            ->groupBy('party_id')
            ->get()
            ->keyBy('party_id');

        $partyQuery = Party::query();

        if ($request->filled('party_id')) {
            $partyQuery->where('id', $request->party_id);
        }

        $parties = $partyQuery->orderBy('name')->get();

        $result = $parties->map(function ($party) use ($request, $invoiceData) {
            // Fetch all rides for this party within the date range
            $rideQuery = Ride::where('party_id', $party->id)
                ->with('rideExpenses:id,ride_id,expense_amount,expense_type');

            if ($request->filled('start_date')) {
                $rideQuery->where('start_date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $rideQuery->where('start_date', '<=', $request->end_date);
            }

            $rides = $rideQuery->get();

            $bookingAmount   = (float) $rides->sum('booking_amount');
            $personalExpenses = 0;
            $partyExpenses    = 0;

            foreach ($rides as $ride) {
                $personalExpenses += $ride->rideExpenses->where('expense_type', 'personal')->sum('expense_amount');
                $partyExpenses    += $ride->rideExpenses->where('expense_type', 'party')->sum('expense_amount');
            }

            $inv = $invoiceData->get($party->id);

            return [
                'party_id'          => $party->id,
                'party_name'        => $party->name,
                'party_phone'       => $party->phone,
                'party_email'       => $party->email,
                'total_rides'       => $rides->count(),
                'total_billed'      => round($bookingAmount, 2),
                'invoice_summary'   => [
                    'total_invoices'   => $inv ? (int) $inv->total_invoices : 0,
                    'paid_invoices'    => $inv ? (int) $inv->paid_invoices : 0,
                    'unpaid_invoices'  => $inv ? (int) $inv->unpaid_invoices : 0,
                    'total_paid'       => $inv ? round($inv->total_paid, 2) : 0,
                    'total_unpaid'     => $inv ? round($inv->total_unpaid, 2) : 0,
                ],
                'expense_summary'   => [
                    'personal_expenses' => round($personalExpenses, 2),
                    'party_expenses'    => round($partyExpenses, 2),
                ],
                'profit'            => round($this->calcProfit($bookingAmount, $personalExpenses, $partyExpenses), 2),
            ];
        });

        $totals = [
            'total_parties'      => $result->count(),
            'total_rides'        => $result->sum('total_rides'),
            'total_billed'       => round($result->sum('total_billed'), 2),
            'total_paid'         => round($result->sum('invoice_summary.total_paid'), 2),
            'total_unpaid'       => round($result->sum('invoice_summary.total_unpaid'), 2),
            'total_profit'       => round($result->sum('profit'), 2),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Party report retrieved successfully',
            'data'    => [
                'parties' => $result->values(),
                'totals'  => $totals,
            ],
        ], 200);
    }

    // =========================================================================
    // 6. INVOICE REPORT
    // =========================================================================

    /**
     * GET /api/reports/invoices
     *
     * Summary of all invoices: total count, paid/unpaid split, outstanding
     * balances, and client-wise pending amounts.
     *
     * FILTERS: start_date, end_date, payment_status, party_id
     */
    public function invoiceReport(Request $request)
    {
        $validator = Validator::make($request->all(), array_merge($this->dateRangeRules(), [
            'payment_status' => 'nullable|in:paid,unpaid',
            'party_id'       => 'nullable|exists:parties,id',
            'per_page'       => 'nullable|integer|min:1|max:100',
        ]));

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $perPage = $request->input('per_page', 15);

        // ── Overall summary (unfiltered by payment_status for accurate totals) ──
        $summaryQuery = Invoice::query();

        if ($request->filled('start_date')) {
            $summaryQuery->where('invoice_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $summaryQuery->where('invoice_date', '<=', $request->end_date);
        }
        if ($request->filled('party_id')) {
            $summaryQuery->where('party_id', $request->party_id);
        }

        $allInvoices = $summaryQuery->get();

        $summary = [
            'total_invoices'       => $allInvoices->count(),
            'paid_invoices'        => $allInvoices->where('payment_status', 'paid')->count(),
            'unpaid_invoices'      => $allInvoices->where('payment_status', 'unpaid')->count(),
            'total_amount'         => round($allInvoices->sum('amount'), 2),
            'total_paid_amount'    => round($allInvoices->where('payment_status', 'paid')->sum('amount'), 2),
            'total_unpaid_amount'  => round($allInvoices->where('payment_status', 'unpaid')->sum('amount'), 2),
            'outstanding_balance'  => round($allInvoices->where('payment_status', 'unpaid')->sum('amount'), 2),
        ];

        // ── Client-wise pending amounts ──
        $clientPending = Invoice::query()
            ->where('payment_status', 'unpaid')
            ->with('party:id,name,phone')
            ->select('party_id', DB::raw('SUM(amount) as pending_amount'), DB::raw('COUNT(*) as pending_invoices'))
            ->groupBy('party_id')
            ->orderBy('pending_amount', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'party_id'         => $item->party_id,
                    'party_name'       => $item->party?->name,
                    'party_phone'      => $item->party?->phone,
                    'pending_amount'   => round($item->pending_amount, 2),
                    'pending_invoices' => $item->pending_invoices,
                ];
            });

        // ── Paginated invoice list ──
        $listQuery = Invoice::with(['party:id,name', 'ride:id,ride_number,route'])
            ->orderBy('invoice_date', 'desc');

        if ($request->filled('start_date')) {
            $listQuery->where('invoice_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $listQuery->where('invoice_date', '<=', $request->end_date);
        }
        if ($request->filled('payment_status')) {
            $listQuery->where('payment_status', $request->payment_status);
        }
        if ($request->filled('party_id')) {
            $listQuery->where('party_id', $request->party_id);
        }

        $paginated = $listQuery->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Invoice report retrieved successfully',
            'data'    => [
                'summary'         => $summary,
                'client_pending'  => $clientPending,
                'invoices'        => $paginated->items(),
                'pagination'      => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'from'         => $paginated->firstItem(),
                    'to'           => $paginated->lastItem(),
                ],
            ],
        ], 200);
    }

    // =========================================================================
    // 7. EXPENSE REPORT
    // =========================================================================

    /**
     * GET /api/reports/expenses
     *
     * Covers ride-level expenses (personal & party), broken down:
     *   - By vehicle
     *   - By expense type (personal / party)
     *   - Optionally filtered by date range
     *
     * FILTERS: start_date, end_date, vehicle_id, expense_type
     *
     * WHY we join through rides to get vehicle:
     *   RideExpense belongs to Ride which belongs to Vehicle.
     *   Direct vehicle filter requires this join chain.
     */
    public function expenseReport(Request $request)
    {
        $validator = Validator::make($request->all(), array_merge($this->dateRangeRules(), [
            'vehicle_id'   => 'nullable|exists:vehicles,id',
            'expense_type' => 'nullable|in:personal,party',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]));

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $perPage = $request->input('per_page', 15);

        // ── Per-vehicle expense breakdown ──
        $vehicleBreakdownQuery = DB::table('ride_expenses as re')
            ->join('rides', 'rides.id', '=', 're.ride_id')
            ->join('vehicles', 'vehicles.id', '=', 'rides.vehicle_id')
            ->select([
                'vehicles.id as vehicle_id',
                'vehicles.vehicle_number',
                'vehicles.type as vehicle_type',
                DB::raw('COALESCE(SUM(CASE WHEN re.expense_type = "personal" THEN re.expense_amount ELSE 0 END), 0) as personal_expenses'),
                DB::raw('COALESCE(SUM(CASE WHEN re.expense_type = "party" THEN re.expense_amount ELSE 0 END), 0) as party_expenses'),
                DB::raw('COALESCE(SUM(re.expense_amount), 0) as total_expenses'),
                DB::raw('COUNT(DISTINCT rides.id) as rides_count'),
            ])
            ->groupBy('vehicles.id', 'vehicles.vehicle_number', 'vehicles.type');

        if ($request->filled('start_date')) {
            $vehicleBreakdownQuery->where('rides.start_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $vehicleBreakdownQuery->where('rides.start_date', '<=', $request->end_date);
        }
        if ($request->filled('vehicle_id')) {
            $vehicleBreakdownQuery->where('vehicles.id', $request->vehicle_id);
        }

        $vehicleBreakdown = $vehicleBreakdownQuery->orderBy('total_expenses', 'desc')->get();

        // ── Type breakdown (overall) ──
        $typeBreakdownQuery = DB::table('ride_expenses as re')
            ->join('rides', 'rides.id', '=', 're.ride_id')
            ->select([
                're.expense_type',
                DB::raw('SUM(re.expense_amount) as total_amount'),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('re.expense_type');

        if ($request->filled('start_date')) {
            $typeBreakdownQuery->where('rides.start_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $typeBreakdownQuery->where('rides.start_date', '<=', $request->end_date);
        }

        $typeBreakdown = $typeBreakdownQuery->get();

        // ── Paginated expense list ──
        $listQuery = RideExpense::query()
            ->with([
                'ride:id,ride_number,route,start_date,vehicle_id',
                'ride.vehicle:id,vehicle_number',
            ])
            ->select('ride_expenses.*');

        if ($request->filled('start_date') || $request->filled('end_date') || $request->filled('vehicle_id')) {
            $listQuery->whereHas('ride', function ($q) use ($request) {
                if ($request->filled('start_date')) {
                    $q->where('start_date', '>=', $request->start_date);
                }
                if ($request->filled('end_date')) {
                    $q->where('start_date', '<=', $request->end_date);
                }
                if ($request->filled('vehicle_id')) {
                    $q->where('vehicle_id', $request->vehicle_id);
                }
            });
        }

        if ($request->filled('expense_type')) {
            $listQuery->where('expense_type', $request->expense_type);
        }

        $paginated = $listQuery->orderBy('created_at', 'desc')->paginate($perPage);

        $overallSummary = [
            'total_personal_expenses' => round($vehicleBreakdown->sum('personal_expenses'), 2),
            'total_party_expenses'    => round($vehicleBreakdown->sum('party_expenses'), 2),
            'total_expenses'          => round($vehicleBreakdown->sum('total_expenses'), 2),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Expense report retrieved successfully',
            'data'    => [
                'summary'           => $overallSummary,
                'vehicle_breakdown' => $vehicleBreakdown,
                'type_breakdown'    => $typeBreakdown,
                'expenses'          => $paginated->items(),
                'pagination'        => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'from'         => $paginated->firstItem(),
                    'to'           => $paginated->lastItem(),
                ],
            ],
        ], 200);
    }

    // =========================================================================
    // 8. PROFIT & LOSS REPORT
    // =========================================================================

    /**
     * GET /api/reports/profit-loss
     *
     * Overall P&L: total revenue, total expenses, net profit.
     * Also broken down by month or by day depending on the requested granularity.
     *
     * FILTERS: start_date, end_date, granularity (monthly | daily, default monthly)
     */
    public function profitLossReport(Request $request)
    {
        $validator = Validator::make($request->all(), array_merge($this->dateRangeRules(), [
            'granularity' => 'nullable|in:daily,monthly',
        ]));

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $granularity = $request->input('granularity', 'monthly');

        // ── Overall totals ──
        $allRides = Ride::query()
            ->with('rideExpenses:id,ride_id,expense_amount,expense_type');

        if ($request->filled('start_date')) {
            $allRides->where('start_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $allRides->where('start_date', '<=', $request->end_date);
        }

        $rides           = $allRides->get();
        $totalRevenue    = (float) $rides->sum('booking_amount');
        $totalPersonal   = 0;
        $totalParty      = 0;

        foreach ($rides as $ride) {
            $totalPersonal += $ride->rideExpenses->where('expense_type', 'personal')->sum('expense_amount');
            $totalParty    += $ride->rideExpenses->where('expense_type', 'party')->sum('expense_amount');
        }

        $overall = [
            'total_revenue'           => round($totalRevenue, 2),
            'total_personal_expenses' => round($totalPersonal, 2),
            'total_party_expenses'    => round($totalParty, 2),
            'total_expenses'          => round($totalPersonal + $totalParty, 2),
            'net_profit'              => round($this->calcProfit($totalRevenue, $totalPersonal, $totalParty), 2),
        ];

        // ── Granular breakdown ──
        if ($granularity === 'daily') {
            $groupFormat  = 'DATE(rides.start_date)';
            $selectFormat = DB::raw('DATE(rides.start_date) as period');
        } else {
            // monthly: format as "YYYY-MM" for easy sorting & display
            $groupFormat  = 'DATE_FORMAT(rides.start_date, "%Y-%m")';
            $selectFormat = DB::raw('DATE_FORMAT(rides.start_date, "%Y-%m") as period');
        }

        $breakdown = DB::table('rides')
            ->leftJoin('ride_expenses as re', 're.ride_id', '=', 'rides.id')
            ->select([
                $selectFormat,
                DB::raw('COUNT(DISTINCT rides.id) as total_rides'),
                DB::raw('COALESCE(SUM(rides.booking_amount), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(CASE WHEN re.expense_type = "personal" THEN re.expense_amount ELSE 0 END), 0) as personal_expenses'),
                DB::raw('COALESCE(SUM(CASE WHEN re.expense_type = "party" THEN re.expense_amount ELSE 0 END), 0) as party_expenses'),
            ])
            ->when($request->filled('start_date'), fn($q) => $q->where('rides.start_date', '>=', $request->start_date))
            ->when($request->filled('end_date'), fn($q) => $q->where('rides.start_date', '<=', $request->end_date))
            ->groupBy(DB::raw($groupFormat))
            ->orderBy('period')
            ->get()
            ->map(function ($row) {
                $row->net_profit = round(
                    $this->calcProfit((float) $row->total_revenue, (float) $row->personal_expenses, (float) $row->party_expenses),
                    2
                );
                return $row;
            });

        return response()->json([
            'success' => true,
            'message' => 'Profit & Loss report retrieved successfully',
            'data'    => [
                'overall'     => $overall,
                'granularity' => $granularity,
                'breakdown'   => $breakdown,
            ],
        ], 200);
    }

    // =========================================================================
    // 9. DRIVER / PARTNER REPORT
    // =========================================================================

    /**
     * GET /api/reports/partner
     *
     * Per-partner summary: rides assigned, payments made (via partner ledger),
     * profit generated from partner rides.
     *
     * FILTERS: start_date, end_date, partner_id
     *
     * HOW payments: we look at the partner_ledger table for payments made TO
     * the partner (debit entries represent money paid out).
     * If no ledger is available, payments will show as 0.
     */
    public function partnerReport(Request $request)
    {
        $validator = Validator::make($request->all(), array_merge($this->dateRangeRules(), [
            'partner_id' => 'nullable|exists:partners,id',
        ]));

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $partnerQuery = Partner::query();

        if ($request->filled('partner_id')) {
            $partnerQuery->where('id', $request->partner_id);
        }

        $partners = $partnerQuery->orderBy('name')->get();

        // Pre-fetch ledger payments grouped by partner to avoid N+1
        // WHY: partner_ledgers tracks money paid out to partners
        $ledgerPayments = DB::table('partner_ledger_entries')
            ->select('partner_id', DB::raw('COALESCE(SUM(debit), 0) as total_paid'))
            ->groupBy('partner_id')
            ->get()
            ->keyBy('partner_id');

        $result = $partners->map(function ($partner) use ($request, $ledgerPayments) {
            $rideQuery = Ride::where('partner_id', $partner->id)
                ->with('rideExpenses:id,ride_id,expense_amount,expense_type');

            if ($request->filled('start_date')) {
                $rideQuery->where('start_date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $rideQuery->where('start_date', '<=', $request->end_date);
            }

            $rides = $rideQuery->get();

            $bookingAmount    = (float) $rides->sum('booking_amount');
            $personalExpenses = 0;
            $partyExpenses    = 0;

            foreach ($rides as $ride) {
                $personalExpenses += $ride->rideExpenses->where('expense_type', 'personal')->sum('expense_amount');
                $partyExpenses    += $ride->rideExpenses->where('expense_type', 'party')->sum('expense_amount');
            }

            $ledger = $ledgerPayments->get($partner->id);

            return [
                'partner_id'      => $partner->id,
                'partner_name'    => $partner->name,
                'partner_phone'   => $partner->phone,
                'is_active'       => $partner->is_active,
                'total_rides'     => $rides->count(),
                'total_revenue'   => round($bookingAmount, 2),
                'payments_made'   => $ledger ? round($ledger->total_paid, 2) : 0,
                'expense_summary' => [
                    'personal_expenses' => round($personalExpenses, 2),
                    'party_expenses'    => round($partyExpenses, 2),
                ],
                'profit'          => round($this->calcProfit($bookingAmount, $personalExpenses, $partyExpenses), 2),
            ];
        });

        $totals = [
            'total_partners'   => $result->count(),
            'total_rides'      => $result->sum('total_rides'),
            'total_revenue'    => round($result->sum('total_revenue'), 2),
            'total_payments'   => round($result->sum('payments_made'), 2),
            'total_profit'     => round($result->sum('profit'), 2),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Partner report retrieved successfully',
            'data'    => [
                'partners' => $result->values(),
                'totals'   => $totals,
            ],
        ], 200);
    }

    // =========================================================================
    // 10. DAILY SUMMARY REPORT
    // =========================================================================

    /**
     * GET /api/reports/daily-summary
     *
     * Per-day summary: rides, revenue, expenses, profit for a given date range.
     *
     * FILTERS: start_date (required), end_date (required)
     *
     * WHY both dates required:
     *   A daily summary spanning years would produce too many rows. We enforce
     *   a bounded range to keep responses fast and meaningful.
     */
    public function dailySummaryReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $rows = DB::table('rides')
            ->leftJoin('ride_expenses as re', 're.ride_id', '=', 'rides.id')
            ->select([
                DB::raw('DATE(rides.start_date) as date'),
                DB::raw('COUNT(DISTINCT rides.id) as total_rides'),
                DB::raw('COALESCE(SUM(rides.booking_amount), 0) as daily_revenue'),
                DB::raw('COALESCE(SUM(CASE WHEN re.expense_type = "personal" THEN re.expense_amount ELSE 0 END), 0) as personal_expenses'),
                DB::raw('COALESCE(SUM(CASE WHEN re.expense_type = "party" THEN re.expense_amount ELSE 0 END), 0) as party_expenses'),
            ])
            ->whereBetween('rides.start_date', [$request->start_date, $request->end_date])
            ->groupBy(DB::raw('DATE(rides.start_date)'))
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                $row->daily_expenses = round((float) $row->personal_expenses + (float) $row->party_expenses, 2);
                $row->daily_profit   = round($this->calcProfit(
                    (float) $row->daily_revenue,
                    (float) $row->personal_expenses,
                    (float) $row->party_expenses
                ), 2);
                return $row;
            });

        $totals = [
            'total_days'              => $rows->count(),
            'total_rides'             => $rows->sum('total_rides'),
            'total_revenue'           => round($rows->sum('daily_revenue'), 2),
            'total_personal_expenses' => round($rows->sum('personal_expenses'), 2),
            'total_party_expenses'    => round($rows->sum('party_expenses'), 2),
            'total_expenses'          => round($rows->sum('daily_expenses'), 2),
            'total_profit'            => round($rows->sum('daily_profit'), 2),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Daily summary report retrieved successfully',
            'data'    => [
                'summary' => $totals,
                'days'    => $rows,
            ],
        ], 200);
    }

    // =========================================================================
    // 11. PENDING & DUE REPORT
    // =========================================================================

    /**
     * GET /api/reports/pending-due
     *
     * Lists:
     *   - Unpaid invoices with client details and outstanding amounts
     *   - Pending (incomplete) rides
     *   - Overall outstanding balance
     *
     * FILTERS: party_id (optional)
     */
    public function pendingDueReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'party_id' => 'nullable|exists:parties,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // ── Unpaid invoices ──
        $unpaidQuery = Invoice::with(['party:id,name,phone', 'ride:id,ride_number,route'])
            ->where('payment_status', 'unpaid')
            ->orderBy('invoice_date', 'asc'); // oldest first = most overdue at top

        if ($request->filled('party_id')) {
            $unpaidQuery->where('party_id', $request->party_id);
        }

        $unpaidInvoices = $unpaidQuery->get();

        // ── Pending rides ──
        $pendingRidesQuery = Ride::with(['vehicle:id,vehicle_number', 'party:id,name'])
            ->where('is_completed', false)
            ->orderBy('start_date', 'asc');

        if ($request->filled('party_id')) {
            $pendingRidesQuery->where('party_id', $request->party_id);
        }

        $pendingRides = $pendingRidesQuery->get()->map(function ($ride) {
            return [
                'ride_id'        => $ride->id,
                'ride_number'    => $ride->ride_number,
                'start_date'     => $ride->start_date,
                'route'          => $ride->route,
                'party'          => $ride->party,
                'vehicle'        => $ride->vehicle,
                'booking_amount' => (float) $ride->booking_amount,
            ];
        });

        // ── Client-wise outstanding (from unpaid invoices) ──
        $clientOutstanding = $unpaidInvoices
            ->groupBy('party_id')
            ->map(function ($invoices, $partyId) {
                $first = $invoices->first();
                return [
                    'party_id'          => $partyId,
                    'party_name'        => $first->party?->name,
                    'party_phone'       => $first->party?->phone,
                    'pending_invoices'  => $invoices->count(),
                    'outstanding_amount'=> round($invoices->sum('amount'), 2),
                    'oldest_invoice'    => $invoices->min('invoice_date'),
                ];
            })
            ->sortByDesc('outstanding_amount')
            ->values();

        $summary = [
            'total_unpaid_invoices'   => $unpaidInvoices->count(),
            'total_outstanding_amount'=> round($unpaidInvoices->sum('amount'), 2),
            'total_pending_rides'     => $pendingRides->count(),
            'clients_with_dues'       => $clientOutstanding->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Pending & due report retrieved successfully',
            'data'    => [
                'summary'            => $summary,
                'unpaid_invoices'    => $unpaidInvoices,
                'pending_rides'      => $pendingRides,
                'client_outstanding' => $clientOutstanding,
            ],
        ], 200);
    }
}