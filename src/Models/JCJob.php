<?php

namespace Daalder\JobCentral\Models;

use Illuminate\Database\Eloquent\Model;

class JCJob extends Model
{
    protected $table = 'jc_jobs';

    protected $dates = ['finished_or_failed_at'];

    protected $fillable = [
        'job_id',
        'job_class',
        'exception',
        'status'
    ];

    const PLANNED = 'planned';
    const RUNNING = 'running';
    const SUCCEEDED = 'succeeded';
    const FAILED = 'failed';
}
