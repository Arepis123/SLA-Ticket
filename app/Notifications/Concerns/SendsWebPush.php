<?php

namespace App\Notifications\Concerns;

use App\Enums\EmailNotification;
use App\Models\User;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Mail unless the user turned this type of email off, plus a browser push for
 * users who enabled it on at least one device. Checking here keeps queued
 * jobs from being created for channels the user does not want.
 */
trait SendsWebPush
{
    /**
     * Which setting on the notification settings page controls this email.
     */
    abstract public function emailType(): EmailNotification;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $channels = $notifiable->wantsEmail($this->emailType()) ? ['mail'] : [];

        if ($notifiable->pushSubscriptions()->exists()) {
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
