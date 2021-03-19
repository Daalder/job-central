<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class RenameJcJobsTable extends Migration
{
    public function up()
    {
        if(Schema::hasTable('jc_jobs')) {
            Schema::rename('jc_jobs', 'job_central');
        }
    }

    public function down()
    {
        // This change shouldn't be revertable
    }
}