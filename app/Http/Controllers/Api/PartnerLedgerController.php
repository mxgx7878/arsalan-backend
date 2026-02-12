<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PartnerLedgerController extends Controller
{
    /**
     * Get partner ledger with entries
     * 
     * @param Request $request
     * @param int $partnerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $partnerId)
    {
        $partner = Partner::find($partnerId);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $perPage = $request->input('per_page', 50);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Build query
        $query = PartnerLedgerEntry::forPartner($partnerId)
            ->with('creator:id,name')
            ->chronological();

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        $entries = $query->paginate($perPage);

        // Calculate summary
        $summaryQuery = PartnerLedgerEntry::forPartner($partnerId);
        
        if ($startDate && $endDate) {
            $summaryQuery->dateRange($startDate, $endDate);
        }

        $summary = [
            'total_credit' => $summaryQuery->sum('credit'),
            'total_debit' => $summaryQuery->sum('debit'),
            'opening_balance' => $this->getOpeningBalance($partnerId, $startDate),
            'closing_balance' => $partner->current_balance,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Ledger entries retrieved successfully',
            'data' => [
                'partner' => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'current_balance' => $partner->current_balance,
                ],
                'date_range' => [
                    'from' => $startDate,
                    'to' => $endDate,
                ],
                'summary' => $summary,
                'entries' => $entries->items(),
                'pagination' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'per_page' => $entries->perPage(),
                    'total' => $entries->total(),
                    'from' => $entries->firstItem(),
                    'to' => $entries->lastItem(),
                ],
            ],
        ], 200);
    }

    /**
     * Get opening balance for a date range
     * 
     * @param int $partnerId
     * @param string|null $startDate
     * @return float
     */
    private function getOpeningBalance($partnerId, $startDate = null)
    {
        if (!$startDate) {
            // If no start date, find the first entry
            $firstEntry = PartnerLedgerEntry::forPartner($partnerId)
                ->chronological()
                ->first();
            
            return $firstEntry ? $firstEntry->balance - $firstEntry->credit + $firstEntry->debit : 0;
        }

        // Get the last entry before start date
        $lastEntryBeforeStart = PartnerLedgerEntry::forPartner($partnerId)
            ->where('entry_date', '<', $startDate)
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntryBeforeStart ? $lastEntryBeforeStart->balance : 0;
    }

    /**
     * Store a new ledger entry
     * 
     * @param Request $request
     * @param int $partnerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $partnerId)
    {
        $partner = Partner::find($partnerId);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'entry_date' => 'required|date',
            'description' => 'required|string|max:255',
            'credit' => 'required|numeric|min:0',
            'debit' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        // Validate that either credit or debit has value, but not both
        $credit = $request->input('credit', 0);
        $debit = $request->input('debit', 0);

        if ($credit > 0 && $debit > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot have both credit and debit in the same entry',
            ], 400);
        }

        if ($credit == 0 && $debit == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Either credit or debit must be greater than 0',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Get previous entry to calculate balance
            $previousEntry = PartnerLedgerEntry::where('partner_id', $partnerId)
                ->where(function ($query) use ($request) {
                    $query->where('entry_date', '<', $request->entry_date)
                        ->orWhere(function ($q) use ($request) {
                            $q->where('entry_date', '=', $request->entry_date);
                        });
                })
                ->orderBy('entry_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $previousBalance = $previousEntry ? $previousEntry->balance : 0;
            $newBalance = $previousBalance + $credit - $debit;

            // Create ledger entry
            $entry = PartnerLedgerEntry::create([
                'partner_id' => $partnerId,
                'entry_date' => $request->entry_date,
                'description' => $request->description,
                'credit' => $credit,
                'debit' => $debit,
                'balance' => $newBalance,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // The model's boot method will handle subsequent balance recalculation
            
            DB::commit();

            $entry->load('creator:id,name');

            return response()->json([
                'success' => true,
                'message' => 'Ledger entry added successfully',
                'data' => [
                    'entry' => $entry,
                    'partner_balance' => $entry->balance,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add ledger entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific ledger entry
     * 
     * @param int $partnerId
     * @param int $entryId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($partnerId, $entryId)
    {
        $partner = Partner::find($partnerId);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        $entry = PartnerLedgerEntry::forPartner($partnerId)
            ->with('creator:id,name')
            ->find($entryId);

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Ledger entry not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ledger entry retrieved successfully',
            'data' => $entry,
        ], 200);
    }

    /**
     * Update a ledger entry
     * 
     * @param Request $request
     * @param int $partnerId
     * @param int $entryId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $partnerId, $entryId)
    {
        $partner = Partner::find($partnerId);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        $entry = PartnerLedgerEntry::forPartner($partnerId)->find($entryId);

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Ledger entry not found',
            ], 404);
        }

        // Prevent editing opening balance
        if ($entry->isOpeningBalance()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit opening balance entry. Please delete and create a new one if needed.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'entry_date' => 'required|date',
            'description' => 'required|string|max:255',
            'credit' => 'required|numeric|min:0',
            'debit' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $credit = $request->input('credit', 0);
        $debit = $request->input('debit', 0);

        if ($credit > 0 && $debit > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot have both credit and debit in the same entry',
            ], 400);
        }

        if ($credit == 0 && $debit == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Either credit or debit must be greater than 0',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Update entry
            $entry->update([
                'entry_date' => $request->entry_date,
                'description' => $request->description,
                'credit' => $credit,
                'debit' => $debit,
                'notes' => $request->notes,
            ]);

            // The model's boot method will handle balance recalculation
            
            DB::commit();

            $entry->refresh()->load('creator:id,name');

            // Count affected entries
            $affectedEntries = PartnerLedgerEntry::forPartner($partnerId)
                ->where(function ($query) use ($entry) {
                    $query->where('entry_date', '>', $entry->entry_date)
                        ->orWhere(function ($q) use ($entry) {
                            $q->where('entry_date', '=', $entry->entry_date)
                                ->where('id', '>', $entry->id);
                        });
                })
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Ledger entry updated successfully',
                'data' => [
                    'entry' => $entry,
                    'affected_entries' => $affectedEntries,
                    'partner_balance' => $partner->fresh()->current_balance,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ledger entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a ledger entry
     * 
     * @param int $partnerId
     * @param int $entryId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($partnerId, $entryId)
    {
        $partner = Partner::find($partnerId);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        $entry = PartnerLedgerEntry::forPartner($partnerId)->find($entryId);

        if (!$entry) {
            return response()->json([
                'success' => false,
                'message' => 'Ledger entry not found',
            ], 404);
        }

        // Prevent deleting opening balance
        if ($entry->isOpeningBalance()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete opening balance entry',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Count affected entries before deletion
            $affectedEntries = PartnerLedgerEntry::forPartner($partnerId)
                ->where(function ($query) use ($entry) {
                    $query->where('entry_date', '>', $entry->entry_date)
                        ->orWhere(function ($q) use ($entry) {
                            $q->where('entry_date', '=', $entry->entry_date)
                                ->where('id', '>', $entry->id);
                        });
                })
                ->count();

            $entry->delete();

            // The model's boot method will handle balance recalculation
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ledger entry deleted successfully',
                'data' => [
                    'affected_entries' => $affectedEntries,
                    'partner_balance' => $partner->fresh()->current_balance,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ledger entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ledger summary for a partner
     * 
     * @param int $partnerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary($partnerId)
    {
        $partner = Partner::withLedgerSummary()->find($partnerId);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        $openingBalanceEntry = $partner->openingBalance;
        $last5Entries = $partner->ledgerEntries()
            ->with('creator:id,name')
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Ledger summary retrieved successfully',
            'data' => [
                'partner' => [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'current_balance' => $partner->current_balance,
                ],
                'summary' => [
                    'total_entries' => $partner->total_entries ?? 0,
                    'opening_balance' => $openingBalanceEntry ? $openingBalanceEntry->balance : 0,
                    'total_credit' => $partner->total_credit ?? 0,
                    'total_debit' => $partner->total_debit ?? 0,
                    'current_balance' => $partner->current_balance,
                ],
                'last_5_entries' => $last5Entries,
            ],
        ], 200);
    }

    /**
     * Set opening balance for a partner
     * 
     * @param Request $request
     * @param int $partnerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function setOpeningBalance(Request $request, $partnerId)
    {
        $partner = Partner::find($partnerId);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
            ], 404);
        }

        // Check if opening balance already exists
        $existingOpeningBalance = PartnerLedgerEntry::forPartner($partnerId)
            ->where('description', 'OPENING BALANCE')
            ->first();

        if ($existingOpeningBalance) {
            return response()->json([
                'success' => false,
                'message' => 'Opening balance already exists. Please update or delete the existing entry.',
                'data' => [
                    'existing_entry' => $existingOpeningBalance,
                ],
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'opening_balance' => 'required|numeric',
            'opening_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Create opening balance entry
            $entry = PartnerLedgerEntry::create([
                'partner_id' => $partnerId,
                'entry_date' => $request->opening_date,
                'description' => 'OPENING BALANCE',
                'credit' => 0,
                'debit' => 0,
                'balance' => $request->opening_balance,
                'notes' => 'Initial opening balance',
                'created_by' => auth()->id(),
            ]);

            // Update partner's current balance
            $partner->update(['current_balance' => $request->opening_balance]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Opening balance set successfully',
                'data' => [
                    'entry' => $entry,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to set opening balance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}