<?php

namespace App\Application\Services;

use App\Application\Support\LeaveDays;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\LeaveType;
use App\Models\Notification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leave requests and the decisions on them.
 *
 * The rule this service exists to enforce: **submitting and approving are two
 * different acts by two different people.** A request always enters as
 * `pending`, whatever the client sends, and only `review()` can move it — a
 * method the route puts behind `leave.approve`.
 *
 * That separation is not bureaucracy. Approved leave turns a day on the
 * attendance calendar from an absence into `leave`, so an employee able to
 * approve their own could erase their own absences.
 */
class LeaveRequestService
{
    public function __construct(
        private LeaveDays $days,
        private NotificationService $notifications,
        private TelegramNotifier $telegram,
    ) {}

    /**
     * Submit a request. It is always pending — never approved on arrival.
     *
     * @param  array<string, mixed>  $data
     */
    public function request(Employee $employee, array $data, ?int $actorId = null): EmployeeLeave
    {
        $from = CarbonImmutable::parse($data['start_date'])->startOfDay();
        $to = CarbonImmutable::parse($data['end_date'])->startOfDay();

        if ($to->lt($from)) {
            throw new BusinessRuleException(
                'A leave request cannot end before it starts.',
                'INVALID_LEAVE_RANGE'
            );
        }

        $leave = DB::transaction(function () use ($employee, $data, $from, $to): EmployeeLeave {
            $this->guardOverlap($employee, $from, $to);

            return EmployeeLeave::create([
                'employee_id' => $employee->id,
                'leave_type' => $data['leave_type'],
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
                // Counted from the employee's own working week, so a request
                // spanning a weekend does not spend leave on days off.
                'days_count' => $this->days->between($employee, $from, $to),
                'status' => EmployeeLeave::STATUS_PENDING,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'approved_by' => null,
                'decided_at' => null,
                'decision_note' => null,
            ]);
        });

        $this->announce($employee, $leave, $actorId);

        return $leave;
    }

    /**
     * Tell whoever decides leave that something is waiting for them.
     *
     * Outside the transaction: a request that is on file is on file, and a
     * notification failing to be written is not a reason to refuse it.
     *
     * The sidebar badge already carries the pending count, but only while
     * somebody is looking at the app - this is what reaches a manager who is
     * on another screen, and what leaves a record of when it arrived.
     */
    private function announce(Employee $employee, EmployeeLeave $leave, ?int $actorId): void
    {
        $actor = $actorId !== null ? User::find($actorId) : $employee->user;

        $this->notifications->pushToPermission(
            'leave.approve',
            Notification::TYPE_LEAVE_REQUESTED,
            [
                'actor' => $employee->name,
                'from' => $leave->start_date->format('Y-m-d'),
                'to' => $leave->end_date->format('Y-m-d'),
                'days' => (float) $leave->days_count,
            ],
            // The inbox tab, not the screen's default: a manager who also
            // requests leave lands on their own tab otherwise, one click away
            // from the request they were just told about.
            '/leaves?tab=inbox',
            $leave,
            $actor,
        );

        // And on the phone, for the same reason the bell exists at all: a
        // pending request holds up the employee's planning until somebody
        // decides it, and nobody sits on the leave screen waiting.
        $this->telegram->leaveRequested($employee, $leave);
    }

