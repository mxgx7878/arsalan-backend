<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $statusFilter = $request->input('status');

        $query = Vehicle::withCount('rides');

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
            'total_vehicles' => Vehicle::count(),
            'active_vehicles' => Vehicle::where('status', 'active')->count(),
            'inactive_vehicles' => Vehicle::where('status', 'inactive')->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Vehicles retrieved successfully',
            'data' => [
                'vehicles' => $vehicles->items(),
                'statistics' => $statistics,
                'pagination' => [
                    'current_page' => $vehicles->currentPage(),
                    'last_page' => $vehicles->lastPage(),
                    'per_page' => $vehicles->perPage(),
                    'total' => $vehicles->total(),
                    'from' => $vehicles->firstItem(),
                    'to' => $vehicles->lastItem(),
                ],
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_number' => 'required|string|max:50|unique:vehicles,vehicle_number',
            'type' => 'required|string|max:50',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $vehicle = Vehicle::create([
            'vehicle_number' => $request->vehicle_number,
            'type' => $request->type,
            'status' => $request->input('status', 'active'),
        ]);

        $vehicle->loadCount('rides');

        return response()->json([
            'success' => true,
            'message' => 'Vehicle added successfully',
            'data' => $vehicle,
        ], 201);
    }

    public function show($id)
    {
        $vehicle = Vehicle::withCount('rides')->find($id);

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicle retrieved successfully',
            'data' => $vehicle,
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

        $validator = Validator::make($request->all(), [
            'vehicle_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles', 'vehicle_number')->ignore($vehicle->id),
            ],
            'type' => 'required|string|max:50',
            'status' => 'nullable|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $vehicle->update([
            'vehicle_number' => $request->vehicle_number,
            'type' => $request->type,
            'status' => $request->input('status', $vehicle->status),
        ]);

        $vehicle->refresh()->loadCount('rides');

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle,
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
            'data' => $vehicle,
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
}