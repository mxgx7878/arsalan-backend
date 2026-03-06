<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RideExpense;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RideExpenseController extends Controller
{
    // =========================================================================
    // STORE
    // =========================================================================

    public function store(Request $request, $rideId)
    {
        $ride = Ride::find($rideId);

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Ride not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'expense_amount' => 'required|numeric|min:0',
            'expense_note'   => 'nullable|string|max:500',
            /*
             * WHAT: expense_type is required on creation
             * WHY:  Caller must explicitly declare whether this cost is
             *       internal (personal) or billable to the party (party).
             *       Forcing this avoids silent mis-classification.
             */
            'expense_type'   => 'required|in:personal,party',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 400);
        }

        $expense = RideExpense::create([
            'ride_id'        => $rideId,
            'expense_amount' => $request->expense_amount,
            'expense_note'   => $request->expense_note,
            'expense_type'   => $request->expense_type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense added successfully',
            'data'    => $expense
        ], 201);
    }

    // =========================================================================
    // GET RIDE EXPENSES
    // =========================================================================

    public function getRideExpenses(Request $request, $rideId)
    {
        $ride = Ride::find($rideId);

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Ride not found'
            ], 404);
        }

        $query = RideExpense::where('ride_id', $rideId);

        /*
         * WHAT: Optional filter by expense_type
         * WHY:  Frontend may want to show personal vs party expenses
         *       separately (e.g., in a billing breakdown view).
         */
        if ($request->filled('expense_type')) {
            $query->where('expense_type', $request->expense_type);
        }

        $expenses = $query->orderBy('created_at', 'desc')->get();

        $summary = [
            'total_expenses'    => $expenses->sum('expense_amount'),
            'count'             => $expenses->count(),
            // Split totals by type for quick frontend consumption
            'personal_total'    => $expenses->where('expense_type', 'personal')->sum('expense_amount'),
            'party_total'       => $expenses->where('expense_type', 'party')->sum('expense_amount'),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'expenses' => $expenses,
                'summary'  => $summary,
            ]
        ], 200);
    }

    // =========================================================================
    // SHOW
    // =========================================================================

    public function show($id)
    {
        $expense = RideExpense::with('ride')->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $expense
        ], 200);
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, $id)
    {
        $expense = RideExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $ride = Ride::find($expense->ride_id);
        if ($ride->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update expense for completed ride'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'expense_amount' => 'nullable|numeric|min:0',
            'expense_note'   => 'nullable|string|max:500',
            /*
             * WHAT: expense_type is nullable on update
             * WHY:  Partial updates should be allowed — if the caller only
             *       wants to change the amount, they shouldn't be forced to
             *       re-send expense_type. The existing value is preserved.
             */
            'expense_type'   => 'nullable|in:personal,party',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 400);
        }

        $expense->fill($request->only(['expense_amount', 'expense_note', 'expense_type']));
        $expense->save();

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data'    => $expense
        ], 200);
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function destroy($id)
    {
        $expense = RideExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $ride = Ride::find($expense->ride_id);
        if ($ride->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete expense for completed ride'
            ], 400);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully'
        ], 200);
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    public function statistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'ride_id'      => 'nullable|exists:rides,id',
            /*
             * WHAT: expense_type filter in statistics
             * WHY:  Allows fetching stats scoped to only personal or
             *       only party expenses — useful for separate P&L views.
             */
            'expense_type' => 'nullable|in:personal,party',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 400);
        }

        $query = RideExpense::query();

        if ($request->filled('ride_id')) {
            $query->where('ride_id', $request->ride_id);
        }

        if ($request->filled('expense_type')) {
            $query->where('expense_type', $request->expense_type);
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->whereHas('ride', function ($q) use ($request) {
                if ($request->filled('start_date')) {
                    $q->where('start_date', '>=', $request->start_date);
                }
                if ($request->filled('end_date')) {
                    $q->where('start_date', '<=', $request->end_date);
                }
            });
        }

        $expenses = $query->get();

        $overall = [
            'total_expenses'  => $expenses->count(),
            'total_amount'    => $expenses->sum('expense_amount'),
            'average_expense' => $expenses->avg('expense_amount'),
            // Split by type so caller gets a full picture in one call
            'personal_total'  => $expenses->where('expense_type', 'personal')->sum('expense_amount'),
            'party_total'     => $expenses->where('expense_type', 'party')->sum('expense_amount'),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'overall' => $overall
            ]
        ], 200);
    }
}