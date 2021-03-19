<?php

Route::get('jobruns-linechart', ApiController::class. '@jobRunsLineChart');
Route::get('results-columnchart', ApiController::class. '@resultsColumnChart');
Route::get('exceptions-list', ApiController::class. '@exceptionsList');