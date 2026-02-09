<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanyExpenseController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:1900|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');

        $query = CompanyExpense::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->monthYear($request->month, $request->year);
        }

        $query->orderBy('expense_date', 'desc');

        $expenses = $query->paginate($perPage);

        $statistics = [
            'total_expenses' => CompanyExpense::count(),
            'total_amount' => CompanyExpense::sum('amount'),
            'average_amount' => CompanyExpense::avg('amount'),
            'current_month_total' => CompanyExpense::monthYear(date('n'), date('Y'))->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Company expenses retrieved successfully',
            'data' => [
                'expenses' => $expenses->items(),
                'statistics' => $statistics,
                'pagination' => [
                    'current_page' => $expenses->currentPage(),
                    'last_page' => $expenses->lastPage(),
                    'per_page' => $expenses->perPage(),
                    'total' => $expenses->total(),
                    'from' => $expenses->firstItem(),
                    'to' => $expenses->lastItem(),
                ],
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $expense = CompanyExpense::create([
            'expense_date' => $request->expense_date,
            'amount' => $request->amount,
            'description' => $request->description,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Company expense added successfully',
            'data' => $expense,
        ], 201);
    }

    public function show($id)
    {
        $expense = CompanyExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Company expense not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Company expense retrieved successfully',
            'data' => $expense,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $expense = CompanyExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Company expense not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $expense->update([
            'expense_date' => $request->expense_date,
            'amount' => $request->amount,
            'description' => $request->description,
            'notes' => $request->notes,
        ]);

        $expense->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Company expense updated successfully',
            'data' => $expense,
        ], 200);
    }

    public function destroy($id)
    {
        $expense = CompanyExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Company expense not found',
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company expense deleted successfully',
        ], 200);
    }
}