<?php

namespace Daalder\JobCentral\Models;

use ScoutElastic\IndexConfigurator;
use ScoutElastic\Migratable;

class JCJobIndexConfigurator extends IndexConfigurator
{
    use Migratable;

    /**
     * @var array
     */
    protected $settings = [
        'index' => [
            'mapping' => [
                'total_fields.limit' => 999999,
            ],
            'blocks' => [
                'read_only_allow_delete' => 'false'
            ]
        ]
    ];

    public function getName()
    {
        return strtolower('jc_job');
    }
}
