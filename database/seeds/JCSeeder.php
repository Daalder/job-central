<?php

use Illuminate\Database\Seeder;
use Daalder\JobCentral\Models\JCJob;
use Carbon\Carbon;

class JCSeeder extends Seeder
{
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
     * Run the database seeds.
     *
     * @return void
     */
    public function run(\Faker\Generator $faker)
    {
        $jcJobData = [];

        for($i = 0; $i < 1000; $i++) {
            $created_at = Carbon::now()->startOfDay()->subDays(rand(0, 10))->addHours(rand(0, 23))->addMinutes(rand(0, 59))->addSeconds(rand(0, 59));
            $updated_at = $created_at->addDays(rand(0, 3));

            $jcJob = [
                'job_id' => md5(Carbon::now()->addSeconds(rand(0, 2000))),
                'job_class' => 'TestingJob',
                'created_at' => $created_at,
                'updated_at' => $updated_at,
                'finished_or_failed_at' => $updated_at,
            ];

            if(rand(0,3) % 2 === 0 ) {
                $jcJob['status'] = JCJob::SUCCEEDED;
                $jcJob['exception'] = null;
            } else {
                $jcJob['status'] = JCJob::FAILED;
                $jcJob['exception'] = $this->exceptions[rand(0, count($this->exceptions) - 1)];
            }

            $jcJobData[] = $jcJob;
        }

        for($i = 0; $i < 1000; $i++) {
            $created_at = Carbon::now()->startOfDay()->subDays(rand(0, 10))->addHours(rand(0, 23))->addMinutes(rand(0, 59))->addSeconds(rand(0, 59));
            $updated_at = $created_at->addDays(rand(0, 3));

            $jcJob = [
                'job_id' => md5(Carbon::now()->addSeconds(rand(0, 2000))),
                'job_class' => 'TestingTwoJob',
                'created_at' => $created_at,
                'updated_at' => $updated_at,
                'finished_or_failed_at' => $updated_at,
            ];

            if(rand(0,3) % 2 === 0 ) {
                $jcJob['status'] = JCJob::FAILED;
                $jcJob['exception'] = null;
            } else {
                $jcJob['status'] = JCJob::SUCCEEDED;
                $jcJob['exception'] = $this->exceptions[rand(0, count($this->exceptions) - 1)];
            }

            $jcJobData[] = $jcJob;
        }

        JCJob::insert($jcJobData);
    }
}
