<?php
// ============================================================================
// app/Models/RideExpense.php
// ============================================================================
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
    ];

    protected function casts(): array
    {
        return [
            'expense_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}