<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class PartnerLedgerEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'partner_id',
        'entry_date',
        'description',
        'credit',
        'debit',
        'balance',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'credit' => 'decimal:2',
        'debit' => 'decimal:2',
        'balance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationship: Belongs to Partner
     */
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Relationship: Created by User
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Boot method to handle automatic balance recalculation
     */
    protected static function boot()
    {
        parent::boot();

        // After creating, update balances
        static::created(function ($entry) {
            $entry->recalculateSubsequentBalances();
        });

        // After updating, recalculate balances
        static::updated(function ($entry) {
            if ($entry->isDirty(['credit', 'debit', 'entry_date'])) {
                $entry->recalculateSubsequentBalances();
            }
        });

        // After deleting, recalculate balances
        static::deleted(function ($entry) {
            $entry->recalculateSubsequentBalances();
        });
    }

    /**
     * Get the previous entry before this one
     */
    public function getPreviousEntry()
    {
        return static::where('partner_id', $this->partner_id)
            ->where(function ($query) {
                $query->where('entry_date', '<', $this->entry_date)
                    ->orWhere(function ($q) {
                        $q->where('entry_date', '=', $this->entry_date)
                            ->where('id', '<', $this->id);
                    });
            })
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Calculate balance for this entry based on previous entry
     */
    public function calculateBalance()
    {
        $previousEntry = $this->getPreviousEntry();
        $previousBalance = $previousEntry ? $previousEntry->balance : 0;
        
        return $previousBalance + $this->credit - $this->debit;
    }

    /**
     * Recalculate balances for all entries after this one
     */
    public function recalculateSubsequentBalances()
    {
        DB::transaction(function () {
            // Get all entries for this partner in chronological order
            $entries = static::where('partner_id', $this->partner_id)
                ->orderBy('entry_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $runningBalance = 0;

            foreach ($entries as $entry) {
                $runningBalance = $runningBalance + $entry->credit - $entry->debit;
                
                // Update balance without triggering events (to avoid infinite loop)
                $entry->timestamps = false;
                $entry->balance = $runningBalance;
                $entry->saveQuietly();
            }

            // Update partner's current balance
            if ($entries->isNotEmpty()) {
                Partner::where('id', $this->partner_id)
                    ->update(['current_balance' => $runningBalance]);
            }
        });
    }

    /**
     * Scope: Get entries for a specific partner
     */
    public function scopeForPartner($query, $partnerId)
    {
        return $query->where('partner_id', $partnerId);
    }

    /**
     * Scope: Get entries within date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('entry_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Order by date and ID
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('entry_date', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Check if this is an opening balance entry
     */
    public function isOpeningBalance()
    {
        return strtoupper($this->description) === 'OPENING BALANCE';
    }
}