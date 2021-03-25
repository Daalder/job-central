<?php

namespace Daalder\JobCentral\Tests;

use Daalder\JobCentral\Models\JCJob;
use Daalder\JobCentral\Tests\TestCase as DaalderTestCase;
use Pionect\Daalder\Models\Product\Product;

/**
 * Class ExampleTest
 * @package Daalder\JobCentral\Tests
 */
class CacheTest extends DaalderTestCase
{
    /** @test */
    public function jobresults_linechart_is_cached()
    {
        JCJob::factory()->count(10)->create();

        $hours = config('job-central.keep-logs-for-days') * 24;
        $json = $this->getJson("/job-central/jobruns-linechart?hours=$hours&group=*")
            ->assertSuccessful()
            ->json();
        
        $totalJobsCount = collect($json['series'])->pluck('data')
            ->sum(function($array){
                return array_sum($array);
            });

        Product::factory()->count(3)->create();
    }
}