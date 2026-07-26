<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;

/**
 * The canonical order of a class head — the single source of the sequence both the detector and the
 * scribe read: trait uses, enum cases, constants, static properties, then instance properties widest
 * visibility first, and derived (hooked) properties last, because a computed slot reads after the
 * fields it computes from. Behaviour follows from the constructor on.
 */
final class ClassLayoutOrder
{
    /** A member that is none of the known kinds sorts with the plain private properties. */
    private const int UNKNOWN = 6;

    /**
     * Where $member belongs in the head, as a rank — lower comes first. Two members of the same rank
     * are in whatever order their author chose; only a DROP in rank is out of order.
     */
    public static function rankOf(Node $member): int
    {
        return match (true) {
            $member instanceof TraitUse => 0,
            $member instanceof EnumCase => 1,
            $member instanceof ClassConst => 2,
            $member instanceof Property => self::rankOfProperty($member),
            default => self::UNKNOWN,
        };
    }

    /**
     * The members of $class that stand ABOVE its first method — the head this order governs. A member
     * below a method is a different sin ({@see \JesseGall\CodeCommandments\Sins\Backend\MemberAfterMethod}),
     * and ordering it here would say the same thing twice.
     *
     * @return list<Node>
     */
    public static function headOf(ClassLike $class): array
    {
        $head = [];

        foreach ($class->stmts as $member) {
            if ($member instanceof ClassMethod) {
                return $head;
            }

            $head[] = $member;
        }

        return $head;
    }

    /**
     * Is $member out of order — does any member ABOVE it in the head belong below it? Reported on the
     * member that arrives too late, which is the one to move.
     */
    public static function isOutOfOrder(ClassLike $class, Node $member): bool
    {
        $rank = self::rankOf($member);

        foreach (self::headOf($class) as $earlier) {
            if ($earlier === $member) {
                return false;
            }

            if (self::rankOf($earlier) > $rank) {
                return true;
            }
        }

        return false;
    }

    /**
     * The head of $class in canonical order, GROUPED by rank — trait uses together, constants
     * together, and so on, each group in the order its author wrote it (a stable sort, so only what is
     * genuinely misplaced moves). The grouping is what lets a fix put a blank line between kinds while
     * leaving a tight run of constants tight.
     *
     * @return list<list<Node>>
     */
    public static function groupedHeadOf(ClassLike $class): array
    {
        $groups = [];

        foreach (self::headOf($class) as $member) {
            $groups[self::rankOf($member)][] = $member;
        }

        ksort($groups);

        return array_values($groups);
    }

    /**
     * Static state, then instance state by visibility, then the derived slots — a hooked property is
     * computed FROM the fields above it, so it reads last.
     */
    private static function rankOfProperty(Property $property): int
    {
        if (($property->flags & Modifiers::STATIC) !== 0) {
            return 3;
        }

        $base = self::hasHook($property) ? 7 : 4;

        return $base + self::visibilityOffset($property->flags);
    }

    /** 0 for public (the default when no modifier is spelled), 1 for protected, 2 for private. */
    private static function visibilityOffset(int $flags): int
    {
        return match (true) {
            ($flags & Modifiers::PRIVATE) !== 0 => 2,
            ($flags & Modifiers::PROTECTED) !== 0 => 1,
            default => 0,
        };
    }

    private static function hasHook(Property $property): bool
    {
        return $property->hooks !== [];
    }
}
