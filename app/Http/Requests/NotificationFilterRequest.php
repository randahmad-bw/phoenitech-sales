<?php

namespace App\Http\Requests;

/**
 * Validates the notification feed's query string.
 */
class NotificationFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'unread' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string', 'max:64'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * The filters as the service expects them.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = array_filter(
            $this->safe()->only(['type', 'per_page']),
            fn ($value) => $value !== null && $value !== ''
        );

        if ($this->boolean('unread')) {
            $filters['unread'] = true;
        }

        return $filters;
    }
}
