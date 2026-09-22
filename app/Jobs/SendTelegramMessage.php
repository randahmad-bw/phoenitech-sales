<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One message to the company's Telegram group.
 *
 * The rule: **an employee's check-in must never wait on Telegram, and must
 * never fail because of it.** Two things enforce that.
 *
 * First, the work happens away from the request — after the response by
 * default (no queue worker needed), on the queue once one is running. Second,
 * nothing in here throws: a network that cannot reach api.telegram.org, an
 * expired token, a bot removed from the group — each is written to the log and
 * dropped. Attendance is recorded in the database either way, and the database
 * is what payroll reads.
 *
 * Retries only mean something on a real queue. Run after the response, the job
 * gets the one attempt the request can afford.
 */
class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(private string $text) {}

    public function handle(): void
    {
        $token = config('telegram.token');
        $chatId = config('telegram.chat_id');

        // Re-checked here rather than trusted from dispatch time: a queued job
        // may run minutes later, against a config that has since been emptied.
        if (! $token || ! $chatId) {
            return;
        }

        $request = Http::timeout((int) config('telegram.timeout', 5))
            ->acceptJson();

        if ($proxy = config('telegram.proxy')) {
            $request = $request->withOptions(['proxy' => $proxy]);
        }

        try {
            $response = $request->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $this->text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if ($response->failed()) {
                // Telegram explains itself in `description` — "chat not found",
                // "bot was kicked from the group chat" — and that sentence is
                // the whole diagnosis, so it goes in the log verbatim.
                Log::warning('Telegram rejected a message.', [
                    'status' => $response->status(),
                    'description' => $response->json('description'),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Telegram could not be reached.', ['error' => $e->getMessage()]);
        }
    }
}
