<?php

namespace Daalder\JobCentral\Tests;

use Astrotomic\Translatable\TranslatableServiceProvider;
use Daalder\JobCentral\JobCentralServiceProvider;
use Daalder\JobCentral\Models\JCJob;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\File;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Database\ConsoleServiceProvider;
use Pionect\Daalder\DaalderServiceProvider;
use Pionect\Daalder\ServiceProviders\ElasticScoutConfigServiceProvider;
use Pionect\Daalder\Tests\TestCase as DaalderTestCase;
use ScoutElastic\ScoutElasticServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends DaalderTestCase
{
    protected function refreshTestDatabase()
    {
        $locale = app()->getLocale();

        if (!RefreshDatabaseState::$migrated) {
            $this->artisan('vendor:publish', [
                '--provider' => PermissionServiceProvider::class
            ]);

            $this->artisan('migrate:fresh', [
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
            ]);

            $this->artisan('db:seed');
            // Only (re-)create indexes
            $this->artisan('elastic:sync --drop --create --only');
            // Make sure the jc_job ES index is created properly
            JCJob::factory()->count(1)->create();
            // Do full ES sync now
            $this->artisan('elastic:sync');

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        // The locale is modified in the artisan(migrate:fresh) command. Change it back.
        app()->setLocale($locale);

        $this->beginDatabaseTransaction();
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('searchable.models', [
            'jcJob' => \Daalder\JobCentral\Models\JCJob::class,
        ]);
        
        foreach (File::files(__DIR__ . '/../vendor/pionect/daalder/config') as $config) {
            if ($config->getExtension() == 'php') {
                $key = str_replace('.php', '', $config->getFilename());
                $default = config()->get($key, []);
                config()->set($key, array_merge($default, require $config->getRealPath()));
            }
        }

        $orchestra = __DIR__ . '/../vendor/orchestra/testbench-core/laravel';
        $migrationDirectory = realpath(__DIR__ . '/../vendor/pionect/daalder/database/migrations');
        $migrations = array_diff(scandir($migrationDirectory), ['..', '.']);
        foreach ($migrations as $migration) {
            copy($migrationDirectory . '/' . $migration, $orchestra . '/database/migrations/' . $migration);
        }

        copy(__DIR__ . '/../vendor/pionect/daalder/tests/storage/oauth-private.key', $orchestra . '/storage/oauth-private.key');
        copy(__DIR__ . '/../vendor/pionect/daalder/tests/storage/oauth-public.key', $orchestra . '/storage/oauth-public.key');
    }

    protected function getPackageProviders($app): array
    {
        return [
            DaalderServiceProvider::class,
            ScoutServiceProvider::class,
            ElasticScoutConfigServiceProvider::class,
            PassportServiceProvider::class,
            PermissionServiceProvider::class,
            TranslatableServiceProvider::class,
            JobCentralServiceProvider::class,
            ConsoleServiceProvider::class,
            ScoutElasticServiceProvider::class,
        ];
    }

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}