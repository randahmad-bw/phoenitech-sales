<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One audit entry, shaped for the trail UI.
 *
 * The actor is served from the snapshot columns when the account has since been
 * deleted, so an entry never loses the name of who performed it. `changes` is a
 * pre-computed field-by-field diff so the frontend does not have to align the
 * old and new maps itself.
 */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,

            'user' => $this->user_id || $this->user_name ? [
                'id' => $this->user_id,
                'name' => $this->user?->name ?? $this->user_name,
                'email' => $this->user?->email ?? $this->user_email,
                // False once the account has been deleted — the name above is
                // then the snapshot taken at the time of the action.
                'exists' => $this->user !== null,
            ] : null,

            'auditable' => $this->auditable_type ? [
                'type' => $this->type_alias,
                'id' => $this->auditable_id,
                'label' => $this->auditable_label,
            ] : null,

            'changes' => $this->diff(),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,

            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'url' => $this->url,
            'method' => $this->method,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Field-by-field diff across the union of the old and new maps.
     *
     * @return list<array{field: string, old: mixed, new: mixed}>
     */
    private function diff(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        $fields = array_keys($old + $new);

        return array_map(fn (string $field) => [
            'field' => $field,
            'old' => $old[$field] ?? null,
            'new' => $new[$field] ?? null,
        ], $fields);
    }
}
