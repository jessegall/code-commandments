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
 * Detects hand-written TypeScript types mirroring backend Spatie Data classes;
 * the server should own the type and the frontend generate from it.
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
            ->reject(fn (TypeDeclarationMatch $type) => $this->isGenerated($type, $generated))
            ->where(fn (TypeDeclarationMatch $type) => $this->mirrorsAServerContract($type, $contracts))
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
