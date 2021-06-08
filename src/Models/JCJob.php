<?php

namespace Daalder\JobCentral\Models;

use Database\Factories\Product\JCJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Pionect\Daalder\Models\Searchable;

/**
 * Class JCJob
 * @package Daalder\JobCentral\Models
 * @property int $id
 * @property string $job_id
 * @property string $job_class
 * @property string $exception
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $finished_or_failed_at
 */
class JCJob extends Model
{
    use Searchable, HasFactory;

    protected $table = 'job_central';

    protected $dates = ['finished_or_failed_at'];

    protected $fillable = [
        'job_id',
        'job_class',
        'exception',
        'status'
    ];

    protected $mapping = [
        'dynamic' => true,
        'properties' => [
            'id' => [
                'type' => 'keyword'
            ],
            'job_id' => [
                'type' => 'keyword'
            ],
            'job_class' => [
                'type' => 'keyword'
            ],
            'exception' => [
                'type' => 'keyword'
            ],
            'status' => [
                'type' => 'keyword'
            ],
            'created_at' => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss'
            ],
            'updated_at' => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss'
            ],
            'finished_or_failed_at' => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss'
            ],
        ],
    ];

    protected $indexConfigurator = JCJobIndexConfigurator::class;

    const PLANNED = 'planned';
    const RUNNING = 'running';
    const SUCCEEDED = 'succeeded';
    const FAILED = 'failed';

    /**
     * @return array|mixed
     */
    public function toSearchableArray()
    {
        if (!$this->exists) {
            return [];
        }

        // The dates are parsed as $date->toJSON() per default, resulting in UTC dateTimestamps.
        // Fix this by
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'job_class' => $this->job_class,
            'exception' => $this->exception,
            'status' => $this->status,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'finished_or_failed_at' => $this->finished_or_failed_at ? $this->finished_or_failed_at->format('Y-m-d H:i:s') : null,
        ];
    }

    protected static function newFactory()
    {
        return JCJobFactory::new();
    }
}
