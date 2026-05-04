<?php

namespace App\Repositories\Contracts;

use App\Models\AdSetting;
use Illuminate\Support\Collection;

interface AdSettingRepositoryInterface
{
    public function getForApp(int $appId): Collection;

    public function getForPlacement(int $appId, string $adType, string $placement): ?AdSetting;

    /** INSERT … ON DUPLICATE KEY UPDATE (upsert). */
    public function upsert(int $appId, string $adType, string $placement, array $data): AdSetting;

    public function delete(int $id): bool;
}
