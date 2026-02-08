<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PartnerController extends Controller
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

        $query = Partner::withCount('rides');

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

        $partners = $query->paginate($perPage);

        $statistics = [
            'total_partners' => Partner::count(),
            'active_partners' => Partner::where('is_active', true)->count(),
            'inactive_partners' => Partner::where('is_active', false)->count(),
            'total_partner_rides' => Partner::withCount('rides')->get()->sum('rides_count'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Partners retrieved successfully',
            'data' => [
                'partners' => $partners->items(),
                'statistics' => $statistics,
                'pagination' => [
                    'current_page' => $partners->currentPage(),
                    'last_page' => $partners->lastPage(),
                    'per_page' => $partners->perPage(),
                    'total' => $partners->total(),
                    'from' => $partners->firstItem(),
                    'to' => $partners->lastItem(),
                ],
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100|unique:partners,email',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $partner = Partner::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'is_active' => $request->input('is_active', true),
        ]);

        $partner->loadCount('rides');

        return response()->json([
            'success' => true,
            'message' => 'Partner added successfully',
            'data' => $partner,
        ], 201);
    }

    public function show($id)
    {
        $partner = Partner::withCount('rides')->find($id);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Partner retrieved successfully',
            'data' => $partner,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $partner = Partner::find($id);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => [
                'nullable',
                'email',
                'max:100',
                Rule::unique('partners', 'email')->ignore($partner->id),
            ],
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $partner->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'is_active' => $request->input('is_active', $partner->is_active),
        ]);

        $partner->refresh()->loadCount('rides');

        return response()->json([
            'success' => true,
            'message' => 'Partner updated successfully',
            'data' => $partner,
        ], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $partner = Partner::find($id);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $partner->update([
            'is_active' => $request->is_active,
        ]);

        $partner->loadCount('rides');

        $status = $request->is_active ? 'active' : 'inactive';

        return response()->json([
            'success' => true,
            'message' => "Partner status updated to {$status} successfully",
            'data' => $partner,
        ], 200);
    }

    public function destroy($id)
    {
        $partner = Partner::find($id);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        if ($partner->rides()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete partner with existing rides. Please set it to inactive instead.',
            ], 400);
        }

        $partner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Partner deleted successfully',
        ], 200);
    }
}