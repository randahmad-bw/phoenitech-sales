<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerSubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $endDate = $this->end_date ? Carbon::parse($this->end_date) : null;
        $isExpiringSoon = false;
        $isExpired = false;
        $daysUntilExpiration = null;

        if ($endDate) {
            $today = Carbon::today();
            $isExpired = $endDate->isPast();
            $diff = (int) $today->diffInDays($endDate, false);
            $daysUntilExpiration = $diff;
            $isExpiringSoon = !$isExpired && $diff <= 30;
        }

        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'company_name'           => $this->company_name,
            'type'                   => $this->type,
            'domain'                 => $this->domain,
            'provider'               => $this->provider,
            'cost'                   => (float) $this->cost,
            'currency'               => $this->currency ?? 'USD',
            'start_date'             => $this->start_date?->format('Y-m-d'),
            'end_date'               => $this->end_date?->format('Y-m-d'),
            'status'                 => $this->status,
            'notes'                  => $this->notes,
            'is_expiring_soon'       => $isExpiringSoon,
            'is_expired'             => $isExpired,
            'days_until_expiration'  => $daysUntilExpiration,
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
        ];
    }
}
