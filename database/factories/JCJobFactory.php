<?php

namespace Database\Factories\Product;

use Daalder\JobCentral\Models\JCJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

class JCJobFactory extends Factory {
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = JCJob::class;

    protected $exceptions = [
        'Itaque doloribus et at veritatis consequatur voluptatem quam.',
        'Corporis dolorem totam et unde.',
        'Nihil consequuntur autem et tempora itaque.',
        'Voluptatem quis facilis quis autem.',
        'Autem id doloribus sit est autem.',
        'Rerum magni non est aut doloribus animi.',
        'Eum quisquam rerum rerum iusto architecto.',
        'Fugiat laudantium assumenda dignissimos atque aut dolorem quia.',
        'Dignissimos dolor architecto voluptatem quo quaerat.',
        'Eos odit asperiores ad.',
        'Esse quo accusantium consequatur vel nulla cupiditate voluptatem.',
        'Repellat aliquam repellendus possimus sed eos voluptatem animi.'
    ];

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $jcJobData = [];
        $jcEnabledJobs = collect(config('job-central.groups'))
            ->flatten()
            ->map(function($jobClass) {
                return Arr::last(explode('\\', $jobClass));
            });

        $createdAt = now()->subHours(mt_rand(24*4, 24*7))->subMinute(mt_rand(0, 30))->subSeconds(mt_rand(0, 59));
        $updatedAt = now()->subHours(mt_rand(0, 24*4))->subMinute(mt_rand(0, 30))->subSeconds(mt_rand(0, 59));

        return [
            'job_id' => $this->faker->uuid,
            'job_class' => $jcEnabledJobs->random(),
            'status' => JCJob::SUCCEEDED,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'finished_or_failed_at' => $updatedAt,
        ];
    }

    /**
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function failed() {
        return $this->state(function(array $attributes) {
            return [
                'status' => JCJob::FAILED,
                'exception' => array_rand($this->exceptions),
            ];
        });
    }
}
