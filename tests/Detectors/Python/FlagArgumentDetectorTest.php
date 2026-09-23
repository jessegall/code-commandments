<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\FlagArgumentDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class FlagArgumentDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new FlagArgumentDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'an if/else on a bool' => ["def render(order, compact: bool) -> str:\n    if compact:\n        return order.sku\n    else:\n        return order.describe()\n"];
        yield 'a negated flag on a method' => ["class Mailer:\n    def send(self, order, *, draft: bool = False) -> None:\n        if not draft:\n            self.outbox.push(order)\n        else:\n            self.drafts.append(order)\n"];
        yield 'a conditional expression' => ["def label(order, short: bool) -> str:\n    return order.code() if short else order.describe()\n"];
        yield 'a docstring above the branch' => ["def export(rows, gzip: bool) -> bytes:\n    \"\"\"Export the rows.\"\"\"\n    if gzip:\n        return compress(dump(rows))\n    else:\n        return dump(rows)\n"];
        yield 'None that means all of them' => ["def fields(form, kind: str | None = None) -> list:\n    if kind is None:\n        return list(form.fields)\n    else:\n        return [f for f in form.fields if f.kind == kind]\n"];
        yield 'the else written as the rest of the body' => ["def label(order, short: bool) -> str:\n    if short:\n        return order.code()\n    return order.describe()\n"];
        yield 'Optional that means all of them' => ["from typing import Optional\n\ndef rows(table, owner: Optional[str] = None) -> list:\n    if owner is not None:\n        return table.owned_by(owner)\n    else:\n        return table.all()\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'work around the branch' => ["def render(order, compact: bool) -> str:\n    head = order.sku\n    if compact:\n        return head\n    else:\n        return head + order.describe()\n"];
        yield 'a guard with no else' => ["def save(row, force: bool) -> None:\n    if force:\n        overwrite(row)\n"];
        yield 'a bool mapped to values' => ["def word(enabled: bool) -> str:\n    if enabled:\n        return 'on'\n    else:\n        return 'off'\n"];
        yield 'a conditional between constants' => ["def word(enabled: bool) -> str:\n    return 'on' if enabled else 'off'\n"];
        yield 'an elif ladder' => ["def pick(fast: bool, n: int) -> int:\n    if fast:\n        return quick(n)\n    elif n > 3:\n        return slow(n)\n    else:\n        return medium(n)\n"];
        yield 'a condition richer than the flag' => ["def pick(fast: bool, n: int) -> int:\n    if fast and n > 3:\n        return quick(n)\n    else:\n        return slow(n)\n"];
        yield 'an untyped parameter' => ["def render(order, compact):\n    if compact:\n        return order.sku\n    else:\n        return order.describe()\n"];
        yield 'a required parameter tested for None' => ["def fields(form, kind: str) -> list:\n    if kind is None:\n        return list(form.fields)\n    else:\n        return form.of(kind)\n"];
        yield 'a guard raising before the work' => ["def load(path, strict: bool) -> str:\n    if strict:\n        raise ValueError(path)\n    return path.read_text()\n"];
        yield 'a guard followed by more than one statement' => ["def label(order, short: bool) -> str:\n    if short:\n        return order.code()\n    text = order.describe()\n    return text.strip()\n"];
        yield 'constants written without an else' => ["def word(enabled: bool) -> str:\n    if enabled:\n        return 'on'\n    return 'off'\n"];
        yield 'the given value or a built default' => ["def transport(given: Transport | None = None) -> Transport:\n    if given is not None:\n        return given\n    return Transport(retries=3)\n"];
        yield 'a constructor storing a flag' => ["class Door:\n    def __init__(self, locked: bool) -> None:\n        if locked:\n            self.lock()\n        else:\n            self.unlock()\n"];
    }
}
