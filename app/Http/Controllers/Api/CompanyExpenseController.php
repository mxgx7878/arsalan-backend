<?php
// ============================================================================
// app/Http/Controllers/Api/CompanyExpenseController.php
// ============================================================================
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanyExpenseController extends Controller
{
    /**
     * GET ALL COMPANY EXPENSES
     * Retrieves all company expenses with optional filtering and pagination
     * Includes category relationship
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:expense_categories,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $perPage = $request->input('per_page', 10);
        $query = CompanyExpense::with('category');

        // Filter by search term
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('expense_date', [$request->start_date, $request->end_date]);
        }

        $query->orderBy('expense_date', 'desc');

        $expenses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $expenses
        ], 200);
    }

    /**
     * CREATE COMPANY EXPENSE
     * Creates a new company expense with category
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:expense_categories,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $expense = CompanyExpense::create([
            'category_id' => $request->category_id,
            'expense_date' => $request->expense_date,
            'amount' => $request->amount,
            'description' => $request->description,
            'notes' => $request->notes,
        ]);

        // Load category relationship
        $expense->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Company expense created successfully',
            'data' => $expense
        ], 201);
    }

    /**
     * GET SINGLE COMPANY EXPENSE
     * Retrieves a single company expense with category
     */
    public function show($id)
    {
        $expense = CompanyExpense::with('category')->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Company expense not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expense
        ], 200);
    }

    /**
     * UPDATE COMPANY EXPENSE
     * Updates an existing company expense
     */
    public function update(Request $request, $id)
    {
        $expense = CompanyExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Company expense not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:expense_categories,id',
            'expense_date' => 'nullable|date',
            'amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $expense->fill($request->only(['category_id', 'expense_date', 'amount', 'description', 'notes']));
        $expense->save();

        // Load category relationship
        $expense->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Company expense updated successfully',
            'data' => $expense
        ], 200);
    }

    /**
     * DELETE COMPANY EXPENSE
     * Deletes a company expense
     */
    public function destroy($id)
    {
        $expense = CompanyExpense::find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Company expense not found'
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company expense deleted successfully'
        ], 200);
    }

    /**
     * COMPANY EXPENSE STATISTICS
     * Provides statistics with category breakdown
     */
    public function statistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:expense_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $query = CompanyExpense::with('category');

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('expense_date', [$request->start_date, $request->end_date]);
        }

        $expenses = $query->get();

        // Overall statistics
        $overall = [
            'total_expenses' => $expenses->count(),
            'total_amount' => $expenses->sum('amount'),
            'average_expense' => $expenses->avg('amount'),
        ];

        // Category-wise breakdown
        $byCategory = $expenses->groupBy('category_id')->map(function ($categoryExpenses) {
            return [
                'category' => $categoryExpenses->first()->category,
                'count' => $categoryExpenses->count(),
                'total_amount' => $categoryExpenses->sum('amount'),
                'average_amount' => $categoryExpenses->avg('amount'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'overall' => $overall,
                'by_category' => $byCategory,
            ]
        ], 200);
    }
}