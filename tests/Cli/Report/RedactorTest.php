<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Report;

use JesseGall\CodeCommandments\Cli\Report\Redactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The report redactor scrubs secrets from a source line before it is captured into an issue — sensitive
 * assignments, `env()` defaults, dotenv values, provider token shapes, URI credentials, and high-entropy
 * blobs — while leaving ordinary code readable.
 */
final class RedactorTest extends TestCase
{
    private Redactor $redactor;

    protected function setUp(): void
    {
        $this->redactor = new Redactor();
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>  [line, the secret that must be gone]
     */
    public static function secrets(): iterable
    {
        yield 'php array password' => ["        'password' => 'hunter2superSecret',", 'hunter2superSecret'];
        yield 'php array api key' => ["    'api_key' => 'sk_live_51H8xYzAbCdEf0123456789',", 'sk_live_51H8xYzAbCdEf0123456789'];
        yield 'php assignment secret' => ['$clientSecret = "s3cr3t-value-here-9999";', 's3cr3t-value-here-9999'];
        yield 'ts object token' => ['  authToken: "abcDEF123456ghiJKL789mno",', 'abcDEF123456ghiJKL789mno'];
        yield 'env() default' => ["return env('DB_PASSWORD', 'prodPassw0rd!');", 'prodPassw0rd!'];
        yield 'dotenv line' => ['STRIPE_SECRET_KEY=sk_test_ABCDEFGHIJKLMNOP12345', 'sk_test_ABCDEFGHIJKLMNOP12345'];
        yield 'dotenv export' => ['export API_TOKEN=zzzz9999yyyy8888xxxx7777', 'zzzz9999yyyy8888xxxx7777'];
        yield 'aws key anywhere' => ['const id = "AKIAIOSFODNN7EXAMPLE";', 'AKIAIOSFODNN7EXAMPLE'];
        yield 'github token' => ['// leftover ghp_16C7e42F292c6912E7710c838347Ae178B4a', 'ghp_16C7e42F292c6912E7710c838347Ae178B4a'];
        yield 'jwt' => ['header("Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV");', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9'];
        yield 'uri credentials' => ["'dsn' => 'postgres://appuser:sup3rP4ss@db.internal:5432/app',", 'sup3rP4ss'];
        yield 'high entropy blob' => ['$token = "aB3dE6gH9jK2mN5pQ8sT1vW4xY7zC0eF";', 'aB3dE6gH9jK2mN5pQ8sT1vW4xY7zC0eF'];
    }

    #[DataProvider("secrets")]
    public function test_masks_the_secret(string $line, string $secret): void
    {
        $redacted = $this->redactor->line($line);

        $this->assertStringNotContainsString($secret, $redacted, "the secret survived: {$redacted}");
        $this->assertStringContainsString('█', $redacted);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function harmless(): iterable
    {
        yield 'plain method' => ['public function fromRow(string $code): OrderState'];
        yield 'ordinary strings' => ["return ['title' => 'Run', 'icon' => 'play'];"];
        yield 'non-sensitive assignment' => ["\$label = 'Order shipped';"];
        yield 'short identifier' => ["\$id = 'edit';"];
        yield 'a class reference' => ['use Spatie\\LaravelData\\Data;'];
        yield 'a route path' => ["Route::get('/orders/{order}', OrderController::class);"];
        yield 'a boolean env' => ["'debug' => env('APP_DEBUG', false),"];
    }

    #[DataProvider("harmless")]
    public function test_leaves_ordinary_code_untouched(string $line): void
    {
        $this->assertSame($line, $this->redactor->line($line), 'ordinary code must not be redacted');
    }
}
