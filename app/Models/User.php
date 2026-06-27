<?php

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

#[Fillable(['name', 'email', 'password', 'role', 'slack_id', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    /** Staff member (Erzieher:in) — may manage children, schedules and the board. */
    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
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
}
