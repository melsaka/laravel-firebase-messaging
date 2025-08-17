<?php

namespace Melsaka\LaravelFirebaseMessaging\Providers;

use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging;
use Melsaka\LaravelFirebaseMessaging\Services\FirebaseMessagingService;
use Melsaka\LaravelFirebaseMessaging\Services\FcmTokenService;
use Melsaka\LaravelFirebaseMessaging\Contracts\FcmTokenServiceInterface;

class FirebaseMessagingServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/firebase-messaging.php',
            'firebase-messaging'
        );

        $this->app->singleton(Messaging::class, function ($app) {
            $factory = (new Factory)
                ->withServiceAccount(config('firebase-messaging.credentials'))
                ->withProjectId(config('firebase-messaging.project_id'));

            return $factory->createMessaging();
        });

        $this->app->bind(FcmTokenServiceInterface::class, FcmTokenService::class);

        $this->app->singleton(FirebaseMessagingService::class, function ($app) {
            return new FirebaseMessagingService(
                $app->make(FcmTokenServiceInterface::class),
                $app->make(Messaging::class)
            );
        });
    }

    public function boot()
    {
        $files = [
            __DIR__ . '/../../config/firebase-messaging.php' => config_path('firebase-messaging.php'),
        ];

        $pattern = database_path('migrations/*_create_fcm_tokens_table.php');

        if (count(glob($pattern)) === 0) {
            $files[__DIR__.'/../../database/migrations/create_fcm_tokens_table.php.stub'] = database_path('migrations/'.date('Y_m_d_His', time()).'_create_fcm_tokens_table.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes($files, 'firebase-messaging');
        }
    }
}