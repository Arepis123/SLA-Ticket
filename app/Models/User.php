<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property int|null $company_id
 * @property bool $is_client_admin
 * @property bool $is_active
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 */
#[Fillable(['name', 'email', 'password', 'role', 'company_id', 'is_client_admin', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_client_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function reportedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'reporter_id');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    /**
     * @return HasMany<SavedView, $this>
     */
    public function savedViews(): HasMany
    {
        return $this->hasMany(SavedView::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * A member of the support team: the people tickets get assigned to.
     */
    public function isInternal(): bool
    {
        return $this->role === UserRole::Internal;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    /**
     * A client who sees every ticket of their company, not only the ones they reported.
     */
    public function isClientAdmin(): bool
    {
        return $this->isClient() && $this->is_client_admin;
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function staff(Builder $query): void
    {
        $query->whereIn('role', [UserRole::Admin, UserRole::Internal])->where('is_active', true);
    }

    /**
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function admins(Builder $query): void
    {
        $query->where('role', UserRole::Admin)->where('is_active', true);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
