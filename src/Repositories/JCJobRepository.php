<?php

namespace Daalder\JobCentral\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Daalder\JobCentral\Models\JCJob;
use Pionect\Daalder\Services\Cache\CacheRepository;

class JCJobRepository
{
    /**
     * @var CacheRepository
     */
    protected $cacheRepository;

    public function __construct() {
        $this->cacheRepository = resolve(CacheRepository::class);
    }

    public function makeJobRunsLineChart(array $jobs, $days = null, $hours = null) {
        $key = 'job-central-jobruns-linechart';
        $params = [$jobs, $days, $hours];

        return $this->cacheRepository->remember($key, function () use ($jobs, $days, $hours) {
            if($days) {
                $xLabels = $this->makeDailyLabels($days);
            } else {
                $xLabels = $this->makeHourlyLabels($hours);
            }

            $series = $this->getJobRunsLineChartSeries($jobs, $days, $hours);
            $chart = $this->makeLineChart($xLabels, $series, $jobs);

            return response()->json($chart);
        }, $params, function ($results) use ($jobs) {
            $tags = [];
            foreach ($jobs as $jobClass) {
                $tags[] = 'job-central-'.Str::lower($jobClass);
            }
            return $tags;
        });
    }

    public function makeJobResultsColumnChart(array $jobs, $days = null, $hours = null) {
        $key = 'job-central-jobresults-columnchart';
        $params = [$jobs, $days, $hours];

        return $this->cacheRepository->remember($key, function () use ($jobs, $days, $hours) {
            if($days) {
                $categories = $this->makeDailyLabels($days);
            } else {
                $categories = $this->makeHourlyLabels($hours);
            }

            $series = $this->getJobRunsColumnChartSeries($jobs, $days, $hours);
            $chart = $this->makeColumnChart($categories, $series);

            return response()->json($chart);
        }, $params, function ($results) use ($jobs) {
            $tags = [];
            foreach ($jobs as $jobClass) {
                $tags[] = 'job-central-'.Str::lower($jobClass);
            }
            return $tags;
        });
    }

    public function makeExceptionsList(array $jobs, $days = null, $hours = null) {
        $key = 'job-central-exceptions-list';
        $params = [$jobs, $days, $hours];

        return $this->cacheRepository->remember($key, function () use ($jobs, $days, $hours) {
            $items = $this->getExceptionListItems($jobs, $days, $hours);
            $chart = $this->makeList($items);
            return response()->json($chart);
        }, $params, function ($results) use ($jobs) {
            $tags = [];
            foreach ($jobs as $jobClass) {
                $tags[] = 'job-central-'.Str::lower($jobClass);
            }
            return $tags;
        });
    }

    public function getJobsInGroup($group) {
        if($group === '*') {
            $enabledJobs = collect(config('job-central.groups'))->flatten();
        } else {
            $enabledJobs = collect(config('job-central.groups')[$group]);
        }

        return $enabledJobs->map(function($jobClass) {
            return Arr::last(explode('\\', $jobClass));
        })->toArray();
    }

    private function makeDailyLabels($days) {
        $labels = [];
        for($i = 0; $i < $days; $i++) {
            $targetDate = today()->subDays($days - 1)->addDays($i);
            array_push($labels, $targetDate->format('d-m'));
        }
        return $labels;
    }

    private function makeHourlyLabels($hours) {
        $labels = [];
        for($i = 0; $i < $hours; $i += 1) {
            $targetDate = now()->minute(0)->second(0)->subHours($hours - 1)->addHours($i);
            array_push($labels, $targetDate->format('H:00'));
        }
        return $labels;
    }

    private function getJobRunsLineChartSeries(array $jobs, $days = null, $hours = null) {
        $series = [];

        foreach($jobs as $jobClass) {
            $serie = [];

            if($days) {
                for($i = 0; $i < $days; $i++) {
                    $targetDate = today()->subDays($days - 1)->addDays($i);
                    $runs = JCJob::whereDate('finished_or_failed_at', $targetDate)->where('job_class', $jobClass)->count();

                    array_push($serie, $runs);
                }
            } else {
                for($i = 0; $i < $hours; $i += 1) {
                    $startDate = now()->minute(0)->second(0)->subHours($hours - 1)->addHours($i);
                    $endDate = now()->minute(0)->second(0)->subHours($hours - 1)->addHours($i + 1);

                    $runs = JCJob::whereBetween('finished_or_failed_at', [$startDate, $endDate])
                        ->where('job_class', $jobClass)->count();
                    array_push($serie, $runs);
                }
            }

            array_push($series, $serie);
        }

        return $series;
    }

    private function getJobRunsColumnChartSeries(array $jobs, $days = null, $hours = null) {
        $series = [[],[]];

        if($days) {
            for($i = 0; $i < $days; $i++) {
                $targetDate = today()->subDays($days - $i -1);

                $failedJobs = JCJob::whereDate('finished_or_failed_at', $targetDate)
                    ->whereIn('job_class', $jobs)
                    ->where('status', JCJob::FAILED)
                    ->count();
                $successfulJobs = JCJob::whereDate('finished_or_failed_at', $targetDate)
                    ->whereIn('job_class', $jobs)
                    ->where('status', JCJob::SUCCEEDED)
                    ->count();

                $series[0][] = $failedJobs;
                $series[1][] = $successfulJobs;
            }
        } else {
            for($i = 0; $i < $hours; $i++) {
                $startDate = now()->minute(0)->second(0)->subHours($hours - 1)->addHours($i);
                $endDate = now()->minute(0)->second(0)->subHours($hours - 1)->addHours($i + 1);

                $failedJobs = JCJob::whereBetween('finished_or_failed_at', [$startDate, $endDate])
                    ->whereIn('job_class', $jobs)
                    ->where('status', JCJob::FAILED)
                    ->count();
                $successfulJobs = JCJob::whereBetween('finished_or_failed_at', [$startDate, $endDate])
                    ->whereIn('job_class', $jobs)
                    ->where('status', JCJob::SUCCEEDED)
                    ->count();

                $series[0][] = $failedJobs;
                $series[1][] = $successfulJobs;
            }
        }

        return $series;
    }

    private function getExceptionListItems(array $jobs, $days = null, $hours = null) {
        if($days) {
            $startDate = now()->minute(0)->second(0)->subDays($days - 1);
            $endDate = now()->minute(0)->second(0)->subDays($days - 1)->addDays($days + 1);
        } else {
            $startDate = now()->minute(0)->second(0)->subHours($hours - 1);
            $endDate = now()->minute(0)->second(0)->subHours($hours - 1)->addHours($hours);
        }

        $jcJobsExceptions = JCJob::whereBetween('finished_or_failed_at', [$startDate, $endDate])
            ->whereIn('job_class', $jobs)
            ->whereNotNull('exception')->get();

        $jcJobsExceptionGroups = $jcJobsExceptions->groupBy(function($jcJob) {
            return $jcJob->exception;
        })->map(function($exceptionGroup) {
            return $exceptionGroup->sortByDesc('finished_or_failed_at');
        })->sortByDesc(function($exceptionGroup) {
            return $exceptionGroup->first()->finished_or_failed_at;
        });

        return $jcJobsExceptionGroups->map(function($jcJobGroup, $exception) {
            return [
                'title' => $exception,
                'subtitle' => $jcJobGroup->count() . 'x - Last seen: ' . Carbon::parse($jcJobGroup->first()->finished_or_failed_at)->format('d-m-Y h:i:s')
            ];
        })->toArray();
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
}
