<?php

namespace App\Application\Services;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Telling people what happened to their work.
 *
 * Three rules hold this together:
 *
 * 1. **Nobody is notified of their own action.** Every `push` takes the actor
 *    and drops the notice when it would land back on them. Doing this here
 *    rather than at each call site is the difference between one rule and one
 *    rule per feature that forgets it.
 * 2. **A notification never blocks the thing that caused it.** Sending is not
 *    allowed to fail a task assignment, so a recipient who cannot be resolved
 *    is simply not notified.
 * 3. **The text is not written here.** A type and its facts are stored; the
 *    reader's screen renders the sentence in their language.
 */
class NotificationService
{
    /**
     * A page of one person's feed, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Notification>
     */
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        return Notification::forUser($user->id)
            ->when(($filters['unread'] ?? null) === true, fn ($q) => $q->unread())
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * What the bell shows.
     *
     * The count is its own call because the bell asks for it on a timer while
     * the panel behind it is closed — fetching a page of rows every minute to
     * read one number off them would be the same answer at twenty times the
     * cost.
     */
    public function unreadCount(User $user): int
    {
        return Notification::forUser($user->id)->unread()->count();
    }

    /**
     * Mark one notice read. Idempotent: an already-read notice keeps its
     * original timestamp rather than jumping to now.
     */
    public function markRead(User $user, int $id): Notification
    {
        $notification = Notification::forUser($user->id)->findOrFail($id);

        if (! $notification->isRead()) {
            $notification->update(['read_at' => now()]);
        }

        return $notification;
    }

    /**
     * Clear the bell. Returns how many were still unread.
     */
    public function markAllRead(User $user): int
    {
        return Notification::forUser($user->id)->unread()->update(['read_at' => now()]);
    }

    /**
     * Write one notice.
     *
     * Returns null — rather than throwing — when there is nobody to tell or
     * when the recipient is the person who caused it.
     *
     * @param  array<string, mixed>  $data
     */
    public function push(
        ?User $recipient,
        string $type,
        array $data = [],
        ?string $link = null,
        ?Model $subject = null,
        ?User $actor = null,
    ): ?Notification {
        if ($recipient === null) {
            return null;
        }

        if ($actor !== null && $actor->id === $recipient->id) {
            return null;
        }

        return Notification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'data' => $data,
            'link' => $link,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
        ]);
    }

    /**
     * Notify the person behind an employee record.
     *
     * Most of the system assigns work to an *employee*, while notifications
     * are read by a *user*. Employees without a login simply go untold, which
     * is the correct outcome rather than an error: the work is still assigned.
     *
     * @param  array<string, mixed>  $data
     */
    public function pushToEmployee(
        ?int $employeeId,
        string $type,
        array $data = [],
        ?string $link = null,
        ?Model $subject = null,
        ?User $actor = null,
    ): ?Notification {
        if (! $employeeId) {
            return null;
        }

        $user = Employee::with('user')->find($employeeId)?->user;

        return $this->push($user, $type, $data, $link, $subject, $actor);
    }

    /**
     * Tell everyone who can act on this.
     *
     * Some events are not addressed to a person but to a job: a leave request
     * is for "management", which is whoever currently holds the permission to
     * decide one. Resolving that here means a role change moves the notice
     * with it, and no feature has to carry a list of role names.
     *
     * `super_admin` is matched by name on purpose. It passes every check
     * through the gate bypass in AppServiceProvider rather than by holding the
     * permission row, so a permission-only query would leave the one account
     * that can always act on this uninformed.
     *
     * Inactive accounts are skipped: they cannot sign in to read the notice,
     * and an unread count nobody will ever clear is not a reminder.
     *
     * @param  array<string, mixed>  $data
     * @return int how many people were told
     */
    public function pushToPermission(
        string $permission,
        string $type,
        array $data = [],
        ?string $link = null,
        ?Model $subject = null,
        ?User $actor = null,
    ): int {
        $recipients = User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($permission): void {
                $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'super_admin'))
                    ->orWhereHas('roles.permissions', fn (Builder $perms) => $perms->where('name', $permission))
                    ->orWhereHas('permissions', fn (Builder $perms) => $perms->where('name', $permission));
            })
            ->get();

        $sent = 0;

        foreach ($recipients as $recipient) {
            if ($this->push($recipient, $type, $data, $link, $subject, $actor) !== null) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Drop every notice about a record that is being deleted.
     *
     * A notification outlives its subject otherwise: the bell keeps offering
     * "a task was assigned to you" and the link behind it 404s. Nothing in the
     * database can do this for us — the subject is polymorphic, so there is no
     * foreign key to cascade.
     */
    public function purgeSubject(Model $subject): int
    {
        return Notification::where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->delete();
    }

    /**
     * Write a notice unless the same one already went out today.
     *
     * This is what makes a daily reminder job safe to run twice: "your task is
     * overdue" is one message a day, not one per run.
     *
     * @param  array<string, mixed>  $data
     */
    public function pushOncePerDay(
        ?int $employeeId,
        string $type,
        array $data = [],
        ?string $link = null,
        ?Model $subject = null,
    ): ?Notification {
        if (! $employeeId || $subject === null) {
            return null;
        }

        $alreadySent = Notification::where('type', $type)
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->whereDate('created_at', now()->toDateString())
            ->exists();

        if ($alreadySent) {
            return null;
        }

        return $this->pushToEmployee($employeeId, $type, $data, $link, $subject);
    }
}
