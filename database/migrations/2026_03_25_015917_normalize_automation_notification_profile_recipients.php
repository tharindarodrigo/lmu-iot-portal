<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_notification_profile_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('automation_notification_profile_id')
                ->constrained('automation_notification_profiles')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['automation_notification_profile_id', 'user_id'],
                'automation_notification_profile_user_profile_user_unique',
            );
        });

        DB::table('automation_notification_profiles')
            ->select(['id', 'organization_id', 'name', 'channel', 'recipients'])
            ->orderBy('id')
            ->get()
            ->each(function (object $profile): void {
                $recipients = $this->normalizeRecipients(
                    values: $this->decodeJsonArray($profile->recipients ?? null),
                    channel: (string) ($profile->channel ?? ''),
                );

                if ($recipients === []) {
                    return;
                }

                $userIds = [];
                $unmappedRecipients = [];

                foreach ($recipients as $recipient) {
                    $userId = $this->resolveOrganizationUserId(
                        organizationId: (int) $profile->organization_id,
                        channel: (string) $profile->channel,
                        recipient: $recipient,
                    );

                    if ($userId === null) {
                        $unmappedRecipients[] = $recipient;

                        continue;
                    }

                    $userIds[$userId] = $userId;
                }

                if ($unmappedRecipients !== []) {
                    throw new RuntimeException(sprintf(
                        'Automation notification profile [%s] has recipients that cannot be mapped to organization users: %s',
                        $profile->name ?? $profile->id,
                        implode(', ', $unmappedRecipients),
                    ));
                }

                foreach (array_values($userIds) as $userId) {
                    DB::table('automation_notification_profile_user')->updateOrInsert(
                        [
                            'automation_notification_profile_id' => $profile->id,
                            'user_id' => $userId,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_notification_profile_user');
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function normalizeRecipients(array $values, string $channel): array
    {
        $normalizedRecipients = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $recipient = trim($value);

            if ($recipient === '') {
                continue;
            }

            if ($channel === 'sms') {
                $normalizedPhoneNumber = $this->normalizePhoneNumber($recipient);

                if ($normalizedPhoneNumber === null) {
                    continue;
                }

                $normalizedRecipients[$normalizedPhoneNumber] = $normalizedPhoneNumber;

                continue;
            }

            if ($channel === 'email') {
                $normalizedEmail = strtolower($recipient);

                if (filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
                    continue;
                }

                $normalizedRecipients[$normalizedEmail] = $normalizedEmail;
            }
        }

        return array_values($normalizedRecipients);
    }

    private function resolveOrganizationUserId(int $organizationId, string $channel, string $recipient): ?int
    {
        $query = DB::table('users')
            ->join('organization_user', 'organization_user.user_id', '=', 'users.id')
            ->where('organization_user.organization_id', $organizationId)
            ->select('users.id');

        if ($channel === 'sms') {
            $query->where('users.phone_number', $recipient);
        } else {
            $query->whereRaw('LOWER(users.email) = ?', [strtolower($recipient)]);
        }

        $userId = $query->value('users.id');

        return is_numeric($userId) ? (int) $userId : null;
    }

    private function normalizePhoneNumber(string $recipient): ?string
    {
        $normalized = preg_replace('/\s+/', '', trim($recipient));

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        if (preg_match('/^\+[1-9][0-9]{7,14}$/', $normalized) === 1) {
            return $normalized;
        }

        if (preg_match('/^[1-9][0-9]{7,14}$/', $normalized) === 1) {
            return '+'.$normalized;
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
};
