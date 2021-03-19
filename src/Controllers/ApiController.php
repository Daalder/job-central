<?php

namespace Daalder\JobCentral\Controllers;

use Daalder\JobCentral\Requests\JobCentralChartRequest;
use Pionect\Daalder\Http\Controllers\Api\Controller;
use Daalder\JobCentral\Repositories\JCJobRepository;
use Illuminate\Support\Collection;
use Daalder\JobCentral\Models\JCJob;

class ApiController extends Controller
{
    /**
     * @var JCJobRepository
     */
    private $jcJobRepository;

    /**
     * ApiController constructor.
     */
    public function __construct() {
        $this->jcJobRepository = resolve(JCJobRepository::class);
    }

    private function getJobsFromRequest($request) {
        if($request->has('group')) {
            return $this->jcJobRepository->getJobsInGroup($request->get('group'));
        } else {
            return [$request->get('job')];
        }
    }

    public function jobRunsLineChart(JobCentralChartRequest $request) {
        $jobs = $this->getJobsFromRequest($request);

        $days = $request->get('days');
        $hours = $request->get('hours');

        return $this->jcJobRepository->makeJobRunsLineChart($jobs, $days, $hours);
    }

    public function resultsColumnChart(JobCentralChartRequest $request) {
        $jobs = $this->getJobsFromRequest($request);

        $days = $request->get('days');
        $hours = $request->get('hours');

        return $this->jcJobRepository->makeJobResultsColumnChart($jobs, $days, $hours);
    }

    public function exceptionsList(JobCentralChartRequest $request) {
        $jobs = $this->getJobsFromRequest($request);

        $days = $request->get('days');
        $hours = $request->get('hours');

        return $this->jcJobRepository->makeExceptionsList($jobs, $days, $hours);
    }
}
