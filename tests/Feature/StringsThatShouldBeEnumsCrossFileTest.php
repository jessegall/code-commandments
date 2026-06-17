<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Prophets\Backend\StringsThatShouldBeEnumsProphet;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\TestCase;

class StringsThatShouldBeEnumsCrossFileTest extends TestCase
{
    private ScrollManager $manager;

    private ProphetRegistry $registry;

    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProphetRegistry;
        $this->manager = new ScrollManager($this->registry, new GenericFileScanner);

        $this->fixtureDir = realpath(__DIR__ . '/../Fixtures/Backend/Sinful/StringsThatShouldBeEnumsCrossFile');
        $this->assertNotFalse($this->fixtureDir);

        $this->registry->registerMany('test', [
            StringsThatShouldBeEnumsProphet::class => [],
        ]);
        $this->registry->setScrollConfig('test', [
            'path' => $this->fixtureDir,
            'extensions' => ['php'],
        ]);
    }

    public function test_pattern_3_flags_string_param_when_callers_form_a_closed_set_matching_an_unimported_enum(): void
    {
        $results = $this->manager->judgeScroll('test');

        $broadcasterFile = $this->fixtureDir . '/Broadcaster.php';
        $this->assertTrue($results->has($broadcasterFile), 'Broadcaster.php should be judged.');

        $sin = $this->firstSinFor($results, $broadcasterFile);
        $this->assertNotNull($sin, 'Broadcaster::dispatch($action) should be flagged.');
        $this->assertStringContainsString('$action', $sin->message);
        $this->assertStringContainsString('MirroringAction', $sin->message);
        $this->assertStringContainsString('publish', $sin->message);
        $this->assertStringContainsString('unpublish', $sin->message);
        $this->assertStringContainsString('Import', $sin->suggestion);
        $this->assertStringContainsString('MirroringAction', $sin->suggestion);
    }

    public function test_pattern_3_flags_closed_set_even_when_no_enum_exists(): void
    {
        $results = $this->manager->judgeScroll('test');

        $toggleFile = $this->fixtureDir . '/Toggle.php';
        $this->assertTrue($results->has($toggleFile), 'Toggle.php should be judged.');

        $sin = $this->firstSinFor($results, $toggleFile);
        $this->assertNotNull($sin, 'Toggle::flip($mode) should be flagged.');
        $this->assertStringContainsString('$mode', $sin->message);
        $this->assertStringContainsString('closed set', $sin->message);
        $this->assertStringContainsString("'on'", $sin->message);
        $this->assertStringContainsString("'off'", $sin->message);
        $this->assertStringContainsString('Define', $sin->suggestion);
        $this->assertStringContainsString('Mode', $sin->suggestion);
    }

    public function test_pattern_3_bidirectional_suffix_match_resolves_short_enum_against_long_param(): void
    {
        $results = $this->manager->judgeScroll('test');

        $walkerFile = $this->fixtureDir . '/Walker.php';
        $this->assertTrue($results->has($walkerFile), 'Walker.php should be judged.');

        $sin = $this->firstSinFor($results, $walkerFile);
        $this->assertNotNull($sin, 'Walker::step($broadcastVerb) should be flagged.');
        $this->assertStringContainsString('$broadcastVerb', $sin->message);
        $this->assertStringContainsString('Verb', $sin->message);
    }

    public function test_pattern_1_named_arg_resolves_against_unimported_project_enum(): void
    {
        $consumer = $this->fixtureDir . '/NamedArgConsumer.php';
        file_put_contents($consumer, <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

        class NamedArgConsumer
        {
            public function __construct(
                private readonly Broadcaster $broadcaster,
            ) {}

            public function run(): void
            {
                $this->broadcaster->dispatch(action: 'publish');
            }
        }
        PHP);

        try {
            $results = $this->manager->judgeScroll('test');

            $this->assertTrue($results->has($consumer));
            $sin = $this->firstSinFor($results, $consumer);
            $this->assertNotNull($sin, 'NamedArgConsumer should be flagged.');
            $this->assertStringContainsString("'publish'", $sin->message);
            $this->assertStringContainsString('MirroringAction', $sin->message);
            $this->assertStringContainsString('add `use', $sin->message);
        } finally {
            @unlink($consumer);
        }
    }

    public function test_no_sins_emitted_for_pure_enum_file(): void
    {
        $results = $this->manager->judgeScroll('test');

        $mirroringActionFile = $this->fixtureDir . '/MirroringAction.php';

        if (! $results->has($mirroringActionFile)) {
            $this->addToAssertionCount(1);
            return;
        }

        $judgment = $results->get($mirroringActionFile)->get(StringsThatShouldBeEnumsProphet::class);
        $this->assertNotNull($judgment);
        $this->assertFalse($judgment->isFallen(), 'Enum-only files should never be flagged.');
    }

    public function test_does_not_flag_a_generic_bag_accessor_key(): void
    {
        // Issue #7: ValueBag::asFloat(string $key) is a typed accessor over an
        // open string-keyed bag. Its handful of call-site literals are a sample,
        // not a closed set — the key space is open, so it must not be flagged.
        $results = $this->manager->judgeScroll('test');

        $valueBagFile = $this->fixtureDir . '/ValueBag.php';

        $this->assertNull(
            $this->firstSinFor($results, $valueBagFile),
            'A typed bag accessor key (asFloat($key)) must not be treated as a closed set.',
        );
    }

    private function firstSinFor(\Illuminate\Support\Collection $results, string $file): ?Sin
    {
        if (! $results->has($file)) {
            return null;
        }

        $judgment = $results->get($file)->get(StringsThatShouldBeEnumsProphet::class);

        if ($judgment === null || ! $judgment->isFallen()) {
            return null;
        }

        return $judgment->sins[0] ?? null;
    }
}
