<?php

namespace App\Services;

use App\Models\AdNetwork;
use App\Repositories\Contracts\AdNetworkRepositoryInterface;
use Illuminate\Support\Collection;

class AdNetworkService
{
    public function __construct(
        private readonly AdNetworkRepositoryInterface $networks
    ) {}

    public function getForApp(int $appId): Collection
    {
        return $this->networks->getForApp($appId);
    }

    public function getActiveNetworksWithPriority(int $appId): Collection
    {
        return $this->networks->getActiveNetworksWithPriority($appId);
    }

    public function createNetwork(int $appId, array $data): AdNetwork
    {
        $data['app_id'] = $appId;

        // Validate supported network slug
        if (!in_array($data['name'], AdNetwork::SUPPORTED, true)) {
            throw new \InvalidArgumentException(
                "Unsupported network '{$data['name']}'. Supported: " .
                implode(', ', AdNetwork::SUPPORTED)
            );
        }

        // Prevent duplicate
        if ($this->networks->findByAppAndName($appId, $data['name'])) {
            throw new \RuntimeException(
                "Network '{$data['name']}' already registered for this app.", 409
            );
        }

        // map name -> global_id
        $global = \DB::table('global_ad_networks')->where('name', $data['name'])->first();
        if (!$global) {
            throw new \RuntimeException("Global network '{$data['name']}' not found.", 404);
        }

        $data['global_id'] = $global->id;
        unset($data['name']);

        return $this->networks->create($data);
    }

    public function updateNetwork(int $id, array $data): AdNetwork
    {
        return $this->networks->update($id, $data);
    }

    public function toggleNetwork(int $id, bool $enabled): AdNetwork
    {
        return $this->networks->update($id, ['enabled' => $enabled]);
    }

    public function deleteNetwork(int $id): bool
    {
        return $this->networks->delete($id);
    }

    public function findById(int $id): ?AdNetwork
    {
        return $this->networks->findById($id);
    }
}
