<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'amount',
        'description',
        'expense_date',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount'       => 'decimal:2',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Each vehicle expense belongs to one vehicle.
     * Eager-loading vehicle gives us number/type without a second query.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    // =========================================================================
    // QUERY SCOPES
    // =========================================================================

    /**
     * Filter expenses within a date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    /**
     * Filter expenses for a specific month and year.
     */
    public function scopeMonthYear($query, $month, $year)
    {
        return $query->whereMonth('expense_date', $month)
                     ->whereYear('expense_date', $year);
    }
}