<?php

namespace Shop\Records;

use JesseGall\CodeCommandments\Testing\Sinful;

final class LabelReplies
{
    #[Sinful(RepeatedRecordShape::class)]
    public static function printed(CallInput $call, ?string $label): ReplyData
    {
        $status = $label === null ? 'missing' : 'printed';

        return ReplyData::from(['token' => $call->token, 'value' => $label === null ? null : strtoupper($label), 'status' => $status]);
    }

    /**
     * @param  list<string>  $lines
     */
    public static function widest(array $lines): int
    {
        $widest = 0;
        foreach ($lines as $line) {
            $widest = max($widest, mb_strlen($line));
        }

        return $widest;
    }
}
