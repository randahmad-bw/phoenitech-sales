<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validates a check-out.
 *
 * Separate from `PunchAttendanceRequest` for one reason: **the day has to
 * account for itself here.** Check-out is the one moment the employee is
 * certain to be at the screen and certain to remember, and management reads the
 * answer beside the hours — an empty one makes the hours unreadable.
 *
 * It may account for itself in either of two ways, and the rule is that one of
 * them must happen:
 *
 *  - **ticking the lines** that were on the day's tasks, which is the normal
 *    case and costs a few taps;
 *  - **writing it out**, for work nobody planned, or a day where the plan was
 *    not what happened.
 *
 * So `notes` is required only when nothing was ticked. An earlier version
 * demanded the paragraph every time; the objection to that was never that the
 * day should go unrecorded, it was that a person who has just ticked four lines
 * is being asked to type them again — and what that teaches is the full stop.
 */
class CheckOutAttendanceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'notes' => [
                Rule::requiredIf(fn (): bool => ! $this->filled('completed_item_ids')),
                'nullable', 'string', 'min:3', 'max:500',
            ],

            /*
             * Which checklist lines were finished. Not validated against the
             * table: the service only ticks lines on the caller's own open
             * tasks, so a stale id is ignored rather than fatal — and a day
             * should not be impossible to close because a manager deleted a
             * line while the dialog was open.
             */
            'completed_item_ids' => ['nullable', 'array', 'max:60'],
            'completed_item_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Tick what you finished, or say what you worked on, before closing the day.',
            'notes.min' => 'A few words at least — management reads this beside your hours.',
        ];
    }

    /**
     * The ticked line ids, as plain integers.
     *
     * @return array<int, int>
     */
    public function completedItemIds(): array
    {
        return array_map('intval', $this->validated('completed_item_ids') ?? []);
    }
}
