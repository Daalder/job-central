<?php

namespace Daalder\JobCentral\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * @var string
     */
    protected $namespace = "Daalder\JobCentral\Controllers";

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    public function map()
    {
        $middleware = hook('api-unauthenticated-middleware', ['api', 'api_log_request']);

        Route::middleware($middleware)
            ->namespace($this->namespace)
            ->prefix('job-central')
            ->group(__DIR__.'/../../routes/api.php');
    }
}
