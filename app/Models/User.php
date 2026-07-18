<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\LogsChanges;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\HasPushSubscriptions;

// role and is_admin are privilege fields — deliberately NOT mass-assignable;
// they are only ever set via explicit forceFill (SSO, UserController, import).
#[Fillable(['name', 'email', 'password', 'slack_id', 'avatar', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, LogsChanges, Notifiable;

    /** @return list<string> */
    protected function activityAttributes(): array
    {
        return ['name', 'email', 'role', 'is_admin'];
    }

    protected function activityLabel(): string
    {
        return $this->name;
    }

    /**
     * Default attribute values (mirroring the migration column defaults).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Parent->value,
        'is_admin' => false,
    ];

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
            'is_admin' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    /** Preferred UI locale for notifications/mail; null → the app default (de). */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Whether this user wants a notification category on a channel ('slack' | 'push').
     * Opt-out model: a missing preference means the channel is ON.
     */
    public function wantsNotification(string $category, string $channel): bool
    {
        return (bool) ($this->notification_preferences[$category][$channel] ?? true);
    }

    /** Staff member (Erzieher:in) — may manage children, schedules and the board. */
    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    /** Admin — a staff member who may also manage users (roles + other admins). */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * The children (Kinder) this user is a parent of.
     *
     * @return BelongsToMany<Child, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class);
    }

    /**
     * Slack DMs go to the user's own Slack id (set on "Sign in with Slack").
     * Users without a Slack id simply don't receive Slack notifications.
     */
    public function routeNotificationForSlack(Notification $notification): ?string
    {
        return $this->slack_id;
    }

    /**
     * Limit to users connected to Slack — i.e. those who can receive DMs.
     *
     * @param  Builder<User>  $query
     */
    public function scopeOnSlack(Builder $query): void
    {
        $query->whereNotNull('slack_id');
    }

    /**
     * Limit to users reachable by any notification channel — a Slack id and/or a
     * web-push subscription. The notification's via() picks the channel(s) per user.
     *
     * @param  Builder<User>  $query
     */
    public function scopeReachable(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereNotNull('slack_id')
            ->orWhereHas('pushSubscriptions'));
    }

    /**
     * Limit to users who are a guardian of at least one child.
     *
     * @param  Builder<User>  $query
     */
    public function scopeGuardians(Builder $query): void
    {
        $query->whereHas('children');
    }
}
