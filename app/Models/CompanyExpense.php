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
        'category_id', // NEW FIELD
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

    /**
     * RELATIONSHIP: Each company expense belongs to one category
     */
    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * SCOPE: Filter expenses by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    /**
     * SCOPE: Filter expenses by specific month and year
     */
    public function scopeMonthYear($query, $month, $year)
    {
        return $query->whereMonth('expense_date', $month)
                     ->whereYear('expense_date', $year);
    }

    /**
     * SCOPE: Calculate total amount for a given period
     */
    public function scopeTotalForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate])
                     ->sum('amount');
    }
}