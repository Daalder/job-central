<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class ChangeExceptionField extends Migration
{
    public function up()
    {
        Schema::table('jc_jobs', function (Blueprint $table) {
            $table->longText('exception')->change();
        });
    }

    public function down()
    {
        Schema::table('jc_jobs', function (Blueprint $table) {
            $table->string('exception')->change();
        });
    }
}