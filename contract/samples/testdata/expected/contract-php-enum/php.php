<?php

// @label suit type
// @contract fact/type/form = "enum"
// @contract role/type/writtenMembers -> [hearts, spades]
enum Suit: string
{
    // @label hearts enum-case
    // @contract kind/enum-case
    // @contract role/enum-case/value -> heartsValue
    // @contract fact/*/symbol on enum-case = "Suit::Hearts"
    // @contract fact/*/name on enum-case = "Hearts"
    // @label heartsValue literal
    case Hearts = 'H';
    // @label spades enum-case
    case Spades = 'S';
}
