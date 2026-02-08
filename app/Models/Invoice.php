<?php
// ============================================================================
// app/Models/Invoice.php
// ============================================================================
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'ride_id',
        'party_id',
        'invoice_type',
        'amount',
        'payment_status',
        'payment_date',
        'description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    public function scopeAdvance($query)
    {
        return $query->where('invoice_type', 'advance');
    }

    public function scopeBalance($query)
    {
        return $query->where('invoice_type', 'balance');
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('invoice_date', [$startDate, $endDate]);
    }

    public function scopeMonthYear($query, $month, $year)
    {
        return $query->whereMonth('invoice_date', $month)
                     ->whereYear('invoice_date', $year);
    }
}