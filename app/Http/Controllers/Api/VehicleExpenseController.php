<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VehicleExpenseController extends Controller
{
    // =========================================================================
    // INDEX
    // =========================================================================

    /**
     * GET ALL VEHICLE EXPENSES
     * Supports filtering by vehicle_id, date range, search term, and pagination.
     * Always eager-loads the vehicle relationship.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page'   => 'nullable|integer|min:1|max:100',
            'search'     => 'nullable|string|max:100',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 400);
        }

        $perPage = $request->input('per_page', 10);
        $query   = VehicleExpense::with('vehicle');

        // Filter by search — matches description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('description', 'LIKE', "%{$search}%");
        }

        // Filter by specific vehicle
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('expense_date', [$request->start_date, $request->end_date]);
        }

        $query->orderBy('expense_date', 'desc');

        $expenses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $expenses,
        ], 200);
    }

    // =========================================================================
    // STORE
    // =========================================================================

    /**
     * CREATE VEHICLE EXPENSE
     * Validates that the vehicle exists and is active before storing.
     * WHY active check: adding expense to an inactive vehicle is likely
     * a data entry mistake — same guard used in RideController.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id'   => 'required|exists:vehicles,id',
            'amount'       => 'required|numeric|min:0',
            'description'  => 'required|string|max:255',
            'expense_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 400);
        }

        // Guard: prevent expenses being logged against inactive vehicles
        $vehicle = Vehicle::find($request->vehicle_id);
        if ($vehicle->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add expense to an inactive vehicle',
            ], 400);
        }

        $expense = VehicleExpense::create([
            'vehicle_id'   => $request->vehicle_id,
            'amount'       => $request->amount,
            'description'  => $request->description,
            'expense_date' => $request->expense_date,
        ]);

        $expense->load('vehicle');

        return response()->json([
            'success' => true,
            'message' => 'Vehicle expense created successfully',
            'data'    => $expense,
        ], 201);
    }

    // =========================================================================
    // SHOW
    // =========================================================================

    /**
     * GET SINGLE VEHICLE EXPENSE
     * Returns the expense with its vehicle relationship.
     */
    public function show($id)
    {
        $expense = VehicleExpense::with('vehicle')->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle expense not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $expense,
        ], 200);
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    /**
     * UPDATE VEHICLE EXPENSE
     * All fields are nullable — only provided fields are updated (PATCH-style).
     * If vehicle_id is changed, the new vehicle is also checked for active status.
     */
    public function update(Request $request, $id)
    {
        $expense = VehicleExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle expense not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_id'   => 'nullable|exists:vehicles,id',
            'amount'       => 'nullable|numeric|min:0',
            'description'  => 'nullable|string|max:255',
            'expense_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 400);
        }

        // If vehicle is being changed, validate the new vehicle is active
        if ($request->filled('vehicle_id') && $request->vehicle_id != $expense->vehicle_id) {
            $vehicle = Vehicle::find($request->vehicle_id);
            if ($vehicle->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot reassign expense to an inactive vehicle',
                ], 400);
            }
        }

        $expense->fill($request->only(['vehicle_id', 'amount', 'description', 'expense_date']));
        $expense->save();

        $expense->load('vehicle');

        return response()->json([
            'success' => true,
            'message' => 'Vehicle expense updated successfully',
            'data'    => $expense,
        ], 200);
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    /**
     * DELETE VEHICLE EXPENSE
     */
    public function destroy($id)
    {
        $expense = VehicleExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle expense not found',
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle expense deleted successfully',
        ], 200);
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * VEHICLE EXPENSE STATISTICS
     *
     * Returns:
     * - overall totals (all time, this month, this year)
     * - per-vehicle breakdown: total spent, expense count, last expense date
     * - date-range total if start_date + end_date are provided
     *
     * WHY per-vehicle breakdown: gives the frontend a ready summary to render
     * a "top spending vehicles" view without additional queries.
     */
    public function statistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'vehicle_id' => 'nullable|exists:vehicles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 400);
        }

        $now = now();

        // Overall totals
        $totalAllTime  = VehicleExpense::sum('amount');
        $totalThisMonth = VehicleExpense::whereMonth('expense_date', $now->month)
                            ->whereYear('expense_date', $now->year)
                            ->sum('amount');
        $totalThisYear  = VehicleExpense::whereYear('expense_date', $now->year)
                            ->sum('amount');

        // Date range total (only if both dates provided)
        $dateRangeTotal = null;
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query = VehicleExpense::whereBetween('expense_date', [
                $request->start_date,
                $request->end_date,
            ]);
            if ($request->filled('vehicle_id')) {
                $query->where('vehicle_id', $request->vehicle_id);
            }
            $dateRangeTotal = $query->sum('amount');
        }

        // Per-vehicle breakdown
        // WHY: JOIN vehicles so we get vehicle_number alongside the aggregates —
        //      avoids N+1 when the frontend renders a vehicle-level table.
        $perVehicle = VehicleExpense::select(
                'vehicle_id',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as expense_count'),
                DB::raw('MAX(expense_date) as last_expense_date')
            )
            ->with('vehicle:id,vehicle_number,type,status')
            ->groupBy('vehicle_id')
            ->orderByDesc('total_amount')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_all_time'   => round($totalAllTime, 2),
                'total_this_month' => round($totalThisMonth, 2),
                'total_this_year'  => round($totalThisYear, 2),
                'date_range_total' => $dateRangeTotal !== null ? round($dateRangeTotal, 2) : null,
                'per_vehicle'      => $perVehicle,
            ],
        ], 200);
    }
}