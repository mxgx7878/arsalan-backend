<?php
// ============================================================================
// app/Models/CompanyExpense.php
// ============================================================================
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_date',
        'amount',
        'description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    public function scopeMonthYear($query, $month, $year)
    {
        return $query->whereMonth('expense_date', $month)
                     ->whereYear('expense_date', $year);
    }

    public function scopeTotalForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate])
                     ->sum('amount');
    }
}