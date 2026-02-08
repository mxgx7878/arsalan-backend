<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RideExpense;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RideExpenseController extends Controller
{
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
            'expense_note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $expense = RideExpense::create([
            'ride_id' => $rideId,
            'expense_amount' => $request->expense_amount,
            'expense_note' => $request->expense_note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense added successfully',
            'data' => $expense
        ], 201);
    }

    public function getRideExpenses($rideId)
    {
        $ride = Ride::find($rideId);

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Ride not found'
            ], 404);
        }

        $expenses = RideExpense::where('ride_id', $rideId)
            ->orderBy('created_at', 'desc')
            ->get();

        $summary = [
            'total_expenses' => $expenses->sum('expense_amount'),
            'count' => $expenses->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'expenses' => $expenses,
                'summary' => $summary
            ]
        ], 200);
    }

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
            'data' => $expense
        ], 200);
    }

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
            'expense_note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $expense->fill($request->only(['expense_amount', 'expense_note']));
        $expense->save();

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense
        ], 200);
    }

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

    public function statistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'ride_id' => 'nullable|exists:rides,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $query = RideExpense::query();

        if ($request->filled('ride_id')) {
            $query->where('ride_id', $request->ride_id);
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
            'total_expenses' => $expenses->count(),
            'total_amount' => $expenses->sum('expense_amount'),
            'average_expense' => $expenses->avg('expense_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'overall' => $overall
            ]
        ], 200);
    }
}