<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

/**
 * An item is ABOUT to be accepted (`commandments build accept`) — raised in the process that would
 * release the hold, before it moves anything, which makes it the one orchestration moment a handler may
 * genuinely stop: never settle an item whose receipt says COULD NOT MEASURE, that nobody measured at all,
 * or that measured a tree other than the one in front of you.
 */
final readonly class Accepting extends Vetoable {}
