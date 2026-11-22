<?php

namespace Shop\Customers;

use Illuminate\Database\ConnectionInterface;
use JesseGall\CodeCommandments\Sins\Backend\LoneImplementerInterface;
use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Finds a loyalty member by the card number printed on their card.
 */
#[Sinful(LoneImplementerInterface::class)]
interface MemberDirectory
{
    public function byCard(string $card): ?object;
}

final class DatabaseMemberDirectory implements MemberDirectory
{
    public function __construct(private ConnectionInterface $database) {}

    public function byCard(string $card): ?object
    {
        return $this->database->table('members')->where('card', $card)->first();
    }
}

/**
 * Signs a shopper up at the till, from the form the cashier filled in.
 */
final class LoyaltySignup
{
    public function __construct(private MemberDirectory $members) {}

    /**
     * @param  array<string, string>  $form
     */
    #[Sinful(ManufacturedFakeFill::class)]
    public function alreadyMember(array $form): bool
    {
        return $this->members->byCard($form['card'] ?? '') !== null;
    }
}
