<?php

namespace Daalder\JobCentral\Repositories;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Pionect\Daalder\Models\BaseRepository;
use Daalder\JobCentral\Models\JCJob;

class JCJobRepository extends BaseRepository
{
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
        if($group === '*') {
            $enabledJobs = collect(config('job-central.groups'))->flatten();
        } else {
            $enabledJobs = collect(config('job-central.groups.' . $group));
        }

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
}
