<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Binds repository interfaces to their Eloquent implementations.
 * Register new domain repository bindings here.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository interface-to-implementation bindings.
     * Add new entries as domain repositories are created.
     */
    protected array $repositories = [
        // \App\Infrastructure\Repositories\Contracts\EmployeeRepositoryInterface::class => \App\Infrastructure\Repositories\Eloquent\EloquentEmployeeRepository::class,
        // \App\Infrastructure\Repositories\Contracts\CompanyRepositoryInterface::class => \App\Infrastructure\Repositories\Eloquent\EloquentCompanyRepository::class,
        // \App\Infrastructure\Repositories\Contracts\ContractRepositoryInterface::class => \App\Infrastructure\Repositories\Eloquent\EloquentContractRepository::class,
        // \App\Infrastructure\Repositories\Contracts\PaymentRepositoryInterface::class => \App\Infrastructure\Repositories\Eloquent\EloquentPaymentRepository::class,
    ];

    /**
     * Register repository bindings into the service container.
     */
    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}
