<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_id',
        'expense_amount',
        'expense_note',
        'expense_type',   // NEW: 'personal' | 'party'
    ];

    protected function casts(): array
    {
        return [
            'expense_amount' => 'decimal:2',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    // =========================================================================
    // QUERY SCOPES
    // =========================================================================

    /**
     * Filter only personal expenses (company-borne).
     */
    public function scopePersonal($query)
    {
        return $query->where('expense_type', 'personal');
    }

    /**
     * Filter only party expenses (chargeable to party).
     */
    public function scopeParty($query)
    {
        return $query->where('expense_type', 'party');
    }
}