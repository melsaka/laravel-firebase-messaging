# Laravel Firebase Messaging

[![Latest Version on Packagist](https://img.shields.io/packagist/v/melsaka/laravel-firebase-messaging.svg?style=flat-square)](https://packagist.org/packages/melsaka/laravel-firebase-messaging)
[![Total Downloads](https://img.shields.io/packagist/dt/melsaka/laravel-firebase-messaging.svg?style=flat-square)](https://packagist.org/packages/melsaka/laravel-firebase-messaging)
[![License](https://img.shields.io/packagist/l/melsaka/laravel-firebase-messaging.svg?style=flat-square)](https://packagist.org/packages/melsaka/laravel-firebase-messaging)

A Laravel package for sending Firebase Cloud Messages (FCM) with support for web push notifications, Android, and iOS platforms. This package provides a clean, fluent API for sending notifications to single or multiple devices with automatic token cleanup and error handling.

## Features

- 🚀 **Easy Integration** - Simple setup with Laravel auto-discovery
- 📱 **Multi-Platform Support** - Web, Android, and iOS notifications
- 🔄 **Automatic Token Cleanup** - Invalid tokens are automatically removed
- 🎯 **Flexible Targeting** - Send to single tokens or multiple devices
- 🛠️ **Configurable** - Customizable settings for all platforms
- 🧪 **Testable** - Built with testing in mind
- 📊 **Error Handling** - Comprehensive error handling and reporting

## Requirements

- PHP 8.2 or higher
- Laravel 11.0 or higher
- Firebase project with FCM enabled

## Installation

You can install the package via Composer:

```bash
composer require melsaka/laravel-firebase-messaging
```

### Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=firebase-messaging

php artisan migrate
```

## Configuration

### Environment Variables

Add the following environment variables to your `.env` file:

```env
FIREBASE_CREDENTIALS=/path/to/your/firebase-service-account.json
FIREBASE_PROJECT_ID=your-firebase-project-id
```

### Firebase Setup

1. Go to the [Firebase Console](https://console.firebase.google.com/)
2. Create a new project or select an existing one
3. Go to Project Settings → Service Accounts
4. Click "Generate new private key" to download your service account JSON file
5. Store the JSON file in your Laravel storage directory
6. Update the `FIREBASE_CREDENTIALS` path in your `.env` file

### Configuration File

The package configuration can be customized in `config/firebase-messaging.php`:

```php
return [
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'tokens_table' => 'fcm_tokens',
    'defaults' => [
        'android' => [
            'ttl' => '3600s',
            'priority' => 'normal',
            'color' => '#f45342',
            'sound' => 'default',
        ],
        'apns' => [
            'priority' => '10',
            'badge' => 42,
            'sound' => 'default',
        ],
    ],
];
```

## Usage

### Basic Usage

```php
use Melsaka\LaravelFirebaseMessaging\Facades\FirebaseMessaging;

// Build a notification
$notification = FirebaseMessaging::buildNotificationMessage(
    title: 'Hello World',
    body: 'This is a test notification.',
    attributes: [
        'link' => 'https://your-app.com/page',
        'image' => 'https://your-app.com/image.png',
        'data' => [
            'custom_key' => 'custom_value',
        ]
    ]
);

// Send to a single token
$success = FirebaseMessaging::notify($notification, $fcmToken);

// Send to multiple tokens
$tokens = collect($fcmTokens); // Collection of FCM token objects
$success = FirebaseMessaging::notify($notification, $tokens);
```

### Advanced Usage

#### Send to Specific Token

```php
$notification = [
    'title' => 'Order Update',
    'body' => 'Your order #12345 has been shipped!',
    'link' => 'https://your-app.com/orders/12345',
    'image' => 'https://your-app.com/images/order-shipped.png',
    'data' => [
        'order_id' => '12345',
        'status' => 'shipped'
    ]
];

$success = FirebaseMessaging::notifyToken($notification, $fcmToken);
```

#### Send to Multiple Devices

```php
use App\Models\User;

// Get all FCM tokens for a user
$user = User::find(1);
$tokens = $user->fcmTokens; // Assuming you have this relationship

$notification = FirebaseMessaging::buildNotificationMessage(
    title: 'Welcome Back!',
    body: 'We have new features waiting for you.',
    attributes: [
        'link' => 'https://your-app.com/dashboard',
        'image' => 'https://your-app.com/image.png',
        'data' => [
            'order_id' => '12345',
            'status' => 'shipped'
        ]
    ]
);

$success = FirebaseMessaging::notifyAll($notification, $tokens);
```

#### Using Dependency Injection

```php
use Melsaka\LaravelFirebaseMessaging\Services\FirebaseMessagingService;

class NotificationController extends Controller
{
    public function __construct(
        private FirebaseMessagingService $firebaseMessaging
    ) {}

    public function sendWelcomeNotification(User $user)
    {
        $notification = $this->firebaseMessaging->buildNotificationMessage(
            title: 'Welcome to Our App!',
            body: 'Thanks for joining us. Get started by exploring our features.',
            attributes: [
                'link' => 'https://your-app.com/dashboard',
                'image' => 'https://your-app.com/image.png',
                'data' => [
                    'order_id' => '12345',
                    'status' => 'shipped'
                ]
            ]
        );

        $tokens = $user->fcmTokens;
        
        return $this->firebaseMessaging->notify($notification, $tokens);
    }
}
```

## Database Structure

The package creates an `fcm_tokens` table with the following structure:

```php
Schema::create('fcm_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('fcm_token')->unique();
    $table->string('device_type')->nullable();
    $table->string('device_id')->nullable();
    $table->timestamps();
    
    $table->index(['user_id', 'fcm_token']);
});
```

## Model Relationships

Add FCM token relationships to your User model:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }
}
```

Create an FCM Token model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FcmToken extends Model
{
    protected $fillable = [
        'user_id',
        'fcm_token',
        'device_type',
        'device_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

## Error Handling

The package automatically handles invalid tokens:

- Invalid tokens are automatically removed from the database
- Failed sends return `false` for easy error handling
- Multicast sends clean up invalid tokens after sending

```php
$success = FirebaseMessaging::notify($notification, $tokens);

if (!$success) {
    Log::error('Failed to send notification');
    // Handle the error appropriately
}
```

## Notification Structure

Notifications support the following structure:

```php
$notification = [
    'title' => 'Required: Notification title',
    'body' => 'Required: Notification body',
    'link' => 'Optional: Deep link URL',
    'image' => 'Optional: Image URL for rich notifications',
    'data' => [
        'key1' => 'Optional: Custom data',
        'key2' => 'More custom data'
    ]
];
```

## Platform-Specific Features

### Web Push Notifications
- Automatic click-through URLs
- Custom notification icons
- Rich media support

### Android Notifications
- Custom colors and sounds
- TTL (Time To Live) configuration
- Priority settings

### iOS (APNS) Notifications
- Badge count management
- Custom sounds
- Mutable content support

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Mohamed ElSaka](https://github.com/melsaka)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

If you discover any issues or have questions, please:

1. Check the [documentation](https://github.com/melsaka/laravel-firebase-messaging)
2. Search through [existing issues](https://github.com/melsaka/laravel-firebase-messaging/issues)
3. Create a [new issue](https://github.com/melsaka/laravel-firebase-messaging/issues/new) if needed

---

Made with ❤️ for the Laravel community