<?php

namespace Shop\Console;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\BareScopeEscape;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Picker;
use Shop\Models\Scopes\CurrentShopScope;

/**
 * Removes the pickers who left, in every shop at once.
 */
final class RetirePickersCommand extends Command
{
    protected $signature = 'pickers:retire';

    #[Sinful(BareScopeEscape::class)]
    public function handle(): int
    {
        Picker::query()->withoutGlobalScopes()->whereNotNull('left_at')->delete();

        return self::SUCCESS;
    }

    #[Righteous(BareScopeEscape::class)]
    public function remaining(): int
    {
        return Picker::query()->withoutGlobalScopes([CurrentShopScope::class])->count();
    }
}
