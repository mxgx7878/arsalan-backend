<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

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
        'route',
        'container_no', // New: optional container reference for logistics/shipping rides
    ];

    protected function casts(): array
    {
        return [
            'start_date'     => 'date',
            'completed_date' => 'date',
            'booking_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'is_completed'   => 'boolean',
            'created_at'     => 'datetime',
            'updated_at'     => 'datetime',
        ];
    }

    // ========================================================================
    // RELATIONSHIPS
    // ========================================================================

    /**
     * Get the vehicle associated with the ride.
     *
     * WHAT: Standard belongsTo on vehicle_id
     * WHY:  Every ride has exactly one vehicle. When ride_type is 'partner',
     *       this vehicle will also carry a partner_id — so loading
     *       vehicle.partner gives us the full ownership chain in one query.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the party (customer) associated with the ride.
     */
    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Get the partner directly associated with the ride (ride-level FK).
     *
     * WHAT: Direct partner on the ride — set when ride_type === 'partner'
     * WHY:  Allows filtering rides by partner and quick access to partner
     *       details without going through the vehicle relationship.
     * NOTE: vehicle.partner and this partner() should always refer to the
     *       same partner when ride_type is 'partner'. Both are kept so that
     *       queries like Ride::with(['partner', 'vehicle.partner']) work
     *       cleanly and the frontend gets data from both angles.
     */
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Get all expenses associated with this ride.
     */
    public function rideExpenses()
    {
        return $this->hasMany(RideExpense::class);
    }

    /**
     * Get all invoices associated with this ride.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // ========================================================================
    // COMPUTED ATTRIBUTES (ACCESSORS)
    // ========================================================================

    /**
     * Calculate total amount (booking amount + all expenses).
     */
    public function getTotalAmountAttribute()
    {
        return $this->booking_amount + $this->rideExpenses()->sum('expense_amount');
    }

    /**
     * Calculate remaining balance (total amount - paid invoices).
     */
    public function getBalanceAmountAttribute()
    {
        $paidAmount = $this->invoices()->where('payment_status', 'paid')->sum('amount');
        return $this->total_amount - $paidAmount;
    }

    /**
     * Calculate total expenses for this ride.
     */
    public function getTotalExpensesAttribute()
    {
        return $this->rideExpenses()->sum('expense_amount');
    }

    // ========================================================================
    // QUERY SCOPES
    // ========================================================================

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_completed', false);
    }

    public function scopePersonal($query)
    {
        return $query->where('ride_type', 'personal');
    }

    public function scopePartner($query)
    {
        return $query->where('ride_type', 'partner');
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    public function scopeMonthYear($query, $month, $year)
    {
        return $query->whereMonth('start_date', $month)
                     ->whereYear('start_date', $year);
    }
}