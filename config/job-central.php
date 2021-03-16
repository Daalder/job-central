<?php

return [
    'keep-logs-for-days' => 7,
    'groups' => [
        'TEST' => [
            \Daalder\JobCentral\Testing\TestingJob::class,
            \Daalder\JobCentral\Testing\TestingJobThatFails::class,
        ],
        'Daalder' => [
            \Pionect\Daalder\Jobs\Category\SyncProducts::class,
            \Pionect\Daalder\Jobs\Media\CreateThumbnails::class,
            \Pionect\Daalder\Jobs\Media\RecreateThumbnails::class,
            \Pionect\Daalder\Jobs\Product\SyncCategories::class,
        ],
    ],
];
