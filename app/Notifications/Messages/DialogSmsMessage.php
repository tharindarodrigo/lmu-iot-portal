<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

class DialogSmsMessage
{
    public function __construct(
        public readonly string $body,
        public readonly ?string $mask = null,
        public readonly ?string $campaignName = null,
    ) {}
}
