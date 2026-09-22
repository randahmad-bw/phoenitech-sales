# Telegram Notifications — Setup & Reference

> إشعارات تلغرام للإدارة. هذا المرجع يشرح كيفية إنشاء البوت وربطه بالمجموعة
> الإدارية وتشخيص الأعطال.
>
> Setup guide and reference for the Telegram channel. Keep it updated whenever
> `config/telegram.php` or the announced events change.

Last updated: 2026-09-22.

---

## 1. What it is

A one-way mirror of three events into a single management Telegram group:

| Event | Config key | When |
|---|---|---|
| Check-in | `attendance.check_in` | Every employee check-in, including an evening return to work |
| Check-out | `attendance.check_out` | Every check-out, with the hours worked that day |
| Leave request | `leave.requested` | A request is filed and is waiting on a decision |

It is a **mirror, never the record**. Attendance and leave are stored in the
database regardless of whether Telegram was reachable; a failed send is a line
in `storage/logs/laravel.log` and nothing more. The in-app notification bell is
unaffected and remains the system of record for leave requests.

The messages are written in Arabic — unlike the in-app bell, which stores facts
and lets each reader's screen build the sentence, this channel has one known
audience reading one language.

---

## 2. Setup

### 2.1 Create the bot

1. Open Telegram and message [@BotFather](https://t.me/BotFather).
2. Send `/newbot`, give it a name and a username ending in `bot`.
3. Copy the token it replies with. It looks like `123456789:AAF...`.

### 2.2 Create the group and add the bot

1. Create a Telegram group for management.
2. Add the bot to it.
3. Send any message in the group (Telegram does not reveal a chat until the bot
   has seen traffic in it).

### 2.3 Find the chat id

```bash
php artisan telegram:check --updates
```

It prints every chat the bot can see, with its id. A **group id is negative**
(`-1001234567890`); a private chat id is positive.

> If the bot is a member of a group but the table is empty, send another message
> in the group and run the command again. Telegram only keeps recent updates,
> and a bot added to a group with "privacy mode" on sees only messages addressed
> to it — `/start@yourbot` in the group is enough.

### 2.4 Fill in `.env`

```dotenv
TELEGRAM_NOTIFICATIONS_ENABLED=true
TELEGRAM_BOT_TOKEN=123456789:AAF...
TELEGRAM_CHAT_ID=-1001234567890
```

Then verify end to end:

```bash
php artisan config:clear
php artisan telegram:check --send
```

A test message should land in the group. If it does, the setup is done.

---

## 3. Configuration

All keys live in `config/telegram.php`, all are driven by `.env`.

| Key | Default | Purpose |
|---|---|---|
| `TELEGRAM_NOTIFICATIONS_ENABLED` | `false` | Master switch. Off means nothing ever contacts Telegram. |
| `TELEGRAM_BOT_TOKEN` | — | From @BotFather. |
| `TELEGRAM_CHAT_ID` | — | The management group (negative id). |
| `TELEGRAM_PROXY` | — | Guzzle proxy URL, only where the network blocks Telegram. |
| `TELEGRAM_TIMEOUT` | `5` | Seconds. Deliberately short. |
| `TELEGRAM_QUEUE` | `false` | `false` sends after the HTTP response, in-process; `true` puts it on the queue. |
| `TELEGRAM_NOTIFY_CHECK_IN` | `true` | Announce check-ins. |
| `TELEGRAM_NOTIFY_CHECK_OUT` | `true` | Announce check-outs. |
| `TELEGRAM_NOTIFY_LEAVE_REQUEST` | `true` | Announce leave requests. |

**Sending is never allowed to delay a check-in.** With `TELEGRAM_QUEUE=false`
(the default) the message is dispatched *after the response has been sent to the
employee*, in the same process — this needs no queue worker, which is why it is
the default. A failed send is logged and dropped; there is no retry.

Once a worker is running (`php artisan queue:work`, `QUEUE_CONNECTION=database`),
set `TELEGRAM_QUEUE=true`: the same job then goes on the queue, where a failure
is retried three times with a 30s / 120s backoff.

---

## 4. Turning down the noise

Check-in fires for every employee every working day. If the group becomes
unreadable, the cheapest fix needs no code change:

```dotenv
TELEGRAM_NOTIFY_CHECK_IN=false
TELEGRAM_NOTIFY_CHECK_OUT=false
```

Leave requests — the messages that actually need a decision — keep arriving.

---

## 5. Troubleshooting

Run `php artisan telegram:check` first; it reports the live configuration and
the exact reason Telegram refused.

| Symptom | Cause | Fix |
|---|---|---|
| `Could not reach api.telegram.org` | The server has no route to Telegram (blocked network, firewall). | Set `TELEGRAM_PROXY`. Verify from the server: `curl -sS https://api.telegram.org/bot<token>/getMe`. |
| `Telegram refused the token` | Wrong or revoked token. | Re-issue with `/token` in @BotFather. |
| `chat not found` | Wrong `TELEGRAM_CHAT_ID`, or the bot is not in that chat. | `--updates` and copy the id exactly, minus sign included. |
| `bot was kicked from the group chat` | The bot was removed. | Re-add it to the group. |
| Test message works, events do not | `TELEGRAM_NOTIFICATIONS_ENABLED=false`, or the per-event key is off. | Check both, then `php artisan config:clear`. |
| Nothing at all, no log line | Config cache holds the old values. | `php artisan config:clear` (and `config:cache` if production caches config). |

Messages are logged at `warning` level when they fail:
`grep -i telegram storage/logs/laravel.log`.

---

## 6. Where the code lives

| File | Role |
|---|---|
| `config/telegram.php` | Credentials, switches, delivery mode. |
| `app/Application/Services/TelegramNotifier.php` | Composes each message and decides whether to send it. |
| `app/Jobs/SendTelegramMessage.php` | The HTTP call. Never throws. |
| `app/Console/Commands/TelegramCheck.php` | `telegram:check` — setup and diagnosis. |
| `app/Application/Services/AttendanceService.php` | Calls the notifier after check-in / check-out commits. |
| `app/Application/Services/LeaveRequestService.php` | Calls it after a leave request is filed. |
| `tests/Feature/TelegramNotificationTest.php` | Feature tests, including the failure paths. |

Adding an event: add a key under `events` in `config/telegram.php`, a method on
`TelegramNotifier`, a call from the service that owns the action (**after** its
transaction commits), a line in `.env.example`, and a row in the table in §1.
