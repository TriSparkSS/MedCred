<?php

namespace App\Providers;

use App\Repositories\Contracts\CredentialingCaseRepositoryInterface;
use App\Repositories\Contracts\ProviderRepositoryInterface;
use App\Repositories\Eloquent\CredentialingCaseRepository;
use App\Repositories\Eloquent\ProviderRepository;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProviderRepositoryInterface::class, ProviderRepository::class);
        $this->app->bind(CredentialingCaseRepositoryInterface::class, CredentialingCaseRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
