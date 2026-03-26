<?php

declare(strict_types=1);

namespace App\Notifications\Automation;

use App\Notifications\Channels\DialogSmsChannel;
use App\Notifications\Messages\DialogSmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AutomationWorkflowAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly string $channel,
        private readonly string $subject,
        private readonly string $body,
        private readonly array $context = [],
        private readonly ?string $mask = null,
        private readonly ?string $campaignName = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return match ($this->channel) {
            'sms' => [DialogSmsChannel::class],
            default => ['mail'],
        };
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject($this->subject);

        $lines = preg_split('/\R/', trim($this->body)) ?: [];

        if ($lines === []) {
            return $message->line('Automation alert triggered.');
        }

        foreach ($lines as $line) {
            $resolvedLine = trim((string) $line);

            if ($resolvedLine === '') {
                continue;
            }

            $message->line(Str::of($resolvedLine)->toString());
        }

        return $message;
    }

    public function toDialogSms(object $notifiable): DialogSmsMessage
    {
        return new DialogSmsMessage(
            body: $this->body,
            mask: $this->mask,
            campaignName: $this->campaignName,
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            ...$this->context,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'body' => $this->body,
        ];
    }
}
