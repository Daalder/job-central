<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class MakeFinishedOrFailedAtFieldSearchable extends Migration
{
    public function up()
    {
        Schema::table('job_central', function (Blueprint $table) {
            $table->index('finished_or_failed_at');
        });
    }

    public function down()
    {
        Schema::table('job_central', function (Blueprint $table) {
            $table->dropIndex(['finished_or_failed_at']);
        });
    }
}