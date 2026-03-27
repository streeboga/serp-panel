<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Repositories\PositionAlertRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendPositionAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var int[] */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $alertId,
        public readonly int $keywordId,
        public readonly int $oldPosition,
        public readonly int $newPosition,
    ) {
        $this->onQueue('default');
    }

    public function handle(PositionAlertRepositoryInterface $alertRepository): void
    {
        $alert = $alertRepository->findById($this->alertId);

        if (! $alert->is_active) {
            return;
        }

        $alert->load('keyword');
        $keyword = $alert->keyword;

        if ($keyword === null) {
            Log::warning("Alert {$this->alertId}: keyword not found");

            return;
        }

        $message = $this->buildMessage($keyword->keyword);

        match ($alert->channel) {
            'telegram' => $this->sendTelegram($alert->recipient, $message),
            'email' => $this->sendEmail($alert->recipient, $keyword->keyword, $message),
            default => Log::warning("Unknown alert channel: {$alert->channel}"),
        };
    }

    private function buildMessage(string $keyword): string
    {
        $direction = $this->newPosition < $this->oldPosition ? '📈 Рост' : '📉 Падение';
        $oldLabel = $this->oldPosition > 100 ? 'вне TOP-100' : (string) $this->oldPosition;
        $newLabel = $this->newPosition > 100 ? 'вне TOP-100' : (string) $this->newPosition;

        return "{$direction} позиции\n\n"
            ."Ключевик: {$keyword}\n"
            ."Было: {$oldLabel}\n"
            ."Стало: {$newLabel}\n"
            .'Изменение: '.($this->oldPosition - $this->newPosition);
    }

    private function sendTelegram(string $chatId, string $message): void
    {
        $botToken = config('services.telegram.bot_token');

        if (empty($botToken)) {
            Log::warning('Telegram bot token not configured, skipping alert');

            return;
        }

        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        if (! $response->successful()) {
            Log::error('Telegram alert failed', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Telegram API error: '.$response->body());
        }
    }

    private function sendEmail(string $email, string $keyword, string $message): void
    {
        Mail::raw($message, function ($mail) use ($email, $keyword) {
            $mail->to($email)
                ->subject("SERP Panel: изменение позиции — {$keyword}");
        });
    }
}
