<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Bridge\ConsumesContracts;
use JesseGall\CodeCommandments\Bridge\Contracts;
use JesseGall\CodeCommandments\Bridge\GeneratedTypes;
use JesseGall\CodeCommandments\Bridge\TypeContract;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Sins\Frontend\MirroredServerType;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\TypeDeclarationMatch;

/**
 * A hand-written TypeScript type that mirrors a backend Spatie `Data` class — the same
 * name and (spelling aside) the same fields. That is a second source of truth for one
 * contract; the server should own it (`#[TypeScript]`) and the frontend generate from
 * it. Points at mirrored-server-type.
 *
 * The `Data` shapes reach this detector as {@see TypeContract}s the backend published
 * across the {@see \JesseGall\CodeCommandments\Bridge\Bridge} — so a FRONTEND detector
 * flags the duplicate without ever reading a PHP node itself. A thin type (fewer than
 * {@see MIN_FIELDS} fields) is dropped: at that size a name-and-shape match is a
 * coincidence, not a copy.
 */
final class MirroredServerTypeDetector implements Detector, ConsumesContracts
{
    /**
     * The fewest fields a type may have and still be judged a mirror — below it, a
     * name-and-field match is too weak to trust.
     */
    private const int MIN_FIELDS = 3;

    private Contracts $contracts;

    public function __construct()
    {
        $this->contracts = new Contracts();
    }

    public function withContracts(Contracts $contracts): void
    {
        $this->contracts = $contracts;
    }

    public function sin(): Sin
    {
        return new MirroredServerType();
    }

    public function find(Codebase $components): array
    {
        $contracts = $this->contracts->ofType(TypeContract::class);
        $generated = $this->contracts->ofType(GeneratedTypes::class);

        return $components
            ->whereTypeDeclaration()
            ->havingAtLeastFields(self::MIN_FIELDS)
            ->reject(fn (TypeDeclarationMatch $type): bool => $this->isGenerated($type, $generated))
            ->where(fn (TypeDeclarationMatch $type): bool => $this->mirrorsAServerContract($type, $contracts))
            ->get();
    }

    /**
     * Is this declaration the generator's OWN output — the correct single source of
     * truth, not a hand-written copy?
     *
     * @param  list<GeneratedTypes>  $generated
     */
    private function isGenerated(TypeDeclarationMatch $type, array $generated): bool
    {
        foreach ($generated as $output) {
            if ($output->covers($type->file())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does any published server contract mirror this hand-written type — same name and
     * field set, spelling-insensitive?
     *
     * @param  list<TypeContract>  $contracts
     */
    private function mirrorsAServerContract(TypeDeclarationMatch $type, array $contracts): bool
    {
        foreach ($contracts as $contract) {
            if ($contract->mirroredBy($type->name(), $type->fields())) {
                return true;
            }
        }

        return false;
    }
}
