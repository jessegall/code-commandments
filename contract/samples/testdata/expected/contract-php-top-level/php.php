<?php // @contract kind/file

// @contract kind/unsupported
// @contract fact/unsupported/construct = "declare"
// @contract role/unsupported/parts -> [declaredValue]
// @label declaredValue literal
declare(strict_types=1);

$total = @intdiv(10, 2);
