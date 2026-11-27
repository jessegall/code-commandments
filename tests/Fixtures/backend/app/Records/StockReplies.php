<?php

namespace Shop\Records;

use JesseGall\CodeCommandments\Testing\Sinful;

final class StockReplies
{
    /**
     * @param  list<CallInput>  $calls
     * @return list<ReplyData>
     */
    #[Sinful(RepeatedRecordShape::class)]
    public function closedAll(array $calls, bool $closed): array
    {
        $replies = [];
        foreach ($calls as $call) {
            if ($closed) {
                $replies[] = ReplyData::from(['status' => 'unavailable', 'value' => null, 'token' => $call->token]);
            }
        }

        return $replies;
    }
}
