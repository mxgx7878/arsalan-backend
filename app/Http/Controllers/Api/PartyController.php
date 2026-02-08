<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $isActiveFilter = $request->input('is_active');

        $query = Party::withCount(['rides', 'invoices']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($isActiveFilter !== null) {
            $query->where('is_active', $isActiveFilter);
        }

        $query->orderBy('created_at', 'desc');

        $parties = $query->paginate($perPage);

        $statistics = [
            'total_parties' => Party::count(),
            'active_parties' => Party::where('is_active', true)->count(),
            'inactive_parties' => Party::where('is_active', false)->count(),
            'total_rides' => Party::withCount('rides')->get()->sum('rides_count'),
            'total_invoices' => Party::withCount('invoices')->get()->sum('invoices_count'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Parties retrieved successfully',
            'data' => [
                'parties' => $parties->items(),
                'statistics' => $statistics,
                'pagination' => [
                    'current_page' => $parties->currentPage(),
                    'last_page' => $parties->lastPage(),
                    'per_page' => $parties->perPage(),
                    'total' => $parties->total(),
                    'from' => $parties->firstItem(),
                    'to' => $parties->lastItem(),
                ],
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100|unique:parties,email',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $party = Party::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'is_active' => $request->input('is_active', true),
        ]);

        $party->loadCount(['rides', 'invoices']);

        return response()->json([
            'success' => true,
            'message' => 'Party added successfully',
            'data' => $party,
        ], 201);
    }

    public function show($id)
    {
        $party = Party::withCount(['rides', 'invoices'])->find($id);

        if (!$party) {
            return response()->json([
                'success' => false,
                'message' => 'Party not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Party retrieved successfully',
            'data' => $party,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $party = Party::find($id);

        if (!$party) {
            return response()->json([
                'success' => false,
                'message' => 'Party not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => [
                'nullable',
                'email',
                'max:100',
                Rule::unique('parties', 'email')->ignore($party->id),
            ],
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $party->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'is_active' => $request->input('is_active', $party->is_active),
        ]);

        $party->refresh()->loadCount(['rides', 'invoices']);

        return response()->json([
            'success' => true,
            'message' => 'Party updated successfully',
            'data' => $party,
        ], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $party = Party::find($id);

        if (!$party) {
            return response()->json([
                'success' => false,
                'message' => 'Party not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $party->update([
            'is_active' => $request->is_active,
        ]);

        $party->loadCount(['rides', 'invoices']);

        $status = $request->is_active ? 'active' : 'inactive';

        return response()->json([
            'success' => true,
            'message' => "Party status updated to {$status} successfully",
            'data' => $party,
        ], 200);
    }

    public function destroy($id)
    {
        $party = Party::find($id);

        if (!$party) {
            return response()->json([
                'success' => false,
                'message' => 'Party not found',
            ], 404);
        }

        if ($party->rides()->count() > 0 || $party->invoices()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete party with existing rides or invoices. Please set it to inactive instead.',
            ], 400);
        }

        $party->delete();

        return response()->json([
            'success' => true,
            'message' => 'Party deleted successfully',
        ], 200);
    }
}