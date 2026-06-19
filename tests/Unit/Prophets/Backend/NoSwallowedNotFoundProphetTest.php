<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoSwallowedNotFoundProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use PHPUnit\Framework\TestCase;

class NoSwallowedNotFoundProphetTest extends TestCase
{
    private NoSwallowedNotFoundProphet $prophet;

    protected function setUp(): void
    {
        $this->prophet = new NoSwallowedNotFoundProphet();
    }

    public function test_flags_not_found_swallowed_into_null(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class UserProfileService
{
    public function displayName(int $id): string
    {
        try {
            $user = $this->users->getById($id);
        } catch (UserNotFoundException) {
            $user = null;
        }

        return $user?->displayName() ?? 'Guest';
    }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('swallowed', strtolower($judgment->warnings[0]->message));
        $this->assertSame('swallowed-notfound:user', $judgment->warnings[0]->symbol);
    }

    public function test_flags_return_sentinels_and_false_and_empty_array(): void
    {
        foreach (['return null;', 'return false;', 'return [];', '$x = false;', '$x = [];'] as $body) {
            $judgment = $this->judge(<<<PHP
final class R
{
    public function f()
    {
        try {
            return \$this->repo->getById(1);
        } catch (RecordNotFoundException) {
            {$body}
        }
    }
}
PHP);

            $this->assertCount(1, $judgment->warnings, "should flag swallow body: {$body}");
        }
    }

    public function test_matches_configured_exact_exceptions(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class R
{
    public function f()
    {
        try {
            return $this->items->at(3);
        } catch (\OutOfBoundsException) {
            return null;
        }
    }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_catch_that_does_real_recovery(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class R
{
    public function f()
    {
        try {
            return $this->repo->getById(1);
        } catch (RecordNotFoundException $e) {
            $this->logger->warning('miss', ['e' => $e]);
            return $this->fallback();
        }
    }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_rethrow(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class R
{
    public function f()
    {
        try {
            return $this->repo->getById(1);
        } catch (ThingNotFoundException $e) {
            throw new DomainException('boom', 0, $e);
        }
    }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_non_not_found_exception(): void
    {
        $judgment = $this->judge(<<<'PHP'
final class R
{
    public function f()
    {
        try {
            return $this->parse($x);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_empty_catch_body(): void
    {
        // An empty catch is a different (rarer) smell; this rule targets the
        // swallow-into-a-sentinel shape specifically.
        $judgment = $this->judge(<<<'PHP'
final class R
{
    public function f(): void
    {
        try {
            $this->repo->getById(1);
        } catch (RecordNotFoundException) {
        }
    }
}
PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n\nnamespace App;\n\n{$body}\n");
    }
}
