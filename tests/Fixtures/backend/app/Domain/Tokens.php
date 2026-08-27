<?php

namespace Shop\Domain;

/**
 * The token kinds the scanner narrows to. A pairing token holds whatever it was matched with, which
 * is why every site has to ask what that is.
 */
final class Bracket
{
    public mixed $pair = null;
}

final class Brace
{
    public mixed $close = null;
}
