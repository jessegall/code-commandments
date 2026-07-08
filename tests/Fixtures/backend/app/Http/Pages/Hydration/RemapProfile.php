<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\HandKeyRemap;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N7 scenario 2 — a wider snake→camel remap off an external payload, in a class that validates first.
 */
final class ProfileData extends Data
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $avatarUrl,
    ) {}
}

final class ProfilePayload
{
    public function all(): array
    {
        return [];
    }

    public function isComplete(): bool
    {
        return true;
    }
}

final class ProfileMapper
{
    #[Sinful(HandKeyRemap::class)]
    public function map(ProfilePayload $payload): ProfileData
    {
        $data = $payload->all();

        return ProfileData::from([
            'firstName' => $data['first_name'],
            'lastName' => $data['last_name'],
            'avatarUrl' => $data['avatar_url'],
        ]);
    }
}
