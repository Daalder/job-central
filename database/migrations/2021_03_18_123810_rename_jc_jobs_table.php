<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class RenameJcJobsTable extends Migration
{
    public function up()
    {
        Schema::rename('jc_jobs', 'job_central');
    }

    public function down()
    {
        Schema::rename('job_central', 'jc_jobs');
    }
}