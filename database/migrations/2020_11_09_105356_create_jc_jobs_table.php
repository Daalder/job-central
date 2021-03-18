<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateJcJobsTable extends Migration {

	public function up()
	{
		Schema::create('job_central', function(Blueprint $table) {
			$table->increments('id');
            $table->string('job_id', 255)->index();
			$table->string('job_class', 255);
            $table->string('job_groups', 255)->nullable();
            $table->string('exception', 255)->nullable();
			$table->string('status', 255);
			$table->timestamps();
			$table->datetime('finished_or_failed_at')->nullable();
		});
	}

	public function down()
	{
        if(Schema::hasTable('jc_jobs')) {
            Schema::drop('jc_jobs');
        }

        if(Schema::hasTable('job_central')) {
            Schema::drop('job_central');
        }
	}
}