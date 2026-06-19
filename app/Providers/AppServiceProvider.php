<?php

namespace App\Providers;

use App\Models\LeadInteraction;
use App\Models\LeadStageHistory;
use App\Policies\AppendOnlyPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Não seguem a convenção Model->ModelPolicy: registrados explicitamente.
        Gate::policy(LeadStageHistory::class, AppendOnlyPolicy::class);
        Gate::policy(LeadInteraction::class, AppendOnlyPolicy::class);
    }
}
