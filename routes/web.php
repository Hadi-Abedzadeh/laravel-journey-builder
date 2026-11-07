<?php

use OpenJourney\LaravelJourneyBuilder\Http\Controllers\JourneyBuilderController;
use OpenJourney\LaravelJourneyBuilder\Http\Middleware\DashboardAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', DashboardAccess::class])->prefix(config('journey_builder.route_prefix', 'journeys'))->name('jb.')->group(function () {
        Route::get('/',[JourneyBuilderController::class,'index'])->name('index');
        Route::get('/sms',[JourneyBuilderController::class,'smsIndex'])->name('sms');
        Route::post('/flows',[JourneyBuilderController::class,'create'])->name('create');
        Route::get('/flows/{id}/edit',[JourneyBuilderController::class,'builder'])->whereNumber('id')->name('builder');
        Route::get('/flows/{id}/export',[JourneyBuilderController::class,'export'])->whereNumber('id')->name('export');
        Route::get('/flows/{id}',[JourneyBuilderController::class,'show'])->whereNumber('id')->name('show');
        Route::put('/flows/{id}',[JourneyBuilderController::class,'save'])->whereNumber('id')->name('save');
        Route::put('/flows/{id}/nodes/{nodeId}/token',[JourneyBuilderController::class,'apiToken'])->whereNumber('id')->where('nodeId','[A-Za-z0-9_-]+')->name('token');
        Route::post('/flows/{id}/status',[JourneyBuilderController::class,'status'])->whereNumber('id')->name('status');
});
