<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\SecretToLogOrResponseProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class SecretToLogOrResponseProphetTest extends TestCase
{
    private SecretToLogOrResponseProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new SecretToLogOrResponseProphet;
    }

    public function test_flags_a_config_secret_logged(): void
    {
        $j = $this->judge('\Log::info("connecting with " . config("services.stripe.secret"));');
        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('SECURITY', $j->warnings[0]->message);
    }

    public function test_flags_a_secret_property_dumped(): void
    {
        $this->assertTrue($this->judge('dd($user->password);')->hasWarnings());
        $this->assertTrue($this->judge('\Log::error($account->apiToken);')->hasWarnings());
        $this->assertTrue($this->judge('logger(config("services.x.token"));')->hasWarnings());
    }

    public function test_does_not_flag_a_redacted_secret(): void
    {
        $this->assertTrue($this->judge('\Log::info(\Str::mask(config("a.token"), "*", -4));')->isRighteous());
        $this->assertTrue($this->judge('\Log::info(bcrypt($user->password));')->isRighteous());
        $this->assertTrue($this->judge('dd(substr($user->password, 0, 2));')->isRighteous());
    }

    public function test_does_not_flag_a_non_secret_value(): void
    {
        $this->assertTrue($this->judge('\Log::info(config("app.name"));')->isRighteous());
        $this->assertTrue($this->judge('\Log::info("just a message");')->isRighteous());
        $this->assertTrue($this->judge('dd($user->email);')->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        $code = "<?php\nnamespace App;\nclass C { public function m(\$user, \$account) { {$body} } }";

        return $this->prophet->judge('/tmp/x.php', $code);
    }
}
