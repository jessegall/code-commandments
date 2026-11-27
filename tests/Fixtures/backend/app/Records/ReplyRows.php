<?php

namespace Shop\Records;

use JesseGall\CodeCommandments\Testing\Righteous;

final class ReplyRows
{
    /**
     * @return list<ReplyData>
     */
    #[Righteous(RepeatedRecordShape::class)]
    public function samples(CallInput $first, CallInput $second): array
    {
        return [
            ReplyData::from(['token' => $first->token, 'status' => 'queued', 'value' => 'a']),
            ReplyData::from(['token' => $second->token, 'status' => 'sent', 'value' => 'b']),
        ];
    }
}
