<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\Invoice;
use App\Models\RideExpense;
use App\Models\Partner;
use App\Models\Party;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RideController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'is_completed' => 'nullable|boolean',
            'ride_type' => 'nullable|in:personal,partner',
            'partner_id' => 'nullable|exists:partners,id',
            'party_id' => 'nullable|exists:parties,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
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
        $query = Ride::with(['vehicle', 'party', 'partner']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ride_number', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhere('route', 'LIKE', "%{$search}%"); // NEW: Added route to search
            });
        }

        if ($request->filled('is_completed')) {
            $query->where('is_completed', $request->boolean('is_completed'));
        }

        if ($request->filled('ride_type')) {
            $query->where('ride_type', $request->ride_type);
        }

        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        if ($request->filled('party_id')) {
            $query->where('party_id', $request->party_id);
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        $query->orderBy('start_date', 'desc');
        $rides = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Rides retrieved successfully',
            'data' => $rides
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ride_number' => 'required|string|unique:rides,ride_number',
            'start_date' => 'required|date',
            'vehicle_id' => 'required|exists:vehicles,id',
            'party_id' => 'required|exists:parties,id',
            'ride_type' => 'required|in:personal,partner',
            'partner_id' => 'required_if:ride_type,partner|nullable|exists:partners,id',
            'booking_amount' => 'required|numeric|min:0',
            'advance_amount' => 'nullable|numeric|min:0|lte:booking_amount',
            'notes' => 'nullable|string',
            'route' => 'nullable|string|max:1000', // NEW: Added route validation
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        if ($request->ride_type === 'partner') {
            $partner = Partner::find($request->partner_id);
            if (!$partner || !$partner->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner not found or inactive'
                ], 400);
            }
        }

        $party = Party::find($request->party_id);
        if (!$party->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Party is inactive'
            ], 400);
        }

        $vehicle = Vehicle::find($request->vehicle_id);
        if ($vehicle->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle is not active'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $ride = Ride::create([
                'ride_number' => $request->ride_number,
                'start_date' => $request->start_date,
                'vehicle_id' => $request->vehicle_id,
                'party_id' => $request->party_id,
                'ride_type' => $request->ride_type,
                'partner_id' => $request->ride_type === 'partner' ? $request->partner_id : null,
                'booking_amount' => $request->booking_amount,
                'advance_amount' => $request->advance_amount ?? 0,
                'is_completed' => false,
                'notes' => $request->notes,
                'route' => $request->route, // NEW: Added route field
            ]);

            $invoice = null;
            $advanceAmount = $request->advance_amount ?? 0;
            
            if ($advanceAmount > 0 && $advanceAmount <= $request->booking_amount) {
                $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($ride->id, 5, '0', STR_PAD_LEFT);
                
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => now(),
                    'ride_id' => $ride->id,
                    'party_id' => $ride->party_id,
                    'invoice_type' => 'advance',
                    'amount' => $advanceAmount,
                    'payment_status' => 'paid',
                    'payment_date' => now(),
                    'description' => "Advance payment for ride {$ride->ride_number}",
                ]);
            }

            DB::commit();

            $ride->load(['vehicle', 'party', 'partner']);

            return response()->json([
                'success' => true,
                'message' => 'Ride created successfully',
                'data' => [
                    'ride' => $ride,
                    'invoice' => $invoice
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ride',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $ride = Ride::with(['vehicle', 'party', 'partner'])->find($id);

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Ride not found'
            ], 404);
        }

        $invoices = Invoice::where('ride_id', $ride->id)->orderBy('invoice_date', 'desc')->get();
        $expenses = RideExpense::where('ride_id', $ride->id)->orderBy('created_at', 'desc')->get();

        $invoiceSummary = [
            'total_invoiced' => $invoices->sum('amount'),
            'total_paid' => $invoices->where('payment_status', 'paid')->sum('amount'),
            'total_unpaid' => $invoices->where('payment_status', 'unpaid')->sum('amount'),
        ];

        $expenseSummary = [
            'total_expenses' => $expenses->sum('expense_amount'),
            'count' => $expenses->count(),
        ];

        $totalAmount = $ride->booking_amount + $expenseSummary['total_expenses'];
        $balanceAmount = $totalAmount - $invoiceSummary['total_paid'];
        

        return response()->json([
            'success' => true,
            'data' => [
                'ride' => $ride,
                'invoices' => $invoices,
                'invoice_summary' => $invoiceSummary,
                'expenses' => $expenses,
                'expense_summary' => $expenseSummary,
                'financial_summary' => [
                    'booking_amount' => $ride->booking_amount,
                    'total_expenses' => $expenseSummary['total_expenses'],
                    'total_amount' => $totalAmount,
                    'balance_amount' => $balanceAmount,

                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $ride = Ride::find($id);

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Ride not found'
            ], 404);
        }

        if ($ride->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update completed ride'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'ride_number' => ['nullable', 'string', Rule::unique('rides', 'ride_number')->ignore($ride->id)],
            'start_date' => 'nullable|date',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'party_id' => 'nullable|exists:parties,id',
            'booking_amount' => 'nullable|numeric|min:0',
            'advance_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'route' => 'nullable|string|max:1000', // NEW: Added route validation
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $newBookingAmount = $request->filled('booking_amount') ? $request->booking_amount : $ride->booking_amount;
        $newAdvanceAmount = $request->filled('advance_amount') ? $request->advance_amount : $ride->advance_amount;

        if ($newAdvanceAmount > $newBookingAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Advance amount cannot be greater than booking amount'
            ], 400);
        }

        $oldAdvanceAmount = $ride->advance_amount;

        DB::beginTransaction();

        try {
            $ride->fill($request->only([
                'ride_number',
                'start_date',
                'vehicle_id',
                'party_id',
                'booking_amount',
                'advance_amount',
                'notes',
                'route' // NEW: Added route to fillable fields
            ]));
            $ride->save();

            if ($request->filled('advance_amount') && $newAdvanceAmount != $oldAdvanceAmount) {
                if ($newAdvanceAmount > $oldAdvanceAmount && $newAdvanceAmount <= $newBookingAmount) {
                    $existingInvoice = Invoice::where('ride_id', $ride->id)
                        ->where('invoice_type', 'advance')
                        ->first();

                    if ($existingInvoice) {
                        $existingInvoice->update([
                            'amount' => $newAdvanceAmount,
                            'payment_date' => now(),
                        ]);
                    } else if ($newAdvanceAmount > 0) {
                        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($ride->id, 5, '0', STR_PAD_LEFT);
                        
                        Invoice::create([
                            'invoice_number' => $invoiceNumber,
                            'invoice_date' => now(),
                            'ride_id' => $ride->id,
                            'party_id' => $ride->party_id,
                            'invoice_type' => 'advance',
                            'amount' => $newAdvanceAmount,
                            'payment_status' => 'paid',
                            'payment_date' => now(),
                            'description' => "Advance payment for ride {$ride->ride_number}",
                        ]);
                    }
                }
            }

            DB::commit();

            $ride->load(['vehicle', 'party', 'partner']);

            return response()->json([
                'success' => true,
                'message' => 'Ride updated successfully',
                'data' => $ride
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ride',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsCompleted(Request $request, $id)
    {
        $ride = Ride::find($id);

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Ride not found'
            ], 404);
        }

        if ($ride->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Ride is already completed'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_received' => 'nullable|boolean',
            'completion_notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        DB::beginTransaction();

        try {
            $ride->is_completed = true;
            $ride->completed_date = now();
            
            if ($request->filled('completion_notes')) {
                $ride->notes = $ride->notes 
                    ? "{$ride->notes}\n\nCompletion: {$request->completion_notes}"
                    : "Completion: {$request->completion_notes}";
            }
            
            $ride->save();

            $totalExpenses = $ride->rideExpenses()->sum('expense_amount');
            $totalAmount = $ride->booking_amount + $totalExpenses;
            $paidAmount = $ride->invoices()->where('payment_status', 'paid')->sum('amount');
            $balanceAmount = $totalAmount - $paidAmount;

            $balanceInvoice = null;

            if ($balanceAmount > 0 && $balanceAmount <= $totalAmount) {
                $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($ride->id, 5, '0', STR_PAD_LEFT) . '-BAL';
                
                $balanceInvoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => now(),
                    'ride_id' => $ride->id,
                    'party_id' => $ride->party_id,
                    'invoice_type' => 'balance',
                    'amount' => $balanceAmount,
                    'payment_status' => $request->boolean('payment_received') ? 'paid' : 'unpaid',
                    'payment_date' => $request->boolean('payment_received') ? now() : null,
                    'description' => "Balance payment for ride {$ride->ride_number}",
                ]);
            }

            DB::commit();

            $invoices = Invoice::where('ride_id', $ride->id)->get();
            $expenses = RideExpense::where('ride_id', $ride->id)->get();

            return response()->json([
                'success' => true,
                'message' => 'Ride marked as completed successfully',
                'data' => [
                    'ride' => $ride,
                    'balance_invoice' => $balanceInvoice,
                    'financial_summary' => [
                        'booking_amount' => $ride->booking_amount,
                        'total_expenses' => $expenses->sum('expense_amount'),
                        'total_amount' => $totalAmount,
                        'total_paid' => $invoices->where('payment_status', 'paid')->sum('amount'),
                        'balance_remaining' => $balanceAmount,
                        'profit' => $ride->booking_amount - $expenses->sum('expense_amount'),
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete ride',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $ride = Ride::find($id);

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Ride not found'
            ], 404);
        }

        if ($ride->is_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete completed ride'
            ], 400);
        }

        DB::beginTransaction();

        try {
            Invoice::where('ride_id', $ride->id)->delete();
            RideExpense::where('ride_id', $ride->id)->delete();
            $ride->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ride deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ride',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'partner_id' => 'nullable|exists:partners,id',
            'party_id' => 'nullable|exists:parties,id',
            'ride_type' => 'nullable|in:personal,partner',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $query = Ride::query();

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        if ($request->filled('party_id')) {
            $query->where('party_id', $request->party_id);
        }

        if ($request->filled('ride_type')) {
            $query->where('ride_type', $request->ride_type);
        }

        $byStatus = [
            'completed' => (clone $query)->where('is_completed', true)->count(),
            'pending' => (clone $query)->where('is_completed', false)->count(),
        ];

        $byType = (clone $query)
            ->select('ride_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(booking_amount) as total_booking'))
            ->groupBy('ride_type')
            ->get();

        $allRides = $query->get();
        $overall = [
            'total_rides' => $allRides->count(),
            'total_booking_amount' => $allRides->sum('booking_amount'),
            'total_advance_amount' => $allRides->sum('advance_amount'),
            'average_booking_amount' => $allRides->avg('booking_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'by_status' => $byStatus,
                'by_type' => $byType,
                'overall' => $overall
            ]
        ], 200);
    }
}