<?php

namespace Shop\CompareSelf;

enum Origin
{
    case Shipped;
    case Stored;

    public function equals(self $other): bool
    {
        return $other === $this;
    }
}
