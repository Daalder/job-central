<?php

namespace Daalder\JobCentral\Providers;

use Daalder\JobCentral\Models\JCJob;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Pionect\Daalder\Services\Cache\CacheRepository;

class QueueLoggingProvider extends ServiceProvider
{
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
            $jcJob->status = $status;
            $jcJob->finished_or_failed_at = now();
            $jcJob->save();

            $this->cacheRepository->clear('job-central-'.Str::lower($jcJob->job_class));
        }
    }

    private function saveJobException($id, $exception) {
        $jcJob = $this->getJCJobFromJobId($id);
        if($jcJob) {
            $jcJob->exception = $exception->getMessage();
            $jcJob->save();

            $this->cacheRepository->clear('job-central-'.Str::lower($jcJob->job_class));
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

                $this->cacheRepository->clear('job-central-'.Str::lower($jobClass));
            }
        }
    }

    private function deleteJCJobIfStatusNotFailed($id) {
        $jcJob = $this->getJCJobFromJobId($id);

        // If JCJob is found and doesn't have failed status.
        // Jobs with failed status have permanently failed and need to be kept for logging
        if($jcJob && $jcJob->status !== JCJob::FAILED) {
            $jcJob->forceDelete();
        }
    }

    public function boot()
    {
        parent::boot();

        // TODO: create JCJob on JobQueued event
//        $this->app['events']->listen(JobQueued::class, function(JobQueued $event) {
//
//        });

        Queue::before(function (JobProcessing $event) {
            // This job will now start running
            $this->makeJCJob($event->job->uuid(), $event->job->payload()['displayName']);
        });

        Queue::exceptionOccurred(function(JobExceptionOccurred $event) {
            // This job failed, but will be retried (removed and new instance with same payload queued)
            $this->deleteJCJobIfStatusNotFailed($event->job->uuid());
        });

        Queue::failing(function (JobFailed $event) {
            // This job failed, and won't be retried
            $this->updateJCJobStatus($event->job->uuid(), JCJob::FAILED);
            $this->saveJobException($event->job->uuid(), $event->exception);
        });

        Queue::after(function (JobProcessed $event) {
            // This job completed succesfully
            $this->updateJCJobStatus($event->job->uuid(), JCJob::SUCCEEDED);
        });
    }
}
