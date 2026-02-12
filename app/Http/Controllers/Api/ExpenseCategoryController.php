<?php
// ============================================================================
// app/Http/Controllers/Api/ExpenseCategoryController.php
// ============================================================================
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseCategoryController extends Controller
{
    /**
     * GET ALL CATEGORIES
     * Retrieves all expense categories with optional filtering and pagination
     * Includes count of associated company expenses for each category
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $perPage = $request->input('per_page', 10);
        $query = ExpenseCategory::withCount('companyExpenses');

        // Filter by search term (name or description)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $query->orderBy('name', 'asc');

        $categories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $categories
        ], 200);
    }

    /**
     * CREATE NEW CATEGORY
     * Creates a new expense category
     * Validates that category name is unique
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:expense_categories,name',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $category = ExpenseCategory::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * GET SINGLE CATEGORY
     * Retrieves a single expense category by ID
     * Includes count of associated company expenses
     */
    public function show($id)
    {
        $category = ExpenseCategory::withCount('companyExpenses')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ], 200);
    }

    /**
     * UPDATE CATEGORY
     * Updates an existing expense category
     * Validates unique name (except current category)
     */
    public function update(Request $request, $id)
    {
        $category = ExpenseCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100|unique:expense_categories,name,' . $id,
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $category->fill($request->only(['name', 'description', 'is_active']));
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Expense category updated successfully',
            'data' => $category
        ], 200);
    }

    /**
     * DELETE CATEGORY
     * Deletes an expense category
     * Prevents deletion if category has associated company expenses
     */
    public function destroy($id)
    {
        $category = ExpenseCategory::withCount('companyExpenses')->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found'
            ], 404);
        }

        // Prevent deletion if category has associated expenses
        if ($category->company_expenses_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with associated expenses. Please reassign or delete the expenses first.',
                'details' => [
                    'company_expenses' => $category->company_expenses_count,
                ]
            ], 400);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense category deleted successfully'
        ], 200);
    }

    /**
     * TOGGLE ACTIVE STATUS
     * Activates or deactivates a category
     */
    public function toggleActive($id)
    {
        $category = ExpenseCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Expense category not found'
            ], 404);
        }

        $category->is_active = !$category->is_active;
        $category->save();

        return response()->json([
            'success' => true,
            'message' => $category->is_active ? 'Category activated' : 'Category deactivated',
            'data' => $category
        ], 200);
    }
}