<?php

namespace Shop\Records;

use JesseGall\CodeCommandments\Testing\Sinful;

final class QuoteReplies
{
    #[Sinful(RepeatedRecordShape::class)]
    public function refused(CallInput $call): ReplyData
    {
        return ReplyData::from([
            'token' => $call->token,
            'status' => 'failed',
            'value' => null,
        ]);
    }

    public function expiresIn(int $minutes): string
    {
        return match (true) {
            $minutes <= 0 => 'expired',
            $minutes < 60 => "{$minutes}m",
            default => intdiv($minutes, 60) . 'h',
        };
    }
}
