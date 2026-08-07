<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Backend\AppliesExemptions;
use JesseGall\CodeCommandments\Packages\ExemptBy;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Tags\CompositionRoot;

/**
 * Reading configuration with `config(...)` inside a class instead of injecting a
 * typed config object. A `CompositionRoot` (a service provider's register/boot) is
 * exempt — that is where config() is wired into the typed objects it binds. Points at laravel-idioms.
 */
final class ConfigReadDetector implements Detector, Exemptable
{
    use AppliesExemptions;

    public function sin(): Sin
    {
        return new ConfigRead();
    }

    public function exemptions(): array
    {
        return [CompositionRoot::class => [ExemptBy::EnclosingClass]];
    }

    public function find(Codebase $codebase): array
    {
        return $this->exempt($codebase
            ->whereFunction('config')
            ->where(static fn (AstNode $node): bool => $node->isEnclosedInClass())
            ->get(), $codebase);
    }
}
