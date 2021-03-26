<?php

namespace Daalder\JobCentral\Tests;

use Daalder\JobCentral\Models\JCJob;
use Daalder\JobCentral\Tests\TestCase as DaalderTestCase;

/**
 * Class ExampleTest
 * @package Daalder\JobCentral\Tests
 */
class GeneralTest extends DaalderTestCase
{
    /** @test */
    public function new_jobresults_are_fetchable()
    {
        JCJob::factory()->count(10)->create();
        sleep(5); // Syncing to ElasticSearch

        $hours = config('job-central.keep-logs-for-days') * 24;
        $json = $this->getJson("/job-central/jobruns-linechart?hours=$hours&group=*")
            ->assertSuccessful()
            ->json();

        $totalJobsCountOne = collect($json['series'])->pluck('data')
            ->sum(function($array){
                return array_sum($array);
            });

        $json = $this->getJson("/job-central/jobruns-linechart?hours=$hours&group=*")
            ->assertSuccessful()
            ->json();

        $totalJobsCountOne = collect($json['series'])->pluck('data')
            ->sum(function($array){
                return array_sum($array);
            });

        JCJob::factory()->count(10)->create();
        sleep(5); // Syncing to ElasticSearch

        $json = $this->getJson("/job-central/jobruns-linechart?hours=$hours&group=*")
            ->assertSuccessful()
            ->json();

        $totalJobsCountTwo = collect($json['series'])->pluck('data')
            ->sum(function($array){
                return array_sum($array);
            });

        self::assertEquals($totalJobsCountOne + 10, $totalJobsCountTwo);
    }
}