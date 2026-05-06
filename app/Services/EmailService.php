<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\App;
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

        SendEmailJob::dispatchSync(
            userId:  $user->id,
            toEmail: $user->email,
            subject: 'Welcome to AdNiba!',
            type:    EmailLog::TYPE_WELCOME,
            data:    ['name' => $user->name, 'app_limit' => $user->app_limit],
            logId:   $log->id
        );
    }

    /**
     * Send email verification code to a developer.
     */
    public function sendVerification(User $user, string $code): void
    {
        $log = EmailLog::create([
            'user_id'  => $user->id,
            'to_email' => $user->email,
            'subject'  => 'Verify Your AdNiba Email',
            'type'     => EmailLog::TYPE_EMAIL_VERIFICATION,
            'status'   => 'queued',
        ]);

        SendEmailJob::dispatchSync(
            userId:  $user->id,
            toEmail: $user->email,
            subject: 'Verify Your AdNiba Email',
            type:    EmailLog::TYPE_EMAIL_VERIFICATION,
            data:    ['name' => $user->name, 'code' => $code],
            logId:   $log->id
        );
    }

    /**
     * Send password reset instructions to a developer.
     */
    public function sendPasswordReset(User $user, string $token): void
    {
        $log = EmailLog::create([
            'user_id'  => $user->id,
            'to_email' => $user->email,
            'subject'  => 'Reset Your AdNiba Password',
            'type'     => EmailLog::TYPE_PASSWORD_RESET,
            'status'   => 'queued',
        ]);

        SendEmailJob::dispatchSync(
            userId:  $user->id,
            toEmail: $user->email,
            subject: 'Reset Your AdNiba Password',
            type:    EmailLog::TYPE_PASSWORD_RESET,
            data:    [
                'name' => $user->name,
                'token' => $token,
                'reset_url' => $this->buildPasswordResetUrl($user, $token),
            ],
            logId:   $log->id
        );
    }

    /**
     * Notify a developer after their password has been reset.
     */
    public function sendPasswordResetSuccess(User $user): void
    {
        $log = EmailLog::create([
            'user_id'  => $user->id,
            'to_email' => $user->email,
            'subject'  => 'Your AdNiba Password Was Reset',
            'type'     => EmailLog::TYPE_PASSWORD_RESET_SUCCESS,
            'status'   => 'queued',
        ]);

        SendEmailJob::dispatchSync(
            userId:  $user->id,
            toEmail: $user->email,
            subject: 'Your AdNiba Password Was Reset',
            type:    EmailLog::TYPE_PASSWORD_RESET_SUCCESS,
            data:    ['name' => $user->name],
            logId:   $log->id
        );
    }

    public function sendAccountStatusChanged(User $user, bool $active, ?string $reason = null): void
    {
        $subject = $active
            ? 'Your AdNiba Account Has Been Activated'
            : 'Your AdNiba Account Has Been Deactivated';

        $safeReason = e($reason ?? $user->deactivation_reason ?? 'Not specified');
        $body = $active
            ? '<p>Your AdNiba developer account has been activated. You can now log in and use your account.</p>'
            : "<p>Your AdNiba developer account has been deactivated by an administrator. You will not be able to log in until it is activated again.</p><p><strong>Reason:</strong> {$safeReason}</p>";

        $this->sendImmediateCustom($user, $subject, $body);
    }

    public function sendAppStatusChanged(App $app): void
    {
        if (!$app->user) {
            return;
        }

        $appName = e($app->name);
        $packageName = e($app->package_name);
        $active = $app->status === App::STATUS_ACTIVE;
        $subject = $active
            ? "Your app \"{$app->name}\" has been activated"
            : "Your app \"{$app->name}\" has been deactivated";
        $body = $active
            ? "<p>Your app <strong>{$appName}</strong> ({$packageName}) has been activated and can now serve SDK requests.</p>"
            : "<p>Your app <strong>{$appName}</strong> ({$packageName}) has been deactivated and will not serve SDK requests until it is activated again.</p>";

        $this->sendImmediateCustom($app->user, $subject, $body);
    }

    public function sendAppSuspended(App $app, string $reason): void
    {
        if (!$app->user) {
            return;
        }

        $appName = e($app->name);
        $packageName = e($app->package_name);
        $safeReason = e($reason);

        $this->sendImmediateCustom(
            $app->user,
            "Your app \"{$app->name}\" has been suspended",
            "
            <h2>App Suspended</h2>
            <p>Your app <strong>{$appName}</strong> ({$packageName}) has been suspended.</p>
            <p><strong>Reason:</strong> {$safeReason}</p>
            <p>Please contact support if you believe this is a mistake.</p>
            "
        );
    }

    public function sendAppReactivated(App $app): void
    {
        if (!$app->user) {
            return;
        }

        $appName = e($app->name);
        $packageName = e($app->package_name);

        $this->sendImmediateCustom(
            $app->user,
            "Your app \"{$app->name}\" has been reactivated",
            "
            <h2>App Reactivated</h2>
            <p>Your app <strong>{$appName}</strong> ({$packageName}) has been reactivated and is now live.</p>
            "
        );
    }

    private function buildPasswordResetUrl(User $user, string $token): ?string
    {
        $baseUrl = config('app.password_reset_url') ?: rtrim(config('app.url'), '/') . '/reset-password';

        if (!$baseUrl) {
            return null;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query([
            'email' => $user->email,
            'token' => $token,
        ]);
    }

    private function sendImmediateCustom(User $user, string $subject, string $body): void
    {
        $log = EmailLog::create([
            'user_id'  => $user->id,
            'to_email' => $user->email,
            'subject'  => $subject,
            'type'     => EmailLog::TYPE_CUSTOM,
            'status'   => 'queued',
        ]);

        SendEmailJob::dispatchSync(
            userId:  $user->id,
            toEmail: $user->email,
            subject: $subject,
            type:    EmailLog::TYPE_CUSTOM,
            data:    ['body' => $body, 'name' => $user->name],
            logId:   $log->id
        );
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
