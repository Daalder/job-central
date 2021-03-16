<?php

namespace Daalder\JobCentral\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Daalder\JobCentral\Models\JCCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Daalder\JobCentral\Models\JCJob;
use Pionect\Daalder\Services\Cache\CacheRepository;

class ApiController extends Controller
{
    /**
     * @var CacheRepository
     */
    private $cacheRepository;

    /**
     * ApiController constructor.
     * @param  CacheRepository  $cacheRepository
     */
    public function __construct() {
        $this->cacheRepository = resolve(CacheRepository::class);
    }

    /**
     * @param  array  $labels
     * @param  array  $series
     * @param  array  $seriesLabels
     * @return array
     */
    private function makeLineChart(array $labels, array $series, array $seriesLabels = []) {
        $payload = [
            "x_axis" => [
                "labels" => []
            ],
            "series" => []
        ];

        foreach($labels as $index => $label) {
            $payload["x_axis"]["labels"][] = $label;
        }

        foreach($series as $seriesIndex => $serie) {
            $payload["series"][$seriesIndex] = ["data" => []];

            foreach($serie as $value)  {
                $payload["series"][$seriesIndex]["data"][] = $value;
            }
        }

        foreach($seriesLabels as $seriesIndex => $seriesLabel) {
            $payload["series"][$seriesIndex]["name"] = $seriesLabel;
        }

        return $payload;
    }

    /**
     * @param $categories
     * @param $series
     * @param  string  $title
     * @return array
     */
    private function makeColumnChart($categories, $series, $title = '') {
        $payload = [
            'chart' => [
                'type' => 'column',
                'renderTo' => "container"
            ],
            'title' => [
                'text' => $title,
            ],
            'xAxis' => [
                'categories' => [],
            ],
            'yAxis' => [
                'min' => 0,
                'title' => [
                    'text' => 'Amount',
                ],
            ],
            'plotOptions' => [
                'column' => [
                    'pointPadding' => 0.2,
                    'borderWidth' => 0,
                ],
            ],
            'series' => [
                [
                    'name' => 'Failed',
                    'data' => [],
                ],
                [
                    'name' => 'Succeeded',
                    'data' => [],
                ],
            ],
        ];

        foreach($categories as $category) {
            $payload['xAxis']['categories'][] = $category;
        }

        foreach($series as $index => $serie) {
            foreach($serie as $value) {
                $payload["series"][$index]["data"][] = $value;
            }
        }

        return [
            "highchart" => json_encode($payload)
        ];
    }

    /**
     * @param $items
     * @return array
     */
    private function makeList($items) {
        $payload = [];

        foreach($items as $item) {
            $payload[] = [
                'title' => [
                    'text' => Str::limit($item['title'], 100)
                ],
                'description' => $item['subtitle']
            ];
        }

        return $payload;
    }

    public function getJobsInGroup($group) {
        $enabledJobs = collect(config('job-central.groups.' . $group));

        return $enabledJobs->map(function($jobClass) {
            return Arr::last(explode('\\', $jobClass));
        });
    }

    public function makeJobCacheTags($jobClassNames) {
        if(($jobClassNames instanceof Collection) === false) {
            $jobClassNames = collect($jobClassNames);
        }

        return $this->cacheRepository->makeTags("job-central-job", $jobClassNames->toArray(), null);
    }

    public function makeGroupCacheTags($groupName) {
        $jobNames = $this->getJobsInGroup($groupName);

        $groupTags = $this->cacheRepository->makeTags("job-central-group", $groupName,null);
        $jobTags = $this->makeJobCacheTags($jobNames);

        return array_merge($groupTags, $jobTags);
    }

    /**
     * @param $group
     * @param $days
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function groupJobRuns($group, $days) {
//        $key = "job-central-group-runs-$group-$days";

//        return $this->cacheRepository->remember($key, function () use ($group, $days) {
            // If group doesn't exist
            if(Arr::has(config('job-central.groups'), $group) === false) {
                return;
            }

            $enabledJobs = $this->getJobsInGroup($group);

            $labels = $series = $seriesNames = [];

            for($i = 0; $i < $days; $i++) {
                $targetDate = Carbon::today()->subDays($days - $i - 1);

                array_push($labels, $targetDate->format('d-m'));
            }

            foreach($enabledJobs as $jobClass) {
                $serie = [];

                for($i = 0; $i < $days; $i++) {
                    $targetDate = Carbon::today()->subDays($days - $i - 1);
                    $runs = JCJob::whereDate('finished_or_failed_at', $targetDate)->where('job_class', $jobClass)->count();

                    array_push($serie, $runs);
                }

                array_push($series, $serie);
                array_push($seriesNames, $jobClass);
            }

            $payload = $this->makeLineChart($labels, $series, $seriesNames);

            return response()->json($payload);
//        }, [], function ($results) use ($group) {
//            return $this->makeGroupCacheTags($group);
//        });
    }

    /**
     * @param $group
     * @param $days
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function groupJobRunsResults($group, $days) {
//        $key = "job-central-group-results-$group-$days";

//        return $this->cacheRepository->remember($key, function () use ($group, $days) {
            // If group doesn't exist
            if(Arr::has(config('job-central.groups'), $group) === false) {
                return;
            }

            $enabledJobs = $this->getJobsInGroup($group)->toArray();

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

            $payload = $this->makeColumnChart($categories, $series);

            return response()->json($payload);
//        }, [], function ($results) use ($group) {
//            return $this->makeGroupCacheTags($group) ;
//        });
    }

    /**
     * @param $group
     * @param $days
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function groupExceptions($group, $days) {
//        $key = "job-central-group-exceptions-$group-$days";

//        return $this->cacheRepository->remember($key, function () use ($group, $days) {
            // If group doesn't exist
            if(Arr::has(config('job-central.groups'), $group) === false) {
                return;
            }

            $fromDate = Carbon::today()->subDays($days);
            $enabledJobs = $this->getJobsInGroup($group)->toArray();

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

            $payload = $this->makeList($exceptions);

            return response()->json($payload);

            return [];
//        }, [], function ($results) use ($group) {
//            return $this->makeGroupCacheTags($group);
//        });
    }

    /**
     * @param $jobClass
     * @param $days
     * @return \Illuminate\Http\JsonResponse
     */
    public function jobRuns($jobClass, $days) {
//        $key = "job-central-job-runs-$jobClass-$days";

//        return $this->cacheRepository->remember($key, function () use ($jobClass, $days) {
            $labels = [];
            $series = [[]];

            for($i = 0; $i < $days; $i++) {
                $targetDate = Carbon::today()->subDays($days - $i - 1);

                array_push($labels, $targetDate->format('d-m'));

                $jobRunCount = JCJob::where('job_class', '=', $jobClass)
                    ->whereDate('finished_or_failed_at', $targetDate)->count();

                array_push($series[0], $jobRunCount);
            }

            $payload = $this->makeLineChart($labels, $series);

            return response()->json($payload);
//        }, [], function ($results) use ($jobClass) {
//            return $this->makeJobCacheTags($jobClass);
//        });
    }

    /**
     * @param $jobClass
     * @param $days
     * @return \Illuminate\Http\Response|mixed
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function jobRunsResults($jobClass, $days) {
//        $key = "job-central-job-results-$jobClass-$days";

//        return $this->cacheRepository->remember($key, function () use ($jobClass, $days) {
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

            $payload = $this->makeColumnChart($categories, $series);

            return response()->make($payload);
//        }, [], function ($results) use ($jobClass) {
//            return $this->makeJobCacheTags($jobClass);
//        });
    }
}
