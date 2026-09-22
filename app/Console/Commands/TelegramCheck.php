<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Setting the bot up, and proving it works.
 *
 * Three things can go wrong between a fresh bot and a message landing in the
 * group, and each fails silently in production because nothing is allowed to
 * break a check-in over it: the server cannot reach api.telegram.org, the
 * token is wrong, or the chat id is not the group the bot is actually in.
 *
 * This command is where those failures are supposed to be discovered — out
 * loud, on the server, before anyone relies on the messages.
 *
 *   php artisan telegram:check             verify the token and the connection
 *   php artisan telegram:check --updates   list the chats the bot can see, with ids
 *   php artisan telegram:check --send      post a test message to the configured chat
 */
class TelegramCheck extends Command
{
    protected $signature = 'telegram:check {--updates : List recent chats and their ids} {--send : Send a test message to the configured chat}';

    protected $description = 'Verify the Telegram bot token, connection and chat id.';

    public function handle(): int
    {
        $token = config('telegram.token');

        $this->line('');
        $this->line('  enabled : '.(config('telegram.enabled') ? 'yes' : 'no'));
        $this->line('  token   : '.($token ? substr((string) $token, 0, 8).'…' : 'MISSING'));
        $this->line('  chat_id : '.(config('telegram.chat_id') ?: 'MISSING'));
        $this->line('  proxy   : '.(config('telegram.proxy') ?: 'none'));
        $this->line('  queue   : '.(config('telegram.queue') ? 'queued job' : 'after response'));
        $this->line('');

        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN is not set. Create a bot with @BotFather and put the token in .env.');

            return self::FAILURE;
        }

        $me = $this->call_('getMe');

        if ($me === null) {
            return self::FAILURE;
        }

        if (! ($me['ok'] ?? false)) {
            $this->error('Telegram refused the token: '.($me['description'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info('Connected as @'.($me['result']['username'] ?? '?'));

        if ($this->option('updates') && ! $this->showChats()) {
            return self::FAILURE;
        }

        if ($this->option('send') && ! $this->sendTest()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * The chats the bot has heard from, with the ids to put in TELEGRAM_CHAT_ID.
     *
     * Telegram only reveals a chat once the bot has seen a message in it, so
     * the instruction printed on an empty result is the actual fix rather than
     * an error.
     */
    private function showChats(): bool
    {
        $updates = $this->call_('getUpdates');

        if ($updates === null) {
            return false;
        }

        $chats = collect($updates['result'] ?? [])
            ->map(fn (array $update) => $update['message']['chat'] ?? $update['channel_post']['chat'] ?? null)
            ->filter()
            ->keyBy('id');

        if ($chats->isEmpty()) {
            $this->warn('No chats seen yet. Add the bot to the management group and send any message there, then run this again.');

            return true;
        }

        $this->line('');
        $this->table(['chat_id', 'type', 'name'], $chats->map(fn (array $chat) => [
            $chat['id'],
            $chat['type'] ?? '',
            $chat['title'] ?? trim(($chat['first_name'] ?? '').' '.($chat['last_name'] ?? '')),
        ])->values()->all());

        return true;
    }

    private function sendTest(): bool
    {
        $chatId = config('telegram.chat_id');

        if (! $chatId) {
            $this->error('TELEGRAM_CHAT_ID is not set. Run this command with --updates to find it.');

            return false;
        }

        $result = $this->call_('sendMessage', [
            'chat_id' => $chatId,
            'text' => "✅ <b>PhoeniTech</b> — رسالة اختبار\nالاتصال بتلغرام يعمل.",
            'parse_mode' => 'HTML',
        ]);

        if ($result === null) {
            return false;
        }

        if (! ($result['ok'] ?? false)) {
            $this->error('Telegram refused the message: '.($result['description'] ?? 'unknown error'));

            return false;
        }

        $this->info('Test message delivered.');

        return true;
    }

    /**
     * One Bot API call, with the connection error reported rather than thrown.
     *
     * Named with a trailing underscore because `call` is Command's own method
     * for running another artisan command.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function call_(string $method, array $payload = []): ?array
    {
        $request = Http::timeout((int) config('telegram.timeout', 5))->acceptJson();

        if ($proxy = config('telegram.proxy')) {
            $request = $request->withOptions(['proxy' => $proxy]);
        }

        try {
            return $request->post('https://api.telegram.org/bot'.config('telegram.token')."/{$method}", $payload)->json();
        } catch (Throwable $e) {
            $this->error('Could not reach api.telegram.org: '.$e->getMessage());
            $this->line('  If the network blocks Telegram, set TELEGRAM_PROXY in .env and try again.');

            return null;
        }
    }
}
