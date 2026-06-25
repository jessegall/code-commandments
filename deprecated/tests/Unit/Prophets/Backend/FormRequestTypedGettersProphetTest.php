<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\FormRequestTypedGettersProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class FormRequestTypedGettersProphetTest extends TestCase
{
    private FormRequestTypedGettersProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new FormRequestTypedGettersProphet();
    }

    public function test_warns_missing_return_types(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => 'required'];
    }

    // Missing return type!
    public function getName()
    {
        return $this->string('name')->toString();
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Requests/StoreUserRequest.php', $content);

        // This prophet returns warnings for missing return types
        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_with_typed_getters(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return ['name' => 'required'];
    }

    public function getName(): string
    {
        return $this->string('name')->toString();
    }

    public function getEmail(): ?string
    {
        return $this->input('email');
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Http/Requests/StoreUserRequest.php', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_request_files(): void
    {
        $content = <<<'PHP'
<?php
namespace App\Services;

class UserService
{
    public function getName()
    {
        return 'test';
    }
}
PHP;

        $judgment = $this->prophet->judge('/app/Services/UserService.php', $content);
        $this->assertTrue($judgment->isRighteous());
    }
}
