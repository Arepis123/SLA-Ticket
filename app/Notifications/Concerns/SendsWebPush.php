<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Mail always, plus a browser push for users who enabled it on at least one
 * device. Checking here keeps a queued push job from being created for
 * everyone else.
 */
trait SendsWebPush
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if ($notifiable instanceof User && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * A push message that opens $url when clicked. Pushes with the same tag
     * replace each other, so a busy ticket shows one popup, not a stack.
     */
    protected function pushMessage(string $title, string $body, string $url, string $tag): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($title)
            ->body($body)
            ->icon(asset('apple-touch-icon.png'))
            ->tag($tag)
            ->renotify()
            ->data(['url' => $url])
            ->options(['TTL' => 60 * 60 * 24]);
    }
}
