<?php

namespace Daalder\JobCentral\Repositories;

use Daalder\JobCentral\Events\BeforeCachingChart;
use Daalder\JobCentral\Models\JCJobFetcher;
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

    /**
     * @var JCJobFetcher
     */
    protected $jcJobFetcher;

    public function __construct() {
        $this->cacheRepository = resolve(CacheRepository::class);
        $this->cacheRepository->setDefaultTTL(5); // 5 minutes
        $this->jcJobFetcher = resolve(JCJobFetcher::class);
    }

    public function makeJobRunsLineChart(array $jobs, $days = null, $hours = null) {
        return $this->cacheRepository->remember('job-central-jobruns-linechart', function () use ($jobs, $days, $hours) {
            if($days) {
                $xLabels = $this->makeDailyLabels($days);
            } else {
                $xLabels = $this->makeHourlyLabels($hours);
            }

            $series = $this->getJobRunsLineChartSeries($jobs, $days, $hours);
            $chart = $this->makeLineChart($xLabels, $series, $jobs);


            $event = new BeforeCachingChart($chart);
            event($event);
            return response()->json($event->getChart());
        }, [$jobs, $days, $hours]);
    }

    public function makeJobResultsColumnChart(array $jobs, $days = null, $hours = null) {
        return $this->cacheRepository->remember('job-central-jobresults-columnchart', function () use ($jobs, $days, $hours) {
            if($days) {
                $categories = $this->makeDailyLabels($days);
            } else {
                $categories = $this->makeHourlyLabels($hours);
            }

            $series = $this->getJobRunsColumnChartSeries($jobs, $days, $hours);
            $chart = $this->makeColumnChart($categories, $series);

            $event = new BeforeCachingChart($chart);
            event($event);
            return response()->json($event->getChart());
        }, [$jobs, $days, $hours]);
    }

    public function makeExceptionsList(array $jobs, $days = null, $hours = null) {
        return $this->cacheRepository->remember('job-central-exceptions-list', function () use ($jobs, $days, $hours) {
            $items = $this->getExceptionListItems($jobs, $days, $hours);
            $chart = $this->makeList($items);

            $event = new BeforeCachingChart($chart);
            event($event);
            return response()->json($event->getChart());
        }, [$jobs, $days, $hours]);
    }

    public function getJobsInGroup($group) {
        if($group === '*') {
            $enabledJobs = collect(config('job-central.groups'))->flatten()->unique()->values();
        } else {
            $enabledJobs = collect(config('job-central.groups')[$group])->unique()->values();
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
        $params = [
            'filter' => [
                'job_class' => '',
                'finished_or_failed_at' => [
                    'min' => '',
                    'max' => ''
                ],
            ]
        ];

        $series = [];
        $startDate = $endDate = $histogramKey = null;

        if($days) {
            $startDate = today()->subDays($days - 1)->hours(0)->minutes(0)->seconds(0);
            $endDate = today()->hours(23)->minutes(59)->seconds(59);
            $histogramKey = 'histogram_daily';
        } else {
            $startDate = now()->minute(0)->second(0)->subHours($hours - 1);
            $endDate = now()->minute(0)->second(0)->subHours($hours - 1)->addHours($hours);
            $histogramKey = 'histogram_hourly';
        }

        $params['filter']['finished_or_failed_at']['min'] = $startDate->toDateTimeString();
        $params['filter']['finished_or_failed_at']['max'] = $endDate->toDateTimeString();

        foreach($jobs as $jobClass) {
            $params['filter']['job_class'] = $jobClass;

            $results = $this->jcJobFetcher->search($params);
            $histogram = $results->aggregations[$histogramKey]['buckets'];
            $total = $results->total;

            $serie = [];
            foreach($histogram as $dateTimeChunk) {
                array_push($serie, $dateTimeChunk['doc_count']);
            }

            array_push($series, $serie);
        }

        return $series;
    }

    private function getJobRunsColumnChartSeries(array $jobs, $days = null, $hours = null) {
        $params = [
            'filter' => [
                'job_class' => $jobs,
                'finished_or_failed_at' => [
                    'min' => '',
                    'max' => ''
                ],
                'status' => '',
            ]
        ];

        $series = [[],[]];
        $startDate = $endDate = null;

        if($days) {
            $startDate = today()->subDays($days - 1)->hours(0)->minutes(0)->seconds(0);
            $endDate = today()->hours(23)->minutes(59)->seconds(59);
            $histogramKey = 'histogram_daily';
        } else {
            $startDate = now()->minute(0)->second(0)->subHours($hours - 1);
            $endDate = now()->minute(0)->second(0)->subHours($hours - 1)->addHours($hours);
            $histogramKey = 'histogram_hourly';
        }

        $params['filter']['finished_or_failed_at']['min'] = $startDate->toDateTimeString();
        $params['filter']['finished_or_failed_at']['max'] = $endDate->toDateTimeString();

        $params['filter']['status'] = JCJob::FAILED;
        $failedJobResults = $this->jcJobFetcher->search($params);
        $failedJobHistogram = $failedJobResults->aggregations[$histogramKey]['buckets'];

        $params['filter']['status'] = JCJob::SUCCEEDED;
        $succeededJobResults = $this->jcJobFetcher->search($params);
        $succeededJobHistogram = $succeededJobResults->aggregations[$histogramKey]['buckets'];

        foreach($failedJobHistogram as $dateTimeChunk) {
            $series[0][] = $dateTimeChunk['doc_count'];
        }

        foreach($succeededJobHistogram as $dateTimeChunk) {
            $series[1][] = $dateTimeChunk['doc_count'];
        }

        return $series;
    }

    private function getExceptionListItems(array $jobs, $days = null, $hours = null) {
        $params = [
            'filter' => [
                'job_class' => $jobs,
                'finished_or_failed_at' => [
                    'min' => '',
                    'max' => ''
                ],
                'status' => JCJob::FAILED,
            ]
        ];

        if($days) {
            $startDate = now()->minute(0)->second(0)->subDays($days - 1);
            $endDate = now()->minute(0)->second(0)->subDays($days - 1)->addDays($days + 1);
        } else {
            $startDate = now()->minute(0)->second(0)->subHours($hours - 1);
            $endDate = now()->minute(0)->second(0)->subHours($hours - 1)->addHours($hours);
        }

        $params['filter']['job_class'] = $jobs;
        $params['filter']['finished_or_failed_at']['min'] = $startDate->toDateTimeString();
        $params['filter']['finished_or_failed_at']['max'] = $endDate->toDateTimeString();

        $jcJobExceptions = $this->jcJobFetcher->search($params);

        $hits = $this->jcJobFetcher->search($params)->hits['hits'];
        if(count($hits) === 0) {
            return [];
        }
        $hits = collect($hits)->pluck('_source');

        $jcJobsExceptionGroups = $hits->groupBy(function($jcJob) {
            return $jcJob['exception'];
        })->map(function($exceptionGroup) {
            return $exceptionGroup->sortByDesc('finished_or_failed_at');
        })->sortByDesc(function($exceptionGroup) {
            return $exceptionGroup->first()['finished_or_failed_at'];
        });

        return $jcJobsExceptionGroups->map(function($jcJobGroup, $exception) {
            // Dates from ElasticSearch are in UTC format. Parse to local timezone with fallback to UTC ('Z')
            $lastSeen = Carbon::createFromDate($jcJobGroup->first()['finished_or_failed_at'])->setTimezone(config('app.timezone') ?? 'Z')->format('d-m-Y H:i:s');

            return [
                'title' => $exception,
                'subtitle' => $jcJobGroup->count() . 'x - Last seen: ' . $lastSeen
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
