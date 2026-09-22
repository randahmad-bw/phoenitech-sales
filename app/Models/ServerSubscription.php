<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServerSubscription extends Model
{
    use Auditable, HasFactory;

    protected $table = 'server_subscriptions';

    protected $fillable = [
        'name',
        'company_name',
        'type',
        'domain',
        'provider',
        'cost',
        'currency',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cost' => 'decimal:2',
    ];

    /**
     * Scope for active subscriptions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for subscriptions expiring within given days (default 30).
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', '!=', 'cancelled')
            ->whereDate('end_date', '>=', Carbon::today())
            ->whereDate('end_date', '<=', Carbon::today()->addDays($days));
    }

    /**
     * Scope for expired subscriptions.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled')
            ->whereDate('end_date', '<', Carbon::today());
    }

    /**
     * Scope by service type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
