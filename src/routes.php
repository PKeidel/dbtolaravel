<?php

Route::group(['prefix' => 'dbtolaravel'], function() {

    Route::get('/', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@redirect');
    Route::put('/write', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@writeToFile');
    Route::get('/{connection}', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@welcome')->name('dbtolaravel::welcome');
    Route::get('/{connection}/infos', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@getAllInfos');
    Route::get('/{connection}/{table}/infos', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@getInfos');
    Route::get('/{connection}/render/{table}/{key}', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@render');
    Route::get('/{connection}/render/{table}/{key}/diff', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@renderDiff');

});