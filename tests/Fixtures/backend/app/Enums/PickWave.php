<?php

namespace Shop\Enums;

/**
 * When a pick runs relative to the wave being released — an INT-backed enum whose
 * ordinals are 0 and 1, the two numbers every count, index and retry limit in a
 * codebase also uses. It is here as the trap for the mirror rules: a number is a
 * quantity, never a name, so sharing an ordinal proves nothing.
 */
enum PickWave: int
{
    case BeforeRelease = 0;
    case AfterRelease = 1;
}
