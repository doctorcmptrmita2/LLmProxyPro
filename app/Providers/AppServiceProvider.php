<?php

namespace App\Providers;

use App\Services\Llm\GatewayService;
use App\Services\Llm\LiteLlmClient;
use App\Services\Llm\LlmRouter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LiteLlmClient::class, function () {
            return new LiteLlmClient();
        });

        $this->app->singleton(LlmRouter::class, function () {
            return new LlmRouter();
        });

        $this->app->singleton(GatewayService::class, function ($app) {
            return new GatewayService(
                $app->make(LiteLlmClient::class),
                $app->make(LlmRouter::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
