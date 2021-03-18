<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class DropJobGroupsColumn extends Migration {

	public function up()
	{
        Schema::table('job_central', function (Blueprint $table) {
            $table->dropColumn('job_groups');
        });
	}

	public function down()
	{
        Schema::table('job_central', function (Blueprint $table) {
            $table->string('job_groups', 255)->nullable()->after('job_class');
        });
	}
}