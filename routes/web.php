<?php

use OpenJourney\LaravelJourneyBuilder\Http\Controllers\JourneyBuilderController;
use OpenJourney\LaravelJourneyBuilder\Http\Middleware\DashboardAccess;
use Illuminate\Support\Facades\Route;
use OpenJourney\LaravelJourneyBuilder\Http\Controllers\SmsProviderController;
use OpenJourney\LaravelJourneyBuilder\Http\Controllers\ChannelProviderController;
use OpenJourney\LaravelJourneyBuilder\Http\Controllers\ChannelLogsController;

Route::middleware(['web', DashboardAccess::class])->prefix(config('journey_builder.route_prefix', 'journeys'))->name('jb.')->group(function () {
        Route::get('/',[JourneyBuilderController::class,'index'])->name('index');
        Route::get('/sms',[JourneyBuilderController::class,'smsIndex'])->name('sms');
        Route::get('/monitoring',[JourneyBuilderController::class,'monitoring'])->name('monitoring');
        Route::get('/channels/logs',[ChannelLogsController::class,'index'])->name('channels.logs');
        Route::get('/channels/{kind}/providers',[ChannelProviderController::class,'index'])->where('kind','email|webpush')->name('channels.index');
        Route::post('/channels/{kind}/providers',[ChannelProviderController::class,'store'])->where('kind','email|webpush')->name('channels.store');
        Route::put('/channels/{kind}/providers/{id}',[ChannelProviderController::class,'update'])->where('kind','email|webpush')->whereNumber('id')->name('channels.update');
        Route::post('/channels/{kind}/providers/{id}/default',[ChannelProviderController::class,'makeDefault'])->where('kind','email|webpush')->whereNumber('id')->name('channels.default');
        Route::delete('/channels/{kind}/providers/{id}',[ChannelProviderController::class,'destroy'])->where('kind','email|webpush')->whereNumber('id')->name('channels.destroy');
        Route::get('/sms/providers',[SmsProviderController::class,'index'])->name('sms.providers');
        Route::post('/sms/providers',[SmsProviderController::class,'store'])->name('sms.providers.store');
        Route::put('/sms/providers/{id}',[SmsProviderController::class,'update'])->whereNumber('id')->name('sms.providers.update');
        Route::post('/sms/providers/{id}/default',[SmsProviderController::class,'makeDefault'])->whereNumber('id')->name('sms.providers.default');
        Route::delete('/sms/providers/{id}',[SmsProviderController::class,'destroy'])->whereNumber('id')->name('sms.providers.destroy');
        Route::post('/flows',[JourneyBuilderController::class,'create'])->name('create');
        Route::delete('/flows/{id}',[JourneyBuilderController::class,'destroy'])->whereNumber('id')->name('delete');
        Route::post('/flows/{id}/restore',[JourneyBuilderController::class,'restore'])->whereNumber('id')->name('restore');
        Route::get('/flows/{id}/edit',[JourneyBuilderController::class,'builder'])->whereNumber('id')->name('builder');
        Route::get('/flows/{id}/export',[JourneyBuilderController::class,'export'])->whereNumber('id')->name('export');
        Route::get('/flows/{id}',[JourneyBuilderController::class,'show'])->whereNumber('id')->name('show');
        Route::put('/flows/{id}',[JourneyBuilderController::class,'save'])->whereNumber('id')->name('save');
        Route::put('/flows/{id}/nodes/{nodeId}/token',[JourneyBuilderController::class,'apiToken'])->whereNumber('id')->where('nodeId','[A-Za-z0-9_-]+')->name('token');
        Route::post('/flows/{id}/enrollment-mode',[JourneyBuilderController::class,'enrollmentMode'])->whereNumber('id')->name('enrollment.mode');
        Route::post('/flows/{id}/enroll-user',[JourneyBuilderController::class,'enrollUser'])->whereNumber('id')->name('enroll.user');
        Route::post('/flows/{id}/status',[JourneyBuilderController::class,'status'])->whereNumber('id')->name('status');
        Route::post('/flows/{id}/runs/{runId}/retry',[JourneyBuilderController::class,'retryRun'])->whereNumber('id')->whereNumber('runId')->name('retry.run');
});
