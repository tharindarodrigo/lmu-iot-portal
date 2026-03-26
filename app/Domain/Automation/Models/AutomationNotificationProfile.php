<?php

declare(strict_types=1);

namespace App\Domain\Automation\Models;

use App\Domain\Shared\Models\Organization;
use App\Domain\Shared\Models\User;
use Database\Factories\Domain\Automation\Models\AutomationNotificationProfileFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutomationNotificationProfile extends Model
{
    /** @use HasFactory<AutomationNotificationProfileFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'bool',
            'recipients' => 'array',
            'legacy_metadata' => 'array',
        ];
    }

    protected static function newFactory(): AutomationNotificationProfileFactory
    {
        return AutomationNotificationProfileFactory::new();
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<AutomationThresholdPolicy, $this> */
    public function thresholdPolicies(): HasMany
    {
        return $this->hasMany(AutomationThresholdPolicy::class, 'notification_profile_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'automation_notification_profile_user')
            ->withTimestamps();
    }

    /**
     * @return Collection<int, User>
     */
    public function notifiableUsers(): Collection
    {
        $this->loadMissing('users');

        return $this->users
            ->filter(function (User $user): bool {
                return match ($this->channel) {
                    'sms' => is_string($user->phone_number) && trim($user->phone_number) !== '',
                    'email' => trim($user->email) !== '',
                    default => false,
                };
            })
            ->values();
    }

    public function recipientCount(): int
    {
        return $this->notifiableUsers()->count();
    }

    /**
     * @return array<int, string>
     */
    public function recipientLabels(): array
    {
        return $this->notifiableUsers()
            ->map(function (User $user): string {
                $route = $this->channel === 'sms'
                    ? (string) $user->phone_number
                    : (string) $user->email;

                return trim($user->name) !== ''
                    ? "{$user->name} ({$route})"
                    : $route;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function normalizedRecipients(): array
    {
        if ($this->relationLoaded('users') || $this->users()->exists()) {
            return $this->notifiableUsers()
                ->map(fn (User $user): string => $this->channel === 'sms'
                    ? (string) $user->phone_number
                    : strtolower((string) $user->email))
                ->values()
                ->all();
        }

        $recipients = $this->getAttribute('recipients');

        if (! is_array($recipients)) {
            return [];
        }

        $normalizedRecipients = [];

        foreach ($recipients as $recipient) {
            if (! is_string($recipient)) {
                continue;
            }

            $trimmedRecipient = trim($recipient);

            if ($trimmedRecipient === '') {
                continue;
            }

            $normalizedRecipients[] = $trimmedRecipient;
        }

        return array_values(array_unique($normalizedRecipients));
    }
}
