<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
    public function ledgerEntries()
    {
        return $this->hasMany(PartnerLedgerEntry::class)
            ->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Get the latest ledger entry
     */
    public function latestLedgerEntry()
    {
        return $this->hasOne(PartnerLedgerEntry::class)
            ->latestOfMany();
    }

    /**
     * Get opening balance entry
     */
    public function openingBalance()
    {
        return $this->hasOne(PartnerLedgerEntry::class)
            ->oldestOfMany()
            ->where('description', 'OPENING BALANCE');
    }

    /**
     * Scope: With ledger summary
     */
    public function scopeWithLedgerSummary($query)
    {
        return $query->withSum('ledgerEntries as total_credit', 'credit')
            ->withSum('ledgerEntries as total_debit', 'debit')
            ->withCount('ledgerEntries as total_entries');
    }

}