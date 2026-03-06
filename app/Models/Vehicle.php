<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    /**
     * WHAT CHANGED: Added 'partner_id' to fillable array
     * WHY: When vehicle type is 'partner', we need to persist which partner
     *      owns/operates this vehicle. Without it in fillable, mass assignment
     *      would silently ignore the field.
     * HOW: partner_id is nullable — only populated when type === 'partner',
     *      otherwise stored as NULL in the database.
     */
    protected $fillable = [
        'vehicle_number',
        'type',
        'status',
        'partner_id', // Nullable FK — only set when type is 'partner'
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ========================================================================
    // RELATIONSHIPS
    // ========================================================================

    /**
     * A vehicle belongs to many rides over its lifetime.
     */
    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    /**
     * WHAT: Partner relationship on the Vehicle model
     * WHY: When type is 'partner', this vehicle is linked to a specific partner.
     *      The relationship allows eager loading like Vehicle::with('partner')
     *      so the API can return full partner details alongside vehicle data.
     * HOW: Standard belongsTo — uses partner_id FK on the vehicles table.
     */
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    // ========================================================================
    // SCOPES
    // ========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }
}