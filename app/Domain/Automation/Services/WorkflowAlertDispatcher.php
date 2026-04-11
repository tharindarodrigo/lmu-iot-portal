<?php

declare(strict_types=1);

namespace App\Domain\Automation\Services;

use App\Domain\Alerts\Models\NotificationProfile;
use App\Domain\Automation\Contracts\AutomationAlertChannel;
use App\Notifications\Automation\AutomationWorkflowAlertNotification;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class WorkflowAlertDispatcher
{
    /**
     * @var array<string, AutomationAlertChannel>
     */
    private array $channels;

    public function __construct(
        AutomationEmailAlertChannel $emailAlertChannel,
        AutomationSmsAlertChannel $smsAlertChannel,
    ) {
        $this->channels = [
            $emailAlertChannel->channel() => $emailAlertChannel,
            $smsAlertChannel->channel() => $smsAlertChannel,
        ];
    }

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function dispatch(string $channel, array $recipients, string $subject, string $body, array $context = []): array
    {
        $resolvedChannel = $this->channels[$channel] ?? null;

        if (! $resolvedChannel instanceof AutomationAlertChannel) {
            throw new RuntimeException("Unsupported alert channel [{$channel}].");
        }

        return $resolvedChannel->dispatch(
            recipients: $recipients,
            subject: $subject,
            body: $body,
            context: $context,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function dispatchProfile(
        NotificationProfile $profile,
        string $subject,
        string $body,
        array $context = [],
    ): array {
        $profile->loadMissing('users');

        $users = $profile->notifiableUsers();

        if ($users->isEmpty()) {
            throw new RuntimeException("Notification profile [{$profile->id}] has no notifiable users.");
        }

        $notification = new AutomationWorkflowAlertNotification(
            channel: $profile->channel,
            subject: $subject,
            body: $body,
            context: $context,
            mask: $profile->mask,
            campaignName: $profile->campaign_name,
        );

        Notification::send($users, $notification);

        return [
            'channel' => $profile->channel,
            'notification_profile_id' => $profile->id,
            'recipient_count' => $users->count(),
            'user_ids' => $users->modelKeys(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function supportedChannels(): array
    {
        return array_keys($this->channels);
    }
}
