<?php

namespace App\Jobs;

use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public array $backoff = [30, 60, 120];
    public int $timeout = 60;

    public function __construct(
        private readonly int    $userId,
        private readonly string $toEmail,
        private readonly string $subject,
        private readonly string $type,
        private readonly array  $data  = [],
        private readonly ?int   $logId = null,
    ) {}

    public function handle(): void
    {
        try {
            // Build email body based on type
            $body = $this->buildBody();

            Mail::html($body, function ($message) {
                $message->to($this->toEmail)
                        ->subject($this->subject)
                        ->from(
                            config('mail.from.address', 'noreply@adniba.io'),
                            config('mail.from.name', 'AdNiba Platform')
                        );
            });

            // Update log status
            if ($this->logId) {
                EmailLog::where('id', $this->logId)->update([
                    'status'  => 'sent',
                    'sent_at' => now(),
                ]);
            }

        } catch (\Throwable $e) {
            if ($this->logId) {
                EmailLog::where('id', $this->logId)->update([
                    'status' => 'failed',
                    'error'  => $e->getMessage(),
                ]);
            }

            Log::channel('mail')->error('SendEmailJob failed', [
                'to'        => $this->toEmail,
                'subject'   => $this->subject,
                'exception' => $e->getMessage(),
            ]);

            throw $e; // Re-throw so queue retries
        }
    }

    // ─── Email Templates ──────────────────────────────────────────────────────

    private function buildBody(): string
    {
        $name = $this->data['name'] ?? 'Developer';

        return match ($this->type) {
            'limit_approved' => $this->limitApprovedTemplate($name),
            'limit_rejected' => $this->limitRejectedTemplate($name),
            'welcome'        => $this->welcomeTemplate($name),
            'announcement',
            'custom'         => $this->genericTemplate($name, $this->data['body'] ?? ''),
            default          => $this->genericTemplate($name, $this->data['body'] ?? ''),
        };
    }

    private function limitApprovedTemplate(string $name): string
    {
        $newLimit  = $this->data['new_limit'] ?? '—';
        $adminNote = $this->data['admin_note'] ? "<p><strong>Note:</strong> {$this->data['admin_note']}</p>" : '';

        return "
        <h2>Hello {$name},</h2>
        <p>Great news! Your app limit increase request has been <strong>approved</strong>.</p>
        <p>Your new app limit is: <strong>{$newLimit}</strong></p>
        {$adminNote}
        <p>You can now create more apps on the AdNiba platform.</p>
        <br><p>— AdNiba Team</p>";
    }

    private function limitRejectedTemplate(string $name): string
    {
        $adminNote = $this->data['admin_note'] ? "<p><strong>Reason:</strong> {$this->data['admin_note']}</p>" : '';

        return "
        <h2>Hello {$name},</h2>
        <p>Unfortunately, your app limit increase request has been <strong>declined</strong>.</p>
        {$adminNote}
        <p>You may submit a new request after addressing the concerns above.</p>
        <br><p>— AdNiba Team</p>";
    }

    private function welcomeTemplate(string $name): string
    {
        $limit = $this->data['app_limit'] ?? 3;

        return "
        <h2>Welcome to AdNiba, {$name}!</h2>
        <p>Your developer account has been created successfully.</p>
        <ul>
            <li>You can create up to <strong>{$limit} apps</strong></li>
            <li>Manage ad networks, units, and settings</li>
            <li>Track impressions, clicks, and revenue</li>
        </ul>
        <p>Get started by creating your first app!</p>
        <br><p>— AdNiba Team</p>";
    }

    private function genericTemplate(string $name, string $body): string
    {
        return "
        <h2>Hello {$name},</h2>
        {$body}
        <br><p>— AdNiba Team</p>";
    }
}
