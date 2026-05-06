<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class AdminUserService
{
    /**
     * List all developers with app stats.
     */
    public function listUsers(array $filters = []): Collection
    {
        return User::withCount('apps')
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where('name', 'like', "%{$filters['search']}%")
                             ->orWhere('email', 'like', "%{$filters['search']}%")
            )
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get a single developer with full details.
     */
    public function getUser(int $id): User
    {
        return User::withCount('apps')
            ->with(['apps', 'limitRequests'])
            ->findOrFail($id);
    }

    /**
     * Activate or deactivate a developer account.
     */
    public function toggleStatus(int $id, bool $active, ?string $reason = null): User
    {
        $user = User::findOrFail($id);
        $user->update([
            'status' => $active,
            'deactivation_reason' => $active ? null : $reason,
            'deactivated_at' => $active ? null : now(),
        ]);

        return $user->fresh();
    }

    /**
     * Directly set app limit (without going through request flow).
     */
    public function setAppLimit(int $id, int $limit): User
    {
        if ($limit < 1) {
            throw new \RuntimeException('Limit must be at least 1.', 422);
        }

        $user = User::findOrFail($id);
        $user->update(['app_limit' => $limit]);

        return $user->fresh();
    }

    /**
     * Summary stats for admin dashboard.
     */
    public function getStats(): array
    {
        return [
            'total_users'    => User::count(),
            'active_users'   => User::where('status', 1)->count(),
            'inactive_users' => User::where('status', 0)->count(),
            'total_apps'     => \App\Models\App::whereNotNull('user_id')->count(),
        ];
    }
}
