<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

/**
 * A worker has filed a receipt and is waiting to be judged (`commandments build report`) — the receipt
 * already on disk and the board already saying `reported`, so a handler here can only say something about
 * it: that a receipt COULD NOT MEASURE, that a lane's number was taken against a base older than the last
 * merge, that nobody ran a check at all.
 */
final readonly class Reported extends Event {}
