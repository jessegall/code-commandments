<?php

namespace Shop\Scales;

/**
 * An optional pieceCount capability some devices and plugins offer and others lack.
 */
interface CountsPieces
{
    public function pieceCount(): string;
}
