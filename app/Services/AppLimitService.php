<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\AppLimitRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class AppLimitService
{
    /**
     * Developer submits a limit increase request.
     */
    public function submitRequest(User $user, int $requestedLimit, ?string $reason = null): AppLimitRequest
    {
        if ($requestedLimit <= $user->app_limit) {
            throw new \RuntimeException(
                "Requested limit ({$requestedLimit}) must be greater than current limit ({$user->app_limit}).", 422
            );
        }

        if ($user->hasPendingLimitRequest()) {
            throw new \RuntimeException('You already have a pending limit request.', 409);
        }

        return AppLimitRequest::create([
            'user_id'         => $user->id,
            'requested_limit' => $requestedLimit,
            'reason'          => $reason,
            'status'          => AppLimitRequest::STATUS_PENDING,
        ]);
    }

    /**
     * Admin: list all pending requests.
     */
    public function getPendingRequests(): Collection
    {
        return AppLimitRequest::with('user')
            ->where('status', AppLimitRequest::STATUS_PENDING)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Admin: list all requests (with filters).
     */
    public function getAllRequests(?string $status = null): Collection
    {
        return AppLimitRequest::with(['user', 'reviewer'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Admin: approve a request — updates user's app_limit.
     */
    public function approve(int $requestId, int $adminId, ?string $note = null): AppLimitRequest
    {
        $request = AppLimitRequest::with('user')->findOrFail($requestId);

        if (!$request->isPending()) {
            throw new \RuntimeException('Request is already reviewed.', 409);
        }

        $request->update([
            'status'      => AppLimitRequest::STATUS_APPROVED,
            'reviewed_by' => $adminId,
            'admin_note'  => $note,
            'reviewed_at' => now(),
        ]);

        // Calculate the new limit (previous limit + requested limit)
        $newLimit = $request->user->app_limit + $request->requested_limit;

        // Update the user's actual limit
        $request->user->update(['app_limit' => $newLimit]);

        // Send notification email async
        SendEmailJob::dispatch(
            userId:  $request->user_id,
            toEmail: $request->user->email,
            subject: 'Your App Limit Request Has Been Approved',
            type:    'limit_approved',
            data:    [
                'name'            => $request->user->name,
                'new_limit'       => $newLimit,
                'admin_note'      => $note,
            ]
        )->onQueue('default');

        return $request->fresh(['user', 'reviewer']);
    }

    /**
     * Admin: reject a request.
     */
    public function reject(int $requestId, int $adminId, ?string $note = null): AppLimitRequest
    {
        $request = AppLimitRequest::with('user')->findOrFail($requestId);

        if (!$request->isPending()) {
            throw new \RuntimeException('Request is already reviewed.', 409);
        }

        $request->update([
            'status'      => AppLimitRequest::STATUS_REJECTED,
            'reviewed_by' => $adminId,
            'admin_note'  => $note,
            'reviewed_at' => now(),
        ]);

        // Send notification email async
        SendEmailJob::dispatch(
            userId:  $request->user_id,
            toEmail: $request->user->email,
            subject: 'Your App Limit Request Has Been Reviewed',
            type:    'limit_rejected',
            data:    [
                'name'       => $request->user->name,
                'admin_note' => $note,
            ]
        )->onQueue('default');

        return $request->fresh(['user', 'reviewer']);
    }

    /**
     * Admin: directly set a user's app limit without a request.
     */
    public function setLimit(User $user, int $newLimit): User
    {
        if ($newLimit < 1) {
            throw new \RuntimeException('App limit must be at least 1.', 422);
        }

        $user->update(['app_limit' => $newLimit]);

        return $user->fresh();
    }
}
