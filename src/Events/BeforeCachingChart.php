<?php

namespace Daalder\JobCentral\Events;

use Pionect\Daalder\Events\Event;

class BeforeCachingChart extends Event
{
    public $chart;

    public function __construct($chart)
    {
        $this->chart = $chart;
    }

    public function getChart()
    {
        return $this->chart;
    }

    public function setChart($chart) {
        $this->chart = $chart;
    }
}
