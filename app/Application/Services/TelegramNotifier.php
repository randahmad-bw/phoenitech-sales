<?php

namespace App\Application\Services;

use App\Jobs\SendTelegramMessage;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\LeaveType;
use App\Models\WorkScheduleDay;
use Carbon\CarbonInterface;

/**
 * The company's Telegram group, told what just happened.
 *
 * Why a second channel at all: the in-app bell only reaches somebody who has
 * the app open. Management does not sit on the attendance screen, and the
 * whole point of a check-in notice is that it arrives while the day is still
 * happening — which is a phone's job, not a browser tab's.
 *
 * Three things this class is careful about:
 *
 * 1. **It is a mirror, never the record.** Nothing here writes to the
 *    database, and nothing here can refuse a check-in. If Telegram is
 *    unreachable the attendance row is still on file, and only the log knows.
 * 2. **The message is written in Arabic, here, once.** Unlike the in-app bell
 *    — which stores facts and lets each reader's screen build the sentence —
 *    this one has a single known audience reading a single language, so the
 *    sentence is finished before it leaves the system.
 * 3. **Times are resolved in the company timezone.** The database stores UTC;
 *    a manager reading "06:05" for a 09:05 arrival would not trust the next
 *    message either.
 */
class TelegramNotifier
{
    /** Somebody started their working day. */
    public const EVENT_CHECK_IN = 'attendance.check_in';

    /** Somebody finished it. */
    public const EVENT_CHECK_OUT = 'attendance.check_out';

    /** A leave request is waiting on a decision. */
    public const EVENT_LEAVE_REQUESTED = 'leave.requested';

    /**
     * "X checked in at 09:05."
     *
     * `$resumed` marks the evening return — a second sitting on a day that was
     * already closed — because "checked in" at 19:00 reads like a mistake
     * otherwise.
     */
    public function checkIn(Employee $employee, Attendance $attendance, bool $resumed = false): void
    {
        $lines = [
            $resumed
                ? '🔁 <b>'.self::escape($employee->name).'</b> — عودة للعمل'
                : '🟢 <b>'.self::escape($employee->name).'</b> — تسجيل حضور',
            '🕒 '.$this->time($attendance->check_in_at).$this->scheduledStart($attendance),
        ];

        if ($place = $this->place($attendance->location)) {
            $lines[] = '📍 '.$place;
        }

        // A day the schedule did not ask for. The hours are recorded all the
        // same, and management should see that they were.
        if ($attendance->isNonWorkingDay()) {
            $lines[] = '⚠️ خارج أيام الدوام';
        }

        $lines[] = '📅 '.$attendance->work_date->format('Y-m-d');

        $this->send(self::EVENT_CHECK_IN, $lines);
    }

    /**
     * "X checked out at 17:10, after 8:05."
     */
    public function checkOut(Employee $employee, Attendance $attendance): void
    {
        $this->send(self::EVENT_CHECK_OUT, [
            '🔴 <b>'.self::escape($employee->name).'</b> — تسجيل انصراف',
            '🕒 '.$this->time($attendance->check_out_at),
            '⏱ ساعات اليوم: '.$this->duration((int) $attendance->worked_minutes),
            '📅 '.$attendance->work_date->format('Y-m-d'),
        ]);
    }

    /**
     * "X asked for leave" — the same event the bell carries, on the phone.
     *
     * This one is worth a message even in a quiet group: a pending request
     * blocks the employee's planning until somebody decides it.
     */
    public function leaveRequested(Employee $employee, EmployeeLeave $leave): void
    {
        $lines = [
            '📅 <b>'.self::escape($employee->name).'</b> — طلب إجازة جديد',
            '🗓 من '.$leave->start_date->format('Y-m-d').' إلى '.$leave->end_date->format('Y-m-d'),
            '📊 '.$this->days((float) $leave->days_count).' · '.self::escape($this->leaveTypeName($leave->leave_type)),
        ];

        if ($reason = trim((string) $leave->reason)) {
            $lines[] = '📝 '.self::escape($reason);
        }

        $lines[] = '⏳ بانتظار قرار الإدارة';

        $this->send(self::EVENT_LEAVE_REQUESTED, $lines);
    }

    /**
     * Hand the message off, if this installation has a bot and wants this event.
     *
     * Dispatch happens outside the request by design — see SendTelegramMessage
     * for why an employee's check-in never waits on it.
     *
     * @param  list<string>  $lines
     */
    private function send(string $event, array $lines): void
    {
        if (! config('telegram.enabled') || ! config('telegram.token') || ! config('telegram.chat_id')) {
            return;
        }

        if (! config("telegram.events.{$event}", true)) {
            return;
        }

        $message = implode("\n", $lines);

        if (config('telegram.queue')) {
            SendTelegramMessage::dispatch($message);

            return;
        }

        SendTelegramMessage::dispatchAfterResponse($message);
    }

    /**
     * A stored UTC timestamp as the wall clock the company actually works by.
     */
    private function time(?CarbonInterface $at): string
    {
        return $at?->copy()->setTimezone(config('attendance.timezone'))->format('H:i') ?? '—';
    }

    /**
     * " (الدوام 09:00)" — the time the day was due to start, when there is one.
     *
     * Stated as a fact beside the arrival, never as a verdict: the attendance
     * module deliberately records times and leaves lateness to a human.
     */
    private function scheduledStart(Attendance $attendance): string
    {
        $start = $attendance->scheduled_start;

        if (! $start) {
            return '';
        }

        return ' (الدوام '.substr((string) $start, 0, 5).')';
    }

    private function place(?string $location): ?string
    {
        return match ($location) {
            WorkScheduleDay::LOCATION_OFFICE => 'المكتب',
            WorkScheduleDay::LOCATION_REMOTE => 'عن بُعد',
            default => null,
        };
    }

    /**
     * Minutes as h:mm — the same shape a clock has, in digits that read the
     * same in both languages.
     */
    private function duration(int $minutes): string
    {
        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function days(float $count): string
    {
        $value = floor($count) === $count ? (string) (int) $count : (string) $count;

        return match (true) {
            $count === 1.0 => 'يوم واحد',
            $count === 2.0 => 'يومان',
            default => $value.' أيام',
        };
    }

    /**
     * The catalogue's Arabic name, falling back to the stored key.
     *
     * A key is never shown as a word if it can be helped, but a type deleted
     * from the catalogue must not blank out the message about it.
     */
    private function leaveTypeName(string $key): string
    {
        return LeaveType::where('key', $key)->value('name_ar') ?? $key;
    }

    /**
     * Telegram parses the message as HTML, so a name with `&` or `<` in it
     * would break the message — or swallow it entirely.
     */
    private static function escape(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
