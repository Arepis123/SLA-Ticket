<?php

namespace App\Livewire\Settings;

use App\Concerns\ManagesPushSubscriptions;
use App\Enums\EmailNotification;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Notification settings')]
class Notifications extends Component
{
    use ManagesPushSubscriptions;

    /**
     * On/off per email type the user can receive, keyed by type.
     *
     * @var array<string, bool>
     */
    public array $emails = [];

    public function mount(): void
    {
        $user = Auth::user();

        foreach (EmailNotification::for($user) as $email) {
            $this->emails[$email->value] = $user->wantsEmail($email);
        }
    }

    /**
     * Saved as soon as a switch is flipped.
     */
    public function updatedEmails(mixed $value, string $key): void
    {
        $user = Auth::user();
        $email = EmailNotification::tryFrom($key);

        abort_unless($email?->reaches($user), 403);

        $on = (bool) $value;
        $muted = collect($user->muted_emails ?? [])->reject(fn (string $type) => $type === $email->value);

        $user->update(['muted_emails' => ($on ? $muted : $muted->push($email->value))->values()->all()]);

        Flux::toast(text: __($on ? 'Emails for “:type” turned on.' : 'Emails for “:type” turned off.', ['type' => $email->label()]));
    }

    /**
     * Turn off push everywhere, for devices the user no longer has to hand.
     */
    public function unsubscribeAll(): void
    {
        Auth::user()->pushSubscriptions()->delete();

        $this->dispatch('push-subscriptions-cleared');

        Flux::toast(text: __('Browser notifications turned off on all devices.'));
    }

    #[Computed]
    public function deviceCount(): int
    {
        return Auth::user()->pushSubscriptions()->count();
    }
}
