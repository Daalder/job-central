<?php

namespace Daalder\JobCentral\Controllers;

use Pionect\Daalder\Http\Controllers\Api\Controller;
use Carbon\Carbon;
use Daalder\JobCentral\Repositories\JCJobRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Daalder\JobCentral\Models\JCJob;
use Pionect\Daalder\Services\Cache\CacheRepository;

class ApiController extends Controller
{
    /**
     * @var CacheRepository
     */
    private $cacheRepository;
    private $jcJobRepository;

    /**
     * ApiController constructor.
     */
    public function __construct() {
        $this->cacheRepository = resolve(CacheRepository::class);
        $this->jcJobRepository = resolve(JCJobRepository::class);
    }

    /**
     * @param $group
     * @param $days
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function groupJobRuns($group, $days) {
        // If $group isn't '*' and doesn't exist
        if($group !== '*' && Arr::has(config('job-central.groups'), $group) === false) {
            return;
        }

        $enabledJobs = $this->jcJobRepository->getJobsInGroup($group);

        $labels = $series = $seriesNames = [];

        if($days <= 3) {
            // Create label for each hour
            for($i = 0; $i < $days * 24; $i += 1) {
                $targetDate = now()->minute(0)->second(0)->subHours($i);
                array_push($labels, $targetDate->format('H:00'));
            }
            $labels = array_reverse($labels);
        } else {
            // Create label for each day
            for($i = 0; $i < $days; $i++) {
                $targetDate = Carbon::today()->subDays($days - $i - 1);
                array_push($labels, $targetDate->format('d-m'));
            }
        }

        foreach($enabledJobs as $jobClass) {
            $serie = [];

            if($days <= 3) {
                // Prepare job data per hour
                for($i = 0; $i < $days * 24; $i += 1) {
                    $startDate = now()->minute(0)->second(0)->subHours($i);
                    $endDate = now()->minute(0)->second(0)->subHours($i)->addHours(1);

                    $runs = JCJob::whereBetween('finished_or_failed_at', [$startDate, $endDate])
                        ->where('job_class', $jobClass)->count();
                    array_push($serie, $runs);
                }
                $serie = array_reverse($serie);
            } else {
                // Prepare job data per day
                for($i = 0; $i < $days; $i++) {
                    $targetDate = Carbon::today()->subDays($days - $i - 1);
                    $runs = JCJob::whereDate('finished_or_failed_at', $targetDate)->where('job_class', $jobClass)->count();

                    array_push($serie, $runs);
                }
            }

            array_push($series, $serie);
            array_push($seriesNames, $jobClass);
        }

        $payload = $this->jcJobRepository->makeLineChart($labels, $series, $seriesNames);

        return response()->json($payload);
    }

    /**
     * @param $group
     * @param $days
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function groupJobRunsResults($group, $days) {
        // If group doesn't exist
        if(Arr::has(config('job-central.groups'), $group) === false) {
            return;
        }

        $enabledJobs = $this->jcJobRepository->getJobsInGroup($group)->toArray();

        $categories = [];
        $series = [[],[]];

        for($i = 0; $i < $days; $i++) {
            $targetDate = Carbon::today()->subDays($days - $i -1);

            $categories[] = $targetDate->format('d-m');

            $failedJobs = JCJob::whereDate('finished_or_failed_at', $targetDate)
                ->whereIn('job_class', $enabledJobs)
                ->where('status', JCJob::FAILED)
                ->count();
            $successfulJobs = JCJob::whereDate('finished_or_failed_at', $targetDate)
                ->whereIn('job_class', $enabledJobs)
                ->where('status', JCJob::SUCCEEDED)
                ->count();

            $series[0][] = $failedJobs;
            $series[1][] = $successfulJobs;
        }

        $payload = $this->jcJobRepository->makeColumnChart($categories, $series);

        return response()->json($payload);
    }

    /**
     * @param $group
     * @param $days
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function groupExceptions($group, $days) {
        // If group doesn't exist
        if(Arr::has(config('job-central.groups'), $group) === false) {
            return;
        }

        $fromDate = Carbon::today()->subDays($days);
        $enabledJobs = $this->jcJobRepository->getJobsInGroup($group)->toArray();

        $jcJobsExceptions = JCJob::whereDate('finished_or_failed_at', '>=', $fromDate)
            ->whereIn('job_class', $enabledJobs)
            ->whereNotNull('exception')->get();

        $jcJobsExceptionGroups = $jcJobsExceptions->groupBy(function($jcJob) {
            return $jcJob->exception;
        })->map(function($exceptionGroup) {
            return $exceptionGroup->sortByDesc('finished_or_failed_at');
        })->sortByDesc(function($exceptionGroup) {
            return $exceptionGroup->first()->finished_or_failed_at;
        });

        $exceptions = $jcJobsExceptionGroups->map(function($jcJobGroup, $exception) {
            return [
                'title' => $exception,
                'subtitle' => $jcJobGroup->count() . 'x - Last seen: ' . Carbon::parse($jcJobGroup->first()->finished_or_failed_at)->format('d-m-Y h:i:s')
            ];
        })->toArray();

        $payload = $this->jcJobRepository->makeList($exceptions);

        return response()->json($payload);
    }

    /**
     * @param $jobClass
     * @param $days
     * @return \Illuminate\Http\JsonResponse
     */
    public function jobRuns($jobClass, $days) {
        $labels = [];
        $series = [[]];

        for($i = 0; $i < $days; $i++) {
            $targetDate = Carbon::today()->subDays($days - $i - 1);

            array_push($labels, $targetDate->format('d-m'));

            $jobRunCount = JCJob::where('job_class', '=', $jobClass)
                ->whereDate('finished_or_failed_at', $targetDate)->count();

            array_push($series[0], $jobRunCount);
        }

        $payload = $this->jcJobRepository->makeLineChart($labels, $series);

        return response()->json($payload);
    }

    /**
     * @param $jobClass
     * @param $days
     * @return \Illuminate\Http\Response|mixed
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function jobRunsResults($jobClass, $days) {
        $categories = [];
        $series = [[],[]];

        for($i = 0; $i < $days; $i++) {
            $targetDate = Carbon::today()->subDays($days - $i -1);

            $categories[] = $targetDate->format('d-m');

            $failedRuns = JCJob::whereDate('finished_or_failed_at', $targetDate)
                ->where('job_class', $jobClass)
                ->where('status', JCJob::FAILED)
                ->count();
            $successfulRuns = JCJob::whereDate('finished_or_failed_at', $targetDate)
                ->where('job_class', $jobClass)
                ->where('status', JCJob::SUCCEEDED)
                ->count();

            $series[0][] = $failedRuns;
            $series[1][] = $successfulRuns;
        }

        $payload = $this->jcJobRepository->makeColumnChart($categories, $series);

        return response()->make($payload);
    }
}
