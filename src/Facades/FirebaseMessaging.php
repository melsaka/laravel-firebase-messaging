<?php

namespace Melsaka\LaravelFirebaseMessaging\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool notify(array $notification, string|\Illuminate\Support\Collection $fcmTokens)
 * @method static bool notifyToken(array $notification, string $fcmToken)
 * @method static bool notifyAll(array $notification, \Illuminate\Support\Collection $fcmTokens)
 * @method static array buildNotificationMessage(string $title, string $body, string|null $link = null, array $data = [])
 */
class FirebaseMessaging extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Melsaka\LaravelFirebaseMessaging\Services\FirebaseMessagingService::class;
    }
}