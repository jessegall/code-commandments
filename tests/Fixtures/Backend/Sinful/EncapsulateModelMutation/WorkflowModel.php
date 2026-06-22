<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Fixture for the IN-MODEL half of #192: behaviour methods that change the
 * record's own state but never call $this->save(), so callers still have to
 * persist by hand. The declared save() marks the class persistable (standing in
 * for the inherited Eloquent Model::save()). Each public non-saving mutator below
 * must be flagged; saveActive() and the fluent/private/constructor cases must not.
 */
class WorkflowModel
{
    public int $edit_seq = 0;

    public bool $is_active = false;

    public ?string $webhook_token = null;

    public ?string $webhook_secret = null;

    public function __construct(int $seq)
    {
        // Construction, not a transition — exempt.
        $this->edit_seq = $seq;
    }

    // ---- flagged: mutate $this but never save ----

    public function incrementEditSeq(): void
    {
        $this->edit_seq++;
    }

    public function setActive(bool $active): void
    {
        $this->is_active = $active;
    }

    public function setWebhookCredentials(string $token, string $secret): void
    {
        $this->webhook_token = $token;
        $this->webhook_secret = $secret;
    }

    // ---- NOT flagged ----

    /** Self-persisting — owns the transition end to end. */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    /** Fluent builder setter — returns $this. */
    public function withToken(string $token): self
    {
        $this->webhook_token = $token;

        return $this;
    }

    /** Private helper — composed by a saving method, not a public operation. */
    private function bumpInternal(): void
    {
        $this->edit_seq++;
    }

    public function save(): bool
    {
        return true;
    }
}