    /**
     * Approve or reject a request.
     *
     * @param  array<string, mixed>  $data
     */
    public function review(EmployeeLeave $leave, array $data, int $actorId): EmployeeLeave
    {
        if (! $leave->isPending()) {
            throw new BusinessRuleException(
                'This request has already been '.$leave->status.'.',
                'LEAVE_ALREADY_DECIDED'
            );
        }

        $leave->update([
            'status' => $data['status'],
            'approved_by' => $actorId,
            'decided_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        return $leave->fresh(['employee', 'approver']);
    }

    /**
     * Withdraw a request that has not been decided yet.
     *
     * An approved leave is not withdrawn by the employee — the attendance
     * calendar already reflects it, so management unmakes that decision.
     */
    public function cancel(EmployeeLeave $leave): void
    {
        if (! $leave->isPending()) {
            throw new BusinessRuleException(
                'Only a request still awaiting a decision can be withdrawn. Ask management to change a decided one.',
                'LEAVE_ALREADY_DECIDED'
            );
        }

        // The notice sent to management goes with it. A withdrawn request still
        // sitting in somebody's bell is a decision they can no longer make.
        $this->notifications->purgeSubject($leave);

        $leave->delete();
    }

    /**
     * One employee's requests, newest first.
     *
     * @return Collection<int, EmployeeLeave>
     */
    public function forEmployee(Employee $employee): Collection
    {
        return EmployeeLeave::where('employee_id', $employee->id)
            ->with('approver')
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Requests across the company, optionally filtered.
     *
     * @param  array{status?: string|null, employee_id?: int|null}  $filters
     * @return Collection<int, EmployeeLeave>
     */
    public function list(array $filters = []): Collection
    {
        return EmployeeLeave::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->with(['employee', 'approver'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * How many requests are waiting for a decision, company-wide.
     *
     * Its own method rather than `count(list(['status' => 'pending']))`
     * because the sidebar asks for this on a timer: loading every pending row
     * with its employee and approver to read one number off them is the same
     * answer at many times the cost.
     */
    public function pendingCount(): int
    {
        return EmployeeLeave::where('status', EmployeeLeave::STATUS_PENDING)->count();
    }

    /**
     * The employee's balance.
     *
     * Only approved leave is spent. A pending request is shown separately so
     * someone can see what they have asked for without it being deducted yet.
     *
     * Which types come off the allowance is no longer the word `annual` in a
     * query - it is `deducts_from_allowance` on the type, so a company that
     * renames its yearly leave, or marks a second type as drawing on the same
     * balance, gets the right number without a code change. `by_type` carries
     * the rest, so no screen has to know the catalogue to display it.
     *
     * @return array<string, mixed>
     */
    public function balance(Employee $employee): array
    {
        $leaves = EmployeeLeave::where('employee_id', $employee->id)->get();
        $allowance = (int) ($employee->annual_leave_allowance ?? 21);

        $approved = $leaves->where('status', EmployeeLeave::STATUS_APPROVED);
        $pending = $leaves->where('status', EmployeeLeave::STATUS_PENDING);

        $deducting = LeaveType::deductingKeys();
        $used = (float) $approved->whereIn('leave_type', $deducting)->sum('days_count');

        return [
            'annual_allowance' => $allowance,
            'annual_used' => $used,
            'annual_pending' => (float) $pending->whereIn('leave_type', $deducting)->sum('days_count'),
            'annual_remaining' => max(0, $allowance - $used),
            'pending_count' => $pending->count(),

            // Approved days per type, catalogue order. Switched-off types are
            // included when they still carry history: hiding a type from the
            // request form is not the same as erasing the leave taken under it.
            'by_type' => LeaveType::query()->ordered()->get()
                ->map(fn (LeaveType $type): array => [
                    'key' => $type->key,
                    'name_ar' => $type->name_ar,
                    'name_en' => $type->name_en,
                    'is_active' => $type->is_active,
                    'deducts_from_allowance' => $type->deducts_from_allowance,
                    'days' => (float) $approved->where('leave_type', $type->key)->sum('days_count'),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Refuse a request that overlaps one already on file.
     *
     * A rejected request is not in the way — only a pending or approved one is.
     */
    private function guardOverlap(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $clash = EmployeeLeave::where('employee_id', $employee->id)
            ->whereIn('status', [EmployeeLeave::STATUS_PENDING, EmployeeLeave::STATUS_APPROVED])
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->first();

        if ($clash) {
            throw new BusinessRuleException(
                'This overlaps an existing request from '.$clash->start_date->format('Y-m-d').' to '.$clash->end_date->format('Y-m-d').'.',
                'LEAVE_OVERLAP'
            );
        }
    }
}
