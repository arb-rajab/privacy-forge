<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * The PolicyEvaluator (ADR-0001) is deliberately NOT bound here as a
     * singleton yet — that wiring is Session 6's job, once the
     * PolicyDefinition model and the sensitive-action registry actually
     * exist. Session 5 is environment-only; this provider intentionally
     * has nothing business-specific in it yet.
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
        //
    }
}
