<?php

final class Cart
{
    public function kind(): string
    {
        return Kind::Food->value; // @contract promise/read-declaration-resolves -> one.php#food
    }
}
