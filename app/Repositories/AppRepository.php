<?php

namespace App\Repositories;

use App\Models\App;
use App\Repositories\Contracts\AppRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AppRepository implements AppRepositoryInterface
{
    public function all(): Collection
    {
        return App::orderBy('created_at', 'desc')->get();
    }

    public function findById(int $id): ?App
    {
        return App::find($id);
    }

    public function findByApiKey(string $apiKey): ?App
    {
        // Use unique index — single row lookup, very fast
        return App::where('api_key', $apiKey)->first();
    }

    public function create(array $data): App
    {
        return App::create($data);
    }

    public function update(int $id, array $data): App
    {
        $app = App::findOrFail($id);
        $app->update($data);
        return $app->fresh();
    }

    public function delete(int $id): bool
    {
        return (bool) App::destroy($id);
    }

    public function generateUniqueApiKey(): string
    {
        do {
            // adniba_ prefix + 40 random hex chars = 46 chars total (well < 100 limit)
            $key = 'adniba_' . bin2hex(random_bytes(20));
        } while (App::where('api_key', $key)->exists());

        return $key;
    }
}
