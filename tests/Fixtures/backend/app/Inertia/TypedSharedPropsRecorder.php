<?php

namespace Shop\Inertia;

use Inertia\DevTools\RequestRecorder;

/**
 * Records shared props that arrive as a typed object rather than an array.
 */
class TypedSharedPropsRecorder extends RequestRecorder
{

    public function sharedPropsResolved(object $middleware, array $shared): void
    {
        parent::sharedPropsResolved($middleware, $shared);
    }

}
