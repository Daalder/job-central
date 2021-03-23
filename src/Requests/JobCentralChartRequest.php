<?php

namespace Daalder\JobCentral\Requests;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Pionect\Daalder\Http\Requests\Request;

class JobCentralChartRequest extends Request
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $groups = array_keys(config('job-central.groups'));
        $groups[] = '*';

        $jobs = array_flatten(config('job-central.groups'));
        foreach($jobs as $key => $job) {
            $jobs[$key] = Arr::last(explode('\\', $job));
        }

        $maxDays = config('job-central.keep-logs-for-days');
        $maxHours = $maxDays * 24;

        return [
            'job' => ['required_without:group', 'empty_with:group', 'string', Rule::in($jobs)],
            'group' => ['required_without:job', 'empty_with:job', 'string', Rule::in($groups)],
            'days' => ['required_without:hours', 'empty_with:hours', 'numeric', 'integer', 'min:1', 'max:'.$maxDays],
            'hours' => ['required_without:days', 'empty_with:days', 'numeric', 'integer', 'min:1', 'max:'.$maxHours],
        ];
    }
}
