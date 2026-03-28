<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page'  => 'nullable|integer|min:1|max:200',
            'search'    => 'nullable|string|max:200',
            'status'    => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $perPage      = $request->input('per_page', 10);
        $search       = $request->input('search');
        $statusFilter = $request->input('status');

        /**
         * WHAT: Eager-load the 'partner' relationship alongside rides count
         * WHY:  When type is 'partner', the consumer of this API needs partner
         *       details (name, etc.) without a second request.
         * HOW:  with('partner') — Laravel will LEFT JOIN partners only for
         *       vehicles that actually have a partner_id set.
         */
        $query = Vehicle::with('partner')->withCount('rides');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('vehicle_number', 'LIKE', "%{$search}%")
                  ->orWhere('type', 'LIKE', "%{$search}%");
            });
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $query->orderBy('created_at', 'desc');

        $vehicles = $query->paginate($perPage);

        $statistics = [
            'total_vehicles'    => Vehicle::count(),
            'active_vehicles'   => Vehicle::where('status', 'active')->count(),
            'inactive_vehicles' => Vehicle::where('status', 'inactive')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Vehicles retrieved successfully',
            'data'    => [
                'vehicles'   => $vehicles->items(),
                'statistics' => $statistics,
                'pagination' => [
                    'current_page' => $vehicles->currentPage(),
                    'last_page'    => $vehicles->lastPage(),
                    'per_page'     => $vehicles->perPage(),
                    'total'        => $vehicles->total(),
                    'from'         => $vehicles->firstItem(),
                    'to'           => $vehicles->lastItem(),
                ],
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        /**
         * WHAT: Added partner_id validation with required_if
         * WHY:  partner_id must be provided ONLY when type === 'partner'.
         *       For any other type it should be absent/null.
         *       We also verify the partner actually exists in the DB.
         * HOW:
         *   - required_if:type,partner  → makes it mandatory when type is 'partner'
         *   - nullable                  → allows NULL for non-partner vehicles
         *   - exists:partners,id        → FK integrity check at the app layer
         */
        $validator = Validator::make($request->all(), [
            'vehicle_number' => 'required|string|max:50|unique:vehicles,vehicle_number',
            'type'           => 'required|string|max:50',
            'status'         => 'nullable|in:active,inactive',
            'partner_id'     => [
                'nullable',
                Rule::requiredIf($request->type === 'partner'),
                'exists:partners,id',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        /**
         * WHAT: Validate partner is active when type is 'partner'
         * WHY:  We should not assign an inactive partner to a vehicle —
         *       it would cause downstream issues in ride creation.
         * HOW:  Only check when type is explicitly 'partner'.
         */
        if ($request->type === 'partner') {
            $partner = Partner::find($request->partner_id);
            if (!$partner || !$partner->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner not found or is inactive',
                ], 400);
            }
        }

        /**
         * WHAT: Save partner_id conditionally
         * WHY:  For non-partner vehicles, we explicitly store NULL so the
         *       column is clean and there's no stale data.
         * HOW:  Ternary — if type is 'partner' use the provided partner_id,
         *       otherwise force null.
         */
        $vehicle = Vehicle::create([
            'vehicle_number' => $request->vehicle_number,
            'type'           => $request->type,
            'status'         => $request->input('status', 'active'),
            'partner_id'     => $request->type === 'partner' ? $request->partner_id : null,
        ]);

        $vehicle->load('partner')->loadCount('rides');

        return response()->json([
            'success' => true,
            'message' => 'Vehicle added successfully',
            'data'    => $vehicle,
        ], 201);
    }

    public function show($id)
    {
        $vehicle = Vehicle::with('partner')->withCount('rides')->find($id);

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicle retrieved successfully',
            'data'    => $vehicle,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found',
            ], 404);
        }

        /**
         * WHAT: partner_id validation mirrors store() logic
         * WHY:  Same business rule — partner_id is required when type is 'partner',
         *       forbidden (null) otherwise. On update, we also ignore the current
         *       vehicle's own vehicle_number for the uniqueness check.
         */
        $validator = Validator::make($request->all(), [
            'vehicle_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles', 'vehicle_number')->ignore($vehicle->id),
            ],
            'type'       => 'required|string|max:50',
            'status'     => 'nullable|in:active,inactive',
            'partner_id' => [
                'nullable',
                Rule::requiredIf($request->type === 'partner'),
                'exists:partners,id',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Active partner check — same reasoning as store()
        if ($request->type === 'partner') {
            $partner = Partner::find($request->partner_id);
            if (!$partner || !$partner->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner not found or is inactive',
                ], 400);
            }
        }

        /**
         * WHAT: Update partner_id conditionally
         * WHY:  If a vehicle type changes FROM 'partner' TO something else,
         *       we must clear partner_id so no orphaned reference remains.
         *       If type stays 'partner', we update to the new partner_id.
         */
        $vehicle->update([
            'vehicle_number' => $request->vehicle_number,
            'type'           => $request->type,
            'status'         => $request->input('status', $vehicle->status),
            'partner_id'     => $request->type === 'partner' ? $request->partner_id : null,
        ]);

        $vehicle->refresh()->load('partner')->loadCount('rides');

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully',
            'data'    => $vehicle,
        ], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $vehicle->update([
            'status' => $request->status,
        ]);

        $vehicle->loadCount('rides');

        return response()->json([
            'success' => true,
            'message' => "Vehicle status updated to {$request->status} successfully",
            'data'    => $vehicle,
        ], 200);
    }

    public function destroy($id)
    {
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found',
            ], 404);
        }

        if ($vehicle->rides()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete vehicle with existing rides. Please set it to inactive instead.',
            ], 400);
        }

        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle deleted successfully',
        ], 200);
    }

    public function getByPartner($partnerId)
    {
        $partner = Partner::find($partnerId);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        $vehicles = Vehicle::withCount('rides')
            ->where('partner_id', $partnerId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Partner vehicles retrieved successfully',
            'data'    => [
                'partner'  => $partner,
                'vehicles' => $vehicles,
            ],
        ], 200);
    }
    public function getPersonalVehicles()
    {

        $vehicles = Vehicle::where('type', 'personal')->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Personal vehicles retrieved successfully',
            'data'    => [
                'vehicles' => $vehicles,
            ],
        ], 200);
    }
}