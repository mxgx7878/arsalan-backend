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
    ];

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

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function rideExpenses()
    {
        return $this->hasMany(RideExpense::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function getTotalAmountAttribute()
    {
        return $this->booking_amount + $this->rideExpenses()->sum('expense_amount');
    }

    public function getBalanceAmountAttribute()
    {
        $paidAmount = $this->invoices()->where('payment_status', 'paid')->sum('amount');
        return $this->total_amount - $paidAmount;
    }

    public function getTotalExpensesAttribute()
    {
        return $this->rideExpenses()->sum('expense_amount');
    }

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