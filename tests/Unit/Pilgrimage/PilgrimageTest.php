<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Pilgrimage;

use JesseGall\CodeCommandments\Doctrines\DoctrineRegistry;
use JesseGall\CodeCommandments\Prophets\Backend\LongMethodProphet;
use JesseGall\CodeCommandments\Prophets\Backend\OptionDisciplineProphet;
use JesseGall\CodeCommandments\Support\Pilgrimage\Pilgrimage;
use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageState;
use PHPUnit\Framework\TestCase;

class PilgrimageTest extends TestCase
{
    public function test_itinerary_lists_doctrines_then_a_final_singletons_station(): void
    {
        $itinerary = Pilgrimage::itinerary([LongMethodProphet::class, OptionDisciplineProphet::class]);

        $names = array_column($itinerary, 'name');

        $this->assertSame('totality', $names[0], 'doctrines come first, in registry order');
        $this->assertSame('singletons', end($names), 'homeless prophets become the final station');
    }

    public function test_homed_prophets_never_land_in_the_singletons_station(): void
    {
        // LongMethod is a homeless singleton; OptionDiscipline is homed in `totality`.
        $itinerary = Pilgrimage::itinerary([LongMethodProphet::class, OptionDisciplineProphet::class]);

        $singletons = array_values(array_filter($itinerary, static fn (array $s): bool => $s['name'] === 'singletons'));
        $flat = array_merge(...array_merge(...array_column($singletons, 'pillars')));

        $this->assertContains(LongMethodProphet::class, $flat);
        $this->assertNotContains(OptionDisciplineProphet::class, $flat);
        $this->assertNull(DoctrineRegistry::locate(LongMethodProphet::class));
    }

    public function test_resolves_a_prefixed_php_scroll_when_the_requested_name_is_absent(): void
    {
        // Consumers name scrolls freely (e.g. `acme-pos-backend`); a hardcoded
        // `backend` default must fall through to the PHP scroll, not register zero
        // prophets and finish instantly (#scroll-resolution bug).
        $config = ['scrolls' => [
            'acme-frontend' => ['extensions' => ['vue', 'ts']],
            'acme-backend' => ['extensions' => ['php']],
        ]];

        $runner = new \JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner('/tmp', $config, 'backend');

        $this->assertSame('acme-backend', $runner->scroll());
    }

    public function test_honours_an_explicit_existing_scroll(): void
    {
        $config = ['scrolls' => [
            'backend' => ['extensions' => ['php']],
            'frontend' => ['extensions' => ['vue']],
        ]];

        $runner = new \JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner('/tmp', $config, 'frontend');

        $this->assertSame('frontend', $runner->scroll());
    }

    public function test_state_round_trips_through_disk(): void
    {
        $dir = sys_get_temp_dir() . '/cc-pilg-' . uniqid();
        mkdir($dir);

        $state = new PilgrimageState(doctrine: 2, pillar: 1, prophet: 3, scope: ['/a.php', '/b.php']);
        $state->save($dir);

        $loaded = PilgrimageState::load($dir);
        $this->assertNotNull($loaded);
        $this->assertSame(2, $loaded->doctrine);
        $this->assertSame(1, $loaded->pillar);
        $this->assertSame(3, $loaded->prophet);
        $this->assertSame(['/a.php', '/b.php'], $loaded->scope);

        PilgrimageState::clear($dir);
        $this->assertNull(PilgrimageState::load($dir));

        @rmdir($dir);
    }
}
