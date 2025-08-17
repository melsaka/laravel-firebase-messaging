<?php

namespace Melsaka\LaravelFirebaseMessaging\Services;

use Illuminate\Support\Collection;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\WebPushConfig;
use Melsaka\LaravelFirebaseMessaging\Contracts\FcmTokenServiceInterface;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class FirebaseMessagingService
{
    protected array $defaults;

    public function __construct(
        private FcmTokenServiceInterface $fcmService,
        private Messaging $messaging
    ) {
        $this->defaults = config('firebase-messaging.defaults', []);
    }
    
    /**
     * Send a notification to one or multiple FCM tokens.
     * This method determines whether to send a notification to a single token or multiple tokens.
     *
     * @param array $notification The notification data (title, body, link, etc.).
     * @param string|Collection $fcmTokens The FCM token(s) to send the notification to.
     * @return bool True if the notification was sent successfully, false otherwise.
     */
    public function notify(array $notification, string|Collection $fcmTokens): bool
    {
        if (is_string($fcmTokens)) {
            return $this->notifyToken($notification, $fcmTokens);
        }

        return $this->notifyAll($notification, $fcmTokens);
    }

    /**
     * Send a notification to a single FCM token.
     * This method sends a notification to a specific FCM token and handles any errors by deleting invalid tokens.
     *
     * @param array $notification The notification data (title, body, link, etc.).
     * @param string $fcmToken The FCM token to send the notification to.
     * @return bool True if the notification was sent successfully, false otherwise.
     */
    public function notifyToken(array $notification, string $fcmToken): bool
    {
        try {
            $message = $this->buildMessage($notification)->toToken($fcmToken);

            $report = $this->messaging->send($message);

            return true;
        } catch (\Exception $e) {
            // Delete the invalid token if an error occurs.
            $this->fcmService->deleteByToken($fcmToken);

            return false;
        }
    }

    /**
     * Send a notification to multiple FCM tokens.
     * This method sends a notification to a list of FCM tokens and cleans up invalid tokens after sending.
     *
     * @param array $notification The notification data (title, body, link, etc.).
     * @param Collection $fcmTokens The list of FCM tokens to send the notification to.
     * @return bool True if the notification was sent successfully, false otherwise.
     */
    public function notifyAll(array $notification, Collection $fcmTokens): bool
    {
        $fcmIds = $fcmTokens->pluck('fcm_token')->filter();

        if ($fcmIds->isEmpty()) {
            return false;
        }

        try {
            $message = $this->buildMessage($notification);

            $report = $this->messaging->sendMulticast($message, $fcmIds->toArray());

            // Clean up invalid tokens after sending the notification.
            $this->cleanUpInvalidTokens($report);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Build a notification message array with the required fields.
     * This method constructs a notification message with a title, body, link, and optional data.
     *
     * @param string $title The title of the notification.
     * @param string $body The body of the notification.
     * @param array $attributes Additional data to include in the notification.
     * @return array The constructed notification message.
     */
    public function buildNotificationMessage(string $title, string $body, array $attributes = []): array
    {
        // Ensure required fields
        $message = [
            'title' => $title,
            'body'  => $body,
            'link'  => Arr::get($attributes, 'link', config('app.url')),
        ];

        // Merge optional attributes (only specific ones you want to allow)
        $optional = Arr::only($attributes, ['image']);
        $message = array_merge($message, $optional);

        // Attach custom data if present
        if (!empty($attributes['data'] ?? [])) {
            $message['data'] = $attributes['data'];
        }

        return $message;
    }

    /**
     * Clean up invalid FCM tokens after sending a multicast notification.
     * This method deletes invalid and unknown tokens from the database.
     *
     * @param MulticastSendReport $report The report from the multicast send operation.
     * @return bool True if the invalid tokens were deleted successfully, false otherwise.
     */
    private function cleanUpInvalidTokens(MulticastSendReport $report): bool
    {
        $invalidTokens = array_merge($report->invalidTokens(), $report->unknownTokens());

        return $this->fcmService->deleteTokens($invalidTokens);
    }

    /**
     * Build a CloudMessage object for sending notifications.
     * This method constructs a CloudMessage with notification, data, and platform-specific configurations.
     *
     * @param array $notification The notification data (title, body, link, etc.).
     * @return CloudMessage The constructed CloudMessage object.
     */
    private function buildMessage(array $notification): CloudMessage
    {
        return CloudMessage::new()
            ->withNotification($this->buildNotification($notification))
            ->withData($this->getNotificationData($notification))
            ->withWebPushConfig($this->getWebPushConfig($notification))
            ->withAndroidConfig($this->getAndroidConfig($notification))
            ->withApnsConfig($this->getApnsConfig($notification));
    }

    /**
     * Build a Notification object for the CloudMessage.
     * This method constructs a Notification object with the title and body from the notification data.
     *
     * @param array $notification The notification data (title, body, image).
     * @return Notification The constructed Notification object.
     */
    private function buildNotification(array $notification): Notification
    {
        // Ensure required keys exist and are not empty
        foreach (['title', 'body'] as $required) {
            if (empty($notification[$required])) {
                throw new InvalidArgumentException("Notification {$required} is required.");
            }
        }

        // Extract required + optional fields
        $base = Arr::only($notification, ['title', 'body', 'image']);

        return Notification::fromArray($base);
    }

    /**
     * Extract additional data from the notification array.
     * This method retrieves the optional data field from the notification array.
     *
     * @param array $notification The notification data (title, body, link, etc.).
     * @return array The additional data included in the notification.
     */
    private function getNotificationData(array $notification): array
    {
        return isset($notification['data']) ? $notification['data'] : [];
    }

    /**
     * Build a WebPushConfig object for the CloudMessage.
     * This method constructs a WebPushConfig object with the title, body, and link from the notification data.
     *
     * @param array $notification The notification data (title, body, link, etc.).
     * @return WebPushConfig The constructed WebPushConfig object.
     */
    private function getWebPushConfig(array $notification): WebPushConfig
    {
        return WebPushConfig::fromArray([
            'notification' => Arr::only($notification, ['title', 'body']),
            'fcm_options' => [
                'link' => $notification['link'] ?? config('app.url'),
            ],
        ]);
    }

    /**
     * Build an AndroidConfig object for the CloudMessage.
     * This method constructs an AndroidConfig object with the title, body, and other Android-specific settings.
     *
     * @param array $notification The notification data (title, body, link, etc.).
     * @return AndroidConfig The constructed AndroidConfig object.
     */
    private function getAndroidConfig(array $notification): AndroidConfig
    {
        $baseDefaults = [
            'ttl'      => '3600s',
            'priority' => 'normal',
            'color'    => '#f45342',
            'sound'    => 'default',
        ];

        // Merge with user-defined defaults (if any)
        $defaults = array_merge($baseDefaults, $this->defaults['android'] ?? []);

        return AndroidConfig::fromArray([
            'ttl'       => $defaults['ttl'],
            'priority'  => $defaults['priority'],
            'notification' => [
                'title' => $notification['title'],
                'body'  => $notification['body'],
                'color' => $defaults['color'],
                'sound' => $defaults['sound'],
            ],
        ]);
    }

    /**
     * Build an ApnsConfig object for the CloudMessage.
     * This method constructs an ApnsConfig object with the title, body, and other iOS-specific settings.
     *
     * @param array $notification The notification data (title, body, link, etc.).
     * @return ApnsConfig The constructed ApnsConfig object.
     */
    private function getApnsConfig(array $notification): ApnsConfig
    {
        $baseDefaults = [
            'priority' => '10',
            'badge'    => 42,
            'sound'    => 'default',
        ];

        // Merge with user-defined defaults (if any)
        $defaults = array_merge($baseDefaults, $this->defaults['apns'] ?? []);

        return ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => $defaults['priority'],
            ],
            'payload' => [
                'aps' => [
                    'alert' => [
                        'title' => $notification['title'],
                        'body'  => $notification['body'],
                    ],
                    'badge' => $defaults['badge'],
                    'sound' => $defaults['sound'],
                    'mutable-content' => 1,
                ],
            ],
        ]);
    }
}