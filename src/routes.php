<?php

Route::group(['prefix' => 'dbtolaravel'], function() {

    Route::get('/', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@redirect');
    Route::put('/write', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@writeToFile');
    Route::get('/{connection}', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@table');
    Route::get('/{connection}/render/{table}/{key}', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@render');
    Route::get('/{connection}/render/{table}/{key}/diff', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@renderDiff');

});