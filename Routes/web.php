<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
if (Module::isEnabled('Office')) {
    Route::prefix('plugins/office')->group(function() {
    //Route::prefix('onlyoffice')->group(function () {
        Route::get('/', 'OfficeController@index');
        Route::get('/editor', 'OfficeController@editor');
        Route::get('/webeditor', 'OfficeController@webeditor');
        Route::post('/webeditor', 'OfficeController@webeditor');
    });
}
