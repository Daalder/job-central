<?php

namespace Daalder\JobCentral\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Daalder\JobCentral\Models\JCJob;

class ClearOldJCJobs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 900;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $expirationDate = today()->subDays(config('job-central.keep-logs-for-days'));
        JCJob::query()
            ->whereDate('finished_or_failed_at', '<=', $expirationDate)
            ->unsearchable();

        JCJob::query()
            ->whereDate('finished_or_failed_at', '<=', $expirationDate)
            ->delete();
    }
}
