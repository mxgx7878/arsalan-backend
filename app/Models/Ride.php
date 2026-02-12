<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * 
     * WHAT CHANGED: Added 'route' to the fillable array
     * WHY: To allow mass assignment of route information when creating or updating rides
     * HOW: The route field will store route information for the ride
     */
    protected $fillable = [
        'ride_number',
        'start_date',
        'vehicle_id',
        'party_id',
        'ride_type',
        'partner_id',
        'booking_amount',
        'advance_amount',
        'is_completed',
        'completed_date',
        'notes',
        'route',  // NEW FIELD: Stores route information for the ride
    ];

    /**
     * The attributes that should be cast.
     * 
     * This defines how Laravel should automatically cast attributes
     * when retrieving them from the database or setting them.
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'completed_date' => 'date',
            'booking_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'is_completed' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ========================================================================
    // RELATIONSHIPS
    // ========================================================================

    /**
     * Get the vehicle associated with the ride
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the party (customer) associated with the ride
     */
    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Get the partner associated with the ride (if ride_type is 'partner')
     */
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Get all expenses associated with this ride
     */
    public function rideExpenses()
    {
        return $this->hasMany(RideExpense::class);
    }

    /**
     * Get all invoices associated with this ride
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // ========================================================================
    // COMPUTED ATTRIBUTES (ACCESSORS)
    // ========================================================================

    /**
     * Calculate total amount (booking amount + all expenses)
     */
    public function getTotalAmountAttribute()
    {
        return $this->booking_amount + $this->rideExpenses()->sum('expense_amount');
    }

    /**
     * Calculate remaining balance (total amount - paid invoices)
     */
    public function getBalanceAmountAttribute()
    {
        $paidAmount = $this->invoices()->where('payment_status', 'paid')->sum('amount');
        return $this->total_amount - $paidAmount;
    }

    /**
     * Calculate total expenses for this ride
     */
    public function getTotalExpensesAttribute()
    {
        return $this->rideExpenses()->sum('expense_amount');
    }

    // ========================================================================
    // QUERY SCOPES
    // ========================================================================

    /**
     * Scope: Filter only completed rides
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    /**
     * Scope: Filter only pending (not completed) rides
     */
    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    /**
     * Scope: Filter personal rides
     */
    public function scopePersonal($query)
    {
        return $query->where('ride_type', 'personal');
    }

    /**
     * Scope: Filter partner rides
     */
    public function scopePartner($query)
    {
        return $query->where('ride_type', 'partner');
    }

    /**
     * Scope: Filter rides within a date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Filter rides by specific month and year
     */
    public function scopeMonthYear($query, $month, $year)
    {
        return $query->whereMonth('start_date', $month)
                     ->whereYear('start_date', $year);
    }
}