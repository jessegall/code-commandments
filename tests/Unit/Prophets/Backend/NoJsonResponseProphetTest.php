<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoJsonResponseProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoJsonResponseProphetTest extends TestCase
{
    private NoJsonResponseProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoJsonResponseProphet();
    }

    public function test_detects_response_json(): void
    {
        $content = $this->getFixtureContent('Backend', 'Sinful', 'JsonResponseUsage.php');
        $judgment = $this->prophet->judge('/test/Controller.php', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThanOrEqual(1, $judgment->sinCount());
    }

    public function test_passes_api_resource(): void
    {
        $content = $this->getFixtureContent('Backend', 'Righteous', 'ProperResponse.php');
        $judgment = $this->prophet->judge('/test/Controller.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_new_json_response(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    public function show()
    {
        return new JsonResponse(['data' => 'test']);
    }
}
PHP;

        $judgment = $this->prophet->judge('/test/Controller.php', $content);
        $this->assertTrue($judgment->isFallen());
    }

    public function test_skips_non_controller(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class ApiService
{
    public function getData()
    {
        return response()->json(['test']);
    }
}
PHP;

        $judgment = $this->prophet->judge('/test/Service.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }
}
