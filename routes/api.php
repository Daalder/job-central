<?php

Route::group(['prefix' => 'job'], function () {
    Route::get('{jobClass}/runs/{days}', ApiController::class . '@jobRuns');
    Route::get('{jobClass}/results/{days}', ApiController::class . '@jobRunsResults');
});

Route::group(['prefix' => 'group'], function () {
    Route::get('{group}/runs/{days}', ApiController::class . '@groupJobRuns');
    Route::get('{group}/results/{days}', ApiController::class . '@groupJobRunsResults');
    Route::get('{group}/exceptions/{days}', ApiController::class . '@groupExceptions');
});