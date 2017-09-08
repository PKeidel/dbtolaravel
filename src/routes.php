<?php

Route::group(['prefix' => 'dbtolaravel'], function() {

    Route::get('/', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@redirect');
    Route::get('/{connection}', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@welcome');
    Route::get('/{connection}/{table}/infos', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@getInfos');
    Route::get('/{connection}/{table}/{key}/write/{overwrite?}', 'PKeidel\DBtoLaravel\Controllers\DBtoLaravelController@writeToFile');

});