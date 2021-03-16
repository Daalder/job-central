<?php

namespace Daalder\JobCentral\Testing;

use Daalder\JobCentral\Testing\TestingJob;
use Daalder\JobCentral\Testing\TestingJobThatFails;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jc:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test command for Job Central';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        TestingJob::dispatch();
        TestingJobThatFails::dispatch();
    }
}
