<?php

namespace Daalder\JobCentral\Testing;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Daalder\JobCentral\Models\JCJob;

class TestingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The instance of the Job Central Job assigned to this job
     *
     * @var JCJob
     */
    private $jobCentralJob = null;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Runs when Job is actually being processed
        sleep(rand(1, 3));
    }
}
