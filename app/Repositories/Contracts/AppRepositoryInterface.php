<?php

namespace App\Repositories\Contracts;

use App\Models\App;
use Illuminate\Support\Collection;

interface AppRepositoryInterface
{
    public function all(): Collection;

    public function findById(int $id): ?App;

    public function findByApiKey(string $apiKey): ?App;

    public function create(array $data): App;

    public function update(int $id, array $data): App;

    public function delete(int $id): bool;

    public function generateUniqueApiKey(): string;
}
