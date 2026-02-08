<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Ride;
use App\Models\RideExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:100',
            'payment_status' => 'nullable|in:paid,unpaid',
            'invoice_type' => 'nullable|in:advance,balance',
            'ride_id' => 'nullable|exists:rides,id',
            'party_id' => 'nullable|exists:parties,id',
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
        $query = Invoice::with(['ride', 'party']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('invoice_type')) {
            $query->where('invoice_type', $request->invoice_type);
        }

        if ($request->filled('ride_id')) {
            $query->where('ride_id', $request->ride_id);
        }

        if ($request->filled('party_id')) {
            $query->where('party_id', $request->party_id);
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        $query->orderBy('invoice_date', 'desc');
        $invoices = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Invoices retrieved successfully',
            'data' => $invoices
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_number' => 'required|string|unique:invoices,invoice_number',
            'invoice_date' => 'required|date',
            'ride_id' => 'required|exists:rides,id',
            'party_id' => 'required|exists:parties,id',
            'invoice_type' => 'required|in:advance,balance',
            'amount' => 'required|numeric|min:0',
            'payment_status' => 'required|in:paid,unpaid',
            'payment_date' => 'nullable|date',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $ride = Ride::find($request->ride_id);
        
        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Ride not found'
            ], 404);
        }

        /**
         * VALIDATION LOGIC FOR CREATE:
         * 
         * Maximum allowed invoice amount = (booking_amount + total_expenses) - sum_of_all_existing_invoices
         * 
         * Where:
         * - booking_amount: ride's booking_amount
         * - total_expenses: sum of all RideExpense.expense_amount for this ride
         * - sum_of_all_existing_invoices: sum of ALL invoices.amount (both paid & unpaid)
         * 
         * The new invoice amount must be <= maximum_allowed
         */
        
        $bookingAmount = $ride->booking_amount;
        $totalExpenses = RideExpense::where('ride_id', $ride->id)->sum('expense_amount');
        $existingInvoicesSum = Invoice::where('ride_id', $ride->id)->sum('amount');
        
        $maximumAllowed = ($bookingAmount + $totalExpenses) - $existingInvoicesSum;

        if ($request->amount > $maximumAllowed) {
            return response()->json([
                'success' => false,
                'message' => "Invoice amount cannot exceed {$maximumAllowed}. (Booking: {$bookingAmount} + Expenses: {$totalExpenses} - Existing Invoices: {$existingInvoicesSum})",
                'validation' => [
                    'booking_amount' => $bookingAmount,
                    'total_expenses' => $totalExpenses,
                    'existing_invoices_sum' => $existingInvoicesSum,
                    'maximum_allowed' => $maximumAllowed,
                    'requested_amount' => $request->amount
                ]
            ], 400);
        }

        $invoice = Invoice::create([
            'invoice_number' => $request->invoice_number,
            'invoice_date' => $request->invoice_date,
            'ride_id' => $request->ride_id,
            'party_id' => $request->party_id,
            'invoice_type' => $request->invoice_type,
            'amount' => $request->amount,
            'payment_status' => $request->payment_status,
            'payment_date' => $request->payment_date,
            'description' => $request->description,
            'notes' => $request->notes,
        ]);

        $invoice->load(['ride', 'party']);

        return response()->json([
            'success' => true,
            'message' => 'Invoice created successfully',
            'data' => $invoice
        ], 201);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['ride', 'party'])->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $invoice
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'invoice_number' => [
                'nullable',
                'string',
                Rule::unique('invoices', 'invoice_number')->ignore($invoice->id)
            ],
            'invoice_date' => 'nullable|date',
            'party_id' => 'nullable|exists:parties,id',
            'invoice_type' => 'nullable|in:advance,balance',
            'amount' => 'nullable|numeric|min:0',
            'payment_status' => 'nullable|in:paid,unpaid',
            'payment_date' => 'nullable|date',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        /**
         * VALIDATION LOGIC FOR UPDATE:
         * 
         * Maximum allowed invoice amount = (booking_amount + total_expenses) - sum_of_other_invoices
         * 
         * Where:
         * - booking_amount: ride's booking_amount
         * - total_expenses: sum of all RideExpense.expense_amount for this ride
         * - sum_of_other_invoices: sum of ALL invoices.amount EXCEPT the current one being edited (both paid & unpaid)
         * 
         * The updated invoice amount must be <= maximum_allowed
         */
        
        if ($request->filled('amount')) {
            $ride = Ride::find($invoice->ride_id);
            
            $bookingAmount = $ride->booking_amount;
            $totalExpenses = RideExpense::where('ride_id', $ride->id)->sum('expense_amount');
            $otherInvoicesSum = Invoice::where('ride_id', $ride->id)
                ->where('id', '!=', $invoice->id)
                ->sum('amount');
            
            $maximumAllowed = ($bookingAmount + $totalExpenses) - $otherInvoicesSum;

            if ($request->amount > $maximumAllowed) {
                return response()->json([
                    'success' => false,
                    'message' => "Invoice amount cannot exceed {$maximumAllowed}. (Booking: {$bookingAmount} + Expenses: {$totalExpenses} - Other Invoices: {$otherInvoicesSum})",
                    'validation' => [
                        'booking_amount' => $bookingAmount,
                        'total_expenses' => $totalExpenses,
                        'other_invoices_sum' => $otherInvoicesSum,
                        'maximum_allowed' => $maximumAllowed,
                        'requested_amount' => $request->amount
                    ]
                ], 400);
            }
        }

        $invoice->fill($request->only([
            'invoice_number',
            'invoice_date',
            'party_id',
            'invoice_type',
            'amount',
            'payment_status',
            'payment_date',
            'description',
            'notes'
        ]));
        $invoice->save();

        $invoice->load(['ride', 'party']);

        return response()->json([
            'success' => true,
            'message' => 'Invoice updated successfully',
            'data' => $invoice
        ], 200);
    }

    public function destroy($id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted successfully'
        ], 200);
    }

    public function markAsPaid(Request $request, $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        if ($invoice->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Invoice is already marked as paid'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $invoice->payment_status = 'paid';
        $invoice->payment_date = $request->payment_date ?? now();
        $invoice->save();

        $invoice->load(['ride', 'party']);

        return response()->json([
            'success' => true,
            'message' => 'Invoice marked as paid successfully',
            'data' => $invoice
        ], 200);
    }

    public function statistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'ride_id' => 'nullable|exists:rides,id',
            'party_id' => 'nullable|exists:parties,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $query = Invoice::query();

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        if ($request->filled('ride_id')) {
            $query->where('ride_id', $request->ride_id);
        }

        if ($request->filled('party_id')) {
            $query->where('party_id', $request->party_id);
        }

        $allInvoices = $query->get();

        $byStatus = [
            'paid' => $allInvoices->where('payment_status', 'paid')->count(),
            'unpaid' => $allInvoices->where('payment_status', 'unpaid')->count(),
        ];

        $byType = [
            'advance' => $allInvoices->where('invoice_type', 'advance')->count(),
            'balance' => $allInvoices->where('invoice_type', 'balance')->count(),
        ];

        $overall = [
            'total_invoices' => $allInvoices->count(),
            'total_amount' => $allInvoices->sum('amount'),
            'total_paid' => $allInvoices->where('payment_status', 'paid')->sum('amount'),
            'total_unpaid' => $allInvoices->where('payment_status', 'unpaid')->sum('amount'),
            'average_invoice_amount' => $allInvoices->avg('amount'),
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