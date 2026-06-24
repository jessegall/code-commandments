<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Config;

use JesseGall\CodeCommandments\Prophets\Backend\NoCompactProphet;
use JesseGall\CodeCommandments\Prophets\Backend\NoRawLiteralProphet;
use JesseGall\CodeCommandments\Results\Severity;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use PHPUnit\Framework\TestCase;

/**
 * User-owned severity: every prophet is its own fluent config builder, and the
 * stake its findings carry (Sin / Admonition / Off) is the user's to set — per
 * prophet, or per doctrine/band as a default.
 */
class SeverityConfigTest extends TestCase
{
    private const EMPTY_ARRAY_DEFAULT = '<?php class C { public function __construct(private array $x = []) {} }';

    public function test_make_builds_a_configured_instance(): void
    {
        $prophet = NoRawLiteralProphet::make()->flagEmptyArray()->initializersOnly();

        $this->assertInstanceOf(NoRawLiteralProphet::class, $prophet);
        $this->assertGreaterThan(0, count($prophet->judge('/x.php', self::EMPTY_ARRAY_DEFAULT)->sins));
    }

    public function test_admonition_override_demotes_a_sin_to_a_warning(): void
    {
        $prophet = NoRawLiteralProphet::make()->severity(Severity::Admonition)->flagEmptyArray();

        $judgment = $prophet->applyConfiguredSeverity($prophet->judge('/x.php', self::EMPTY_ARRAY_DEFAULT));

        $this->assertSame([], $judgment->sins);
        $this->assertCount(1, $judgment->warnings);
    }

    public function test_sin_override_keeps_a_sin_a_sin(): void
    {
        $prophet = NoRawLiteralProphet::make()->severity(Severity::Sin)->flagEmptyArray();

        $judgment = $prophet->applyConfiguredSeverity($prophet->judge('/x.php', self::EMPTY_ARRAY_DEFAULT));

        $this->assertCount(1, $judgment->sins);
        $this->assertSame([], $judgment->warnings);
    }

    public function test_disabled_silences_every_finding(): void
    {
        $prophet = NoRawLiteralProphet::make()->disabled()->flagEmptyArray();

        $this->assertTrue($prophet->isDisabled());
        $judgment = $prophet->applyConfiguredSeverity($prophet->judge('/x.php', self::EMPTY_ARRAY_DEFAULT));
        $this->assertSame([], $judgment->sins);
        $this->assertSame([], $judgment->warnings);
    }

    public function test_legacy_severity_config_string_is_honoured(): void
    {
        $prophet = (new NoRawLiteralProphet)->configure(['severity' => 'admonition', 'flag_empty_array' => true]);

        $this->assertSame(Severity::Admonition, $prophet->severityOverride());
    }

    public function test_warning_remains_an_alias_for_admonition(): void
    {
        $this->assertSame(Severity::Admonition, Severity::fromName('warning'));
    }

    public function test_registry_uses_a_fluent_instance_as_is(): void
    {
        $registry = new ProphetRegistry;
        $registry->registerMany('backend', [NoRawLiteralProphet::make()->severity(Severity::Sin)]);

        $prophet = $registry->getProphets('backend')->first();

        $this->assertSame(Severity::Sin, $prophet->severityOverride());
    }

    public function test_doctrine_level_default_layers_under_an_unset_prophet(): void
    {
        $registry = new ProphetRegistry;
        $registry->registerMany('backend', [NoCompactProphet::class]); // idiomatic-iteration
        $registry->setScrollConfig('backend', ['doctrines' => ['idiomatic-iteration' => 'off']]);

        $prophet = $registry->getProphets('backend')->first();

        $this->assertSame(Severity::Off, $prophet->severityOverride());
    }

    public function test_explicit_prophet_override_beats_a_doctrine_default(): void
    {
        $registry = new ProphetRegistry;
        $registry->registerMany('backend', [NoCompactProphet::make()->severity(Severity::Sin)]);
        $registry->setScrollConfig('backend', ['doctrines' => ['idiomatic-iteration' => 'off']]);

        $prophet = $registry->getProphets('backend')->first();

        $this->assertSame(Severity::Sin, $prophet->severityOverride());
    }
}
