<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Sins;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ConstructorOrchestration;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\InjectedServiceNotHidden;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NewDataObject;
use PHPUnit\Framework\TestCase;

final class SinMatchesTest extends TestCase
{
    public function test_matches_its_own_name_leniently(): void
    {
        $sin = new InjectedServiceNotHidden();

        $this->assertTrue($sin->matches('injected-service-not-hidden'));
        $this->assertTrue($sin->matches('InjectedServiceNotHidden'));
        $this->assertTrue($sin->matches('injected'));
    }

    public function test_matches_stays_name_only_so_skill_resolution_is_exact(): void
    {
        // matches() is the NAME-only form disable/enable rely on — a skill slug must NOT match it.
        $this->assertFalse((new InjectedServiceNotHidden())->matches('page'));
    }

    public function test_scopes_selects_the_whole_group_via_the_skill_slug(): void
    {
        // `--sin=page` scopes EVERY sin under backend/page-objects, not only the one whose name has "page".
        $this->assertTrue((new InjectedServiceNotHidden())->scopes('page'), 'name has no "page" but the slug does');
        $this->assertTrue((new ConstructorOrchestration())->scopes('page-objects'));
        $this->assertTrue((new InjectedServiceNotHidden())->scopes('injected'), 'still matches the name');
    }

    public function test_scopes_does_not_match_an_unrelated_keyword(): void
    {
        $this->assertFalse((new InjectedServiceNotHidden())->scopes('frontend'));
        // NewDataObject lives under spatie-data, not page-objects.
        $this->assertFalse((new NewDataObject())->scopes('page'));
    }
}
