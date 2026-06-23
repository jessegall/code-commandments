<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\LongDocblockProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class LongDocblockProphetTest extends TestCase
{
    private LongDocblockProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new LongDocblockProphet();
    }

    public function test_passes_one_sentence_class_docblock(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

/**
 * Broadcasts publishable changes to writable+mirroring siblings.
 */
class OrderBroadcaster
{
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/OrderBroadcaster.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_method_with_narrative_then_tags(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class OrderBroadcaster
{
    /**
     * Broadcast a catalog resource publish to every writable+mirroring sibling.
     *
     * @param  Publishable  $publishable
     */
    public function broadcast($publishable): void {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/OrderBroadcaster.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_warns_on_numbered_list_in_docblock(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

/**
 * Broadcasts publishable changes to writable+mirroring siblings.
 *
 * The five rules, applied in order on every dispatch:
 *   1. Suppression - bulk import middleware brackets entire imports.
 *   2. Master gate - when a shop has a master-source channel.
 */
class OrderBroadcaster
{
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/OrderBroadcaster.php', $content);

        $this->assertTrue($judgment->isRighteous());
        $this->assertTrue($judgment->hasWarnings());
        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('numbered list', $judgment->warnings[0]->message);
        $this->assertStringContainsString('class OrderBroadcaster', $judgment->warnings[0]->message);
    }

    public function test_warns_on_bulleted_list_in_docblock(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class OrderBroadcaster
{
    /**
     * Broadcast a catalog resource publish.
     *
     * - Suppresses bulk imports
     * - Skips the originating channel
     */
    public function broadcast(): void {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/OrderBroadcaster.php', $content);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertStringContainsString('bulleted list', $judgment->warnings[0]->message);
        $this->assertStringContainsString('OrderBroadcaster::broadcast()', $judgment->warnings[0]->message);
    }

    public function test_warns_on_multi_paragraph_narrative(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

/**
 * Receipts for card payments initiated through an integration.
 *
 * Populated at initiate-time so a frontend crash between the
 * customer tapping their card and the cashier-side success
 * transition can be reconciled on the next page load.
 */
class PaymentReceipts
{
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/PaymentReceipts.php', $content);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertStringContainsString('multiple paragraphs', $judgment->warnings[0]->message);
    }

    public function test_warns_on_long_narrative_without_lists(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

/**
 * This class does several things at once.
 * It first validates the payload against the spec.
 * Then it dispatches to the relevant handler.
 * Then it persists the resulting state to disk.
 * Then it notifies listeners that work has happened.
 */
class Orchestrator
{
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/Orchestrator.php', $content);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertStringContainsString('narrative', $judgment->warnings[0]->message);
    }

    public function test_warns_on_property_with_multi_sentence_docblock(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class PaymentReceipts
{
    /**
     * Receipts for card payments initiated through an integration.
     *
     * Populated at initiate-time so a frontend crash between the
     * customer tapping their card and the cashier-side success
     * transition can be reconciled on the next page load.
     */
    public array $associatedPayments = [];
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/PaymentReceipts.php', $content);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertStringContainsString('PaymentReceipts property $associatedPayments', $judgment->warnings[0]->message);
        $this->assertStringContainsString('multiple paragraphs', $judgment->warnings[0]->message);
    }

    public function test_ignores_param_and_return_tags_when_measuring_narrative(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class Fanout
{
    /**
     * Dispatch the command to each qualifying sibling for the publishable.
     *
     * @param  Publishable  $publishable
     * @param  callable(Shop): iterable<ShopChannel>  $selectChannels
     * @param  MirroringAction  $action
     * @param  callable(ShopChannel): object  $command
     *
     * @throws ModelNotFoundException when the shop projection is gone mid-fanout.
     */
    private function fanout($publishable, callable $selectChannels, $action, callable $command): void {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/Fanout.php', $content);

        $this->assertTrue($judgment->isRighteous());
        $this->assertFalse($judgment->hasWarnings());
    }

    public function test_passes_short_property_docblock(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class Cashier
{
    /**
     * Slug of the integration that owns `$readerId`.
     */
    public string|null $readerIntegration = null;
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/Cashier.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_files_without_docblocks(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class Plain
{
    public function go(): void {}
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/Plain.php', $content);

        $this->assertTrue($judgment->isRighteous());
        $this->assertFalse($judgment->hasWarnings());
    }

    public function test_configurable_narrative_threshold(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

/**
 * Line one.
 * Line two.
 * Line three.
 * Line four.
 */
class Wordy
{
}
PHP;

        $this->prophet->configure(['max_narrative_lines' => 10]);
        $this->assertTrue($this->prophet->judge('/app/Services/Wordy.php', $content)->isRighteous());

        $this->prophet = new LongDocblockProphet();
        $this->prophet->configure(['max_narrative_lines' => 2]);
        $this->assertTrue($this->prophet->judge('/app/Services/Wordy.php', $content)->hasWarnings());
    }

    public function test_detects_docblocks_on_interfaces(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Contracts;

/**
 * A repository of things.
 *
 * It holds them, fetches them, and removes them on demand.
 */
interface Repository
{
    public function find(string $id): mixed;
}
PHP;

        $judgment = $this->prophet->judge('/app/Contracts/Repository.php', $content);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertStringContainsString('interface Repository', $judgment->warnings[0]->message);
    }

    public function test_exempts_enums_via_exempt_classes(): void
    {
        // Enums are exempt at the scroll boundary: EnumCaseMustBeDocumented
        // endorses a `{@see Enum::Case}` bullet per case in the class docblock
        // (necessarily multi-line), so flagging it would contradict that rule.
        $this->assertSame([\UnitEnum::class], $this->prophet->exemptClasses());
    }

    public function test_provides_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
