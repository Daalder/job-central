<?php

namespace Daalder\JobCentral;

use App\Jobs\TestingJob;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Daalder\JobCentral\Models\JCJob;
use Daalder\JobCentral\Providers\RouteServiceProvider;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Daalder\JobCentral\Testing\TestCommand;
use Pionect\Daalder\Services\Cache\CacheRepository;

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
        $this->cacheRepository = resolve(CacheRepository::class);
    }

    private function jobIsJCEnabled($jobClassPath) {
        $enabledJobs = collect(config('job-central.groups'))->flatten();
        return $enabledJobs->contains($jobClassPath);
    }

    private function getJCJobFromJobId($id) {
        return JCJob::where('job_id', '=', $id)->first();
    }

    private function updateJCJobStatus($id, $status) {
        $jcJob = $this->getJCJobFromJobId($id);
        if($jcJob) {
//            $this->cacheRepository->clear('job-central-job', [ $jcJob->job_class ], null);

            $jcJob->status = $status;
            $jcJob->finished_or_failed_at = now();
            $jcJob->save();
        }
    }

    private function saveJobException($id, $exception) {
        $jcJob = $this->getJCJobFromJobId($id);
        if($jcJob) {
//            $this->cacheRepository->clear('job-central-job', [ $jcJob->job_class ], null);

            $jcJob->exception = $exception->getMessage();
            $jcJob->save();
        }
    }

    private function makeJCJob($id, $jobClassPath) {
        // If logging is enabled for this job class
        if($this->jobIsJCEnabled($jobClassPath)) {
            $jobClass = Arr::last(explode('\\', $jobClassPath));

            // Prevent duplicate entries between restarting queue workers/listeners
            $existingEntryWithId = JCJob::where('job_id', '=', $id)->first();
            if(!$existingEntryWithId) {
                JCJob::make([
                    'job_id' => $id,
                    'job_class' => $jobClass,
                    'status' => JCJob::RUNNING,
                ])->save();
            }

//            $this->cacheRepository->clear('job-central-job', [ $jobClass ], null);
        }
    }

    private function deleteJCJobIfStatusNotFailed($id) {
        $jcJob = $this->getJCJobFromJobId($id);

        // If JCJob is found and doesn't have failed status.
        // Jobs with failed status have permanently failed and need to be kept for logging
        if($jcJob && $jcJob->status !== JCJob::FAILED) {
//            $this->cacheRepository->clear('job-central-job', [ $jcJob->job_class ], null);

            $jcJob->forceDelete();
        }
    }

    /**
     * Boot JobCentralServiceProvider
     */
    public function boot()
    {
        parent::boot();

        Queue::before(function (JobProcessing $event) {
            // This job will now start running
            $this->makeJCJob($event->job->getJobId(), $event->job->payload()['displayName']);
        });

        Queue::exceptionOccurred(function(JobExceptionOccurred $event) {
            // This job failed, but will be retried (removed and new instance with same payload queued)
            $this->deleteJCJobIfStatusNotFailed($event->job->getJobId());
        });

        Queue::failing(function (JobFailed $event) {
            // This job failed, and won't be retried
            $this->updateJCJobStatus($event->job->getJobId(), JCJob::FAILED);
            $this->saveJobException($event->job->getJobId(), $event->exception);
        });

        Queue::after(function (JobProcessed $event) {
            // This job completed succesfully
            $this->updateJCJobStatus($event->job->getJobId(), JCJob::SUCCEEDED);
        });

        $this->publishes([
            __DIR__ . '/../config/job-central.php' => config_path('job-central.php'),
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
            __DIR__ . '/../config/job-central.php', 'job-central');

        $this->app->register(RouteServiceProvider::class);
    }
}
