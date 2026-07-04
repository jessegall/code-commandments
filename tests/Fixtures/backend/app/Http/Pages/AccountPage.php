<?php

namespace Shop\Http\Pages;

use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;

/**
 * A righteous page object — composed and returned like the others, but its injected collaborator is
 * `#[Hidden]`, so nothing leaks. The InjectedServiceNotHidden detector must NOT flag it.
 */
final class AccountPage extends Data
{
    public readonly StatCard $profile;

    public readonly MenuLink $settings;

    public function __construct(
        #[Hidden]
        #[FromContainer(SalesReporter::class)]
        public readonly SalesReporter $sales,
    ) {}
}
