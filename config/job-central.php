<?php

return [
    'keep-logs-for-days' => 7,
    'groups' => [
        'TEST' => [
            \Daalder\JobCentral\Testing\TestingJob::class,
            \Daalder\JobCentral\Testing\TestingJobThatFails::class,
        ],
    ],
];
