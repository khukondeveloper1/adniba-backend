<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Support\Collection;

class EmailService
{
    /**
     * Admin sends a custom email to a single user.
     */
    public function sendToUser(User $user, string $subject, string $body): EmailLog
    {
        $log = EmailLog::create([
            'user_id'  => $user->id,
            'to_email' => $user->email,
            'subject'  => $subject,
            'type'     => EmailLog::TYPE_CUSTOM,
            'status'   => 'queued',
        ]);

        SendEmailJob::dispatch(
            userId:  $user->id,
            toEmail: $user->email,
            subject: $subject,
            type:    EmailLog::TYPE_CUSTOM,
            data:    ['body' => $body, 'name' => $user->name],
            logId:   $log->id
        )->onQueue('default');

        return $log;
    }

    /**
     * Admin broadcasts an announcement to ALL active users.
     * Each email is dispatched as a separate queued job.
     */
    public function broadcast(string $subject, string $body): int
    {
        $users = User::where('status', 1)->get();
        $count = 0;

        foreach ($users as $user) {
            $log = EmailLog::create([
                'user_id'  => $user->id,
                'to_email' => $user->email,
                'subject'  => $subject,
                'type'     => EmailLog::TYPE_ANNOUNCEMENT,
                'status'   => 'queued',
            ]);

            SendEmailJob::dispatch(
                userId:  $user->id,
                toEmail: $user->email,
                subject: $subject,
                type:    EmailLog::TYPE_ANNOUNCEMENT,
                data:    ['body' => $body, 'name' => $user->name],
                logId:   $log->id
            )->onQueue('default');

            $count++;
        }

        return $count;
    }

    /**
     * Send a welcome email to a new developer (called from register).
     */
    public function sendWelcome(User $user): void
    {
        $log = EmailLog::create([
            'user_id'  => $user->id,
            'to_email' => $user->email,
            'subject'  => 'Welcome to AdNiba!',
            'type'     => EmailLog::TYPE_WELCOME,
            'status'   => 'queued',
        ]);

        SendEmailJob::dispatch(
            userId:  $user->id,
            toEmail: $user->email,
            subject: 'Welcome to AdNiba!',
            type:    EmailLog::TYPE_WELCOME,
            data:    ['name' => $user->name, 'app_limit' => $user->app_limit],
            logId:   $log->id
        )->onQueue('default');
    }

    /**
     * Email history for admin.
     */
    public function getLogs(?int $userId = null): Collection
    {
        return EmailLog::with('user')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();
    }
}
