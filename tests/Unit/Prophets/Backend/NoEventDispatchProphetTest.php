<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoEventDispatchProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoEventDispatchProphetTest extends TestCase
{
    private NoEventDispatchProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoEventDispatchProphet();
    }

    public function test_detects_static_event_dispatch(): void
    {
        $content = $this->getFixtureContent('Backend', 'Sinful', 'EventDispatch.php');
        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThanOrEqual(1, $judgment->sinCount());
    }

    public function test_passes_event_helper(): void
    {
        $content = $this->getFixtureContent('Backend', 'Righteous', 'ProperEventDispatch.php');
        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_static_dispatch_on_event_class(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

use App\Events\UserCreatedEvent;

class UserService
{
    public function createUser()
    {
        UserCreatedEvent::dispatch($user);
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_allows_event_helper(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

use App\Events\UserCreatedEvent;

class UserService
{
    public function createUser()
    {
        event(new UserCreatedEvent($user));
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }

    public function test_allows_bus_dispatch(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

use Illuminate\Support\Facades\Bus;

class UserService
{
    public function createUser()
    {
        Bus::dispatch(new SendWelcomeEmail($user));
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }
}
