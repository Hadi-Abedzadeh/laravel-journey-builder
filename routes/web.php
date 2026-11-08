<?php

use OpenJourney\LaravelJourneyBuilder\Http\Controllers\JourneyBuilderController;
use OpenJourney\LaravelJourneyBuilder\Http\Middleware\DashboardAccess;
use Illuminate\Support\Facades\Route;
use OpenJourney\LaravelJourneyBuilder\Http\Controllers\SmsProviderController;

Route::middleware(['web', DashboardAccess::class])->prefix(config('journey_builder.route_prefix', 'journeys'))->name('jb.')->group(function () {
        Route::get('/',[JourneyBuilderController::class,'index'])->name('index');
        Route::get('/sms',[JourneyBuilderController::class,'smsIndex'])->name('sms');
        Route::get('/sms/providers',[SmsProviderController::class,'index'])->name('sms.providers');
        Route::post('/sms/providers',[SmsProviderController::class,'store'])->name('sms.providers.store');
        Route::put('/sms/providers/{id}',[SmsProviderController::class,'update'])->whereNumber('id')->name('sms.providers.update');
        Route::post('/sms/providers/{id}/default',[SmsProviderController::class,'makeDefault'])->whereNumber('id')->name('sms.providers.default');
        Route::delete('/sms/providers/{id}',[SmsProviderController::class,'destroy'])->whereNumber('id')->name('sms.providers.destroy');
        Route::post('/flows',[JourneyBuilderController::class,'create'])->name('create');
        Route::get('/flows/{id}/edit',[JourneyBuilderController::class,'builder'])->whereNumber('id')->name('builder');
        Route::get('/flows/{id}/export',[JourneyBuilderController::class,'export'])->whereNumber('id')->name('export');
        Route::get('/flows/{id}',[JourneyBuilderController::class,'show'])->whereNumber('id')->name('show');
        Route::put('/flows/{id}',[JourneyBuilderController::class,'save'])->whereNumber('id')->name('save');
        Route::put('/flows/{id}/nodes/{nodeId}/token',[JourneyBuilderController::class,'apiToken'])->whereNumber('id')->where('nodeId','[A-Za-z0-9_-]+')->name('token');
        Route::post('/flows/{id}/status',[JourneyBuilderController::class,'status'])->whereNumber('id')->name('status');
        Route::post('/flows/{id}/runs/{runId}/retry',[JourneyBuilderController::class,'retryRun'])->whereNumber('id')->whereNumber('runId')->name('retry.run');
});
