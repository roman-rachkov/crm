<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'users', 'middleware' => []], function () {
    Route::get('/', 'UserController@index')->name('api.users.index');
    Route::post('/', 'UserController@store')->name('api.users.store');
    Route::get('/{user}', 'UserController@show')->name('api.users.read');
    Route::put('/{user}', 'UserController@update')->name('api.users.update');
    Route::delete('/{user}', 'UserController@destroy')->name('api.users.delete');
});
