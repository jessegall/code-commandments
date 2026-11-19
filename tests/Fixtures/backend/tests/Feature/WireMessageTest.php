<?php

namespace Shop\Tests\Feature;

use JesseGall\CodeCommandments\Sins\Backend\ConvertedArgument;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Tests\Support\TargetIds;
use Shop\Wire\HotkeyPressed;
use Shop\Wire\WireMessage;

final class WireMessageTest
{
    #[Righteous(ConvertedArgument::class)]
    public function test_a_raised_message_names_its_target(): void
    {
        $message = WireMessage::raiseFor(HotkeyPressed::class, TargetIds::of(7));

        assert($message instanceof WireMessage);
    }

    #[Righteous(ConvertedArgument::class)]
    public function test_two_targets_get_two_messages(): void
    {
        $first = WireMessage::raiseFor(HotkeyPressed::class, TargetIds::of(1));
        $second = WireMessage::raiseFor(HotkeyPressed::class, TargetIds::of(2));

        assert($first != $second);
    }
}
