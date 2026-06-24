<?php
namespace Acme\Notify\AdoptSuppressed;

final class DraftStore
{
    private ?Draft $current = null;

    // Decides null, but only ONE caller branches on it ⇒ below min_callers ⇒ silent.
    public function peek(string $id): Draft|null
    {
        if ($id === '' ) {
            return null;
        }
        return $this->current;
    }
}
