<?php

namespace App\Providers;

use App\Repositories\AdEventRepository;
use App\Repositories\AdNetworkRepository;
use App\Repositories\AdSettingRepository;
use App\Repositories\AdUnitRepository;
use App\Repositories\AppRepository;
use App\Repositories\Contracts\AdEventRepositoryInterface;
use App\Repositories\Contracts\AdNetworkRepositoryInterface;
use App\Repositories\Contracts\AdSettingRepositoryInterface;
use App\Repositories\Contracts\AdUnitRepositoryInterface;
use App\Repositories\Contracts\AppRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All interface → implementation bindings.
     * Add new repositories here — no other file needs touching.
     */
    public array $bindings = [
        AppRepositoryInterface::class       => AppRepository::class,
        AdNetworkRepositoryInterface::class => AdNetworkRepository::class,
        AdUnitRepositoryInterface::class    => AdUnitRepository::class,
        AdSettingRepositoryInterface::class => AdSettingRepository::class,
        AdEventRepositoryInterface::class   => AdEventRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }
}
