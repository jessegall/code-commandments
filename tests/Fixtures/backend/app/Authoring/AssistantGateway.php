<?php

namespace Shop\Authoring;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\PlaceholderFilledData;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Every failure path invents the message it does not have. The envelope was designed for the happy
 * path and the error cases are wearing it — `?string $message`, or a separate failure type, is what
 * these two outcomes actually need. Nothing downstream can tell an empty reply from a missing one.
 */
final class AssistantGateway
{
    /** @var list<string> */
    private array $refusals = [];

    #[Sinful(PlaceholderFilledData::class)]
    public function refuse(string $reason, bool $retryable): AiReplyData
    {
        $this->refusals[] = $reason;

        if ($retryable) {
            return new AiReplyData(message: '', success: false, error: 'retry_later');
        }

        return new AiReplyData(message: '', success: false, error: $reason);
    }

    /** @return list<string> */
    public function refusals(): array
    {
        return $this->refusals;
    }
}
