<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\TicketNotification;
use App\Services\Push\FcmClient;
use App\Services\Push\FcmResult;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Push notifications to the mobile app, through Firebase. The title and text
 * are the same as the browser push, so every message is written once.
 */
class MobilePushChannel
{
    public function __construct(protected FcmClient $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof TicketNotification || ! $this->fcm->configured()) {
            return;
        }

        $push = $notification->toWebPush($notifiable)->toArray();
        $ticket = $notification->ticket;

        $message = [
            'notification' => ['title' => (string) $push['title'], 'body' => (string) $push['body']],
            // Read by the app to open the ticket when the notification is tapped.
            'data' => ['reference' => (string) $ticket->reference, 'type' => class_basename($notification)],
            'android' => [
                'priority' => 'high',
                'ttl' => '86400s',
                // Same tag as the browser push: a busy ticket shows one notification, not a stack.
                'notification' => ['tag' => (string) $ticket->reference, 'channel_id' => 'ticket_alerts'],
            ],
        ];

        $notifiable->deviceTokens->each(function (DeviceToken $device) use ($message) {
            $result = $this->fcm->send($device->token, $message);

            if ($result === FcmResult::DeadToken) {
                $device->delete();
            } elseif ($result === FcmResult::Failed) {
                Log::warning('Mobile push failed', ['device' => $device->id]);
            }
        });
    }
}
