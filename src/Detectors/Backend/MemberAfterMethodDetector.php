<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\MemberAfterMethodScribe;
use JesseGall\CodeCommandments\Sins\Backend\MemberAfterMethod;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects state declared below behaviour: a trait use, constant, property, property hook or enum case
 * that sits after a method in the same class body. Position is the whole signal — the member is read in
 * the order the class declares it, and one field two hundred lines down means a reader can never trust
 * that the head of the class is the whole inventory. Points at the class-layout skill.
 */
final class MemberAfterMethodDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new MemberAfterMethod();
    }

    public function scribe(): string
    {
        return MemberAfterMethodScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClassMember()
            ->where(fn (AstNode $n): bool => $n->followsAMethodInItsClass())
            ->get();
    }
}
