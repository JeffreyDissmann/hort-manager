<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
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
#[Fillable(['name', 'email', 'password', 'slack_id', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

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
        ];
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
     * Limit to users who are a guardian of at least one child.
     *
     * @param  Builder<User>  $query
     */
    public function scopeGuardians(Builder $query): void
    {
        $query->whereHas('children');
    }
}
