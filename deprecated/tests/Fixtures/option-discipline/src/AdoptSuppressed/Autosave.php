<?php
namespace Acme\Notify\AdoptSuppressed;

final class Autosave
{
    public function __construct(private DraftStore $store) {}

    public function run(string $id): void
    {
        $draft = $this->store->peek($id);
        if ($draft === null) {
            return;
        }
        echo $draft->body;
    }
}
