<?php
// ============================================================================
// app/Models/ExpenseCategory.php
// ============================================================================
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
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

    /**
     * RELATIONSHIP: One category can have many company expenses
     */
    public function companyExpenses()
    {
        return $this->hasMany(CompanyExpense::class, 'category_id');
    }

    /**
     * SCOPE: Filter only active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * SCOPE: Filter only inactive categories
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}