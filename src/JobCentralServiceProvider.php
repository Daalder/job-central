<?php

namespace Daalder\JobCentral;

use App\Jobs\TestingJob;
use Daalder\JobCentral\Providers\QueueLoggingProvider;
use Daalder\JobCentral\Providers\RouteServiceProvider;
use Daalder\JobCentral\Testing\TestCommand;
use Daalder\JobCentral\Validators\JobCentralValidator;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Pionect\Daalder\Services\Cache\CacheRepository;
use Validator;

/**
 * Class JobCentralServiceProvider
 *
 * @package JobCentral
 */
class JobCentralServiceProvider extends ServiceProvider
{
    /**
     * @var CacheRepository
     */
    private $cacheRepository;

    public function __construct($app)
    {
        parent::__construct($app);
    }

    /**
     * Boot JobCentralServiceProvider
     */
    public function boot()
    {
        parent::boot();
        Validator::extend('empty_with', JobCentralValidator::class.'@validateEmptyWith');

        $this->publishes([
            __DIR__.'/../config/job-central.php' => config_path('job-central.php'),
        ]);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->commands([
            TestCommand::class,
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/job-central.php', 'job-central');

        $this->app->register(QueueLoggingProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }
}
