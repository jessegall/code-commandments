<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration\Events;

use JesseGall\CodeCommandments\Hooks\HookResponse;
use JesseGall\PhpTypes\Option;

/**
 * What a {@see Handler} answers for one moment — silence, a note the reader sees, a note only the agent
 * sees, or a refusal. A hook moment already has exactly that vocabulary, so a verdict COMPOSES
 * {@see HookResponse} rather than restating it, since a second response type is one protocol written
 * twice; the fifth thing a response can say, a `PreCompact`'s compaction instructions, is absent because
 * no moment raised today travels on that transport and a verdict nothing can deliver is a dropped one.
 */
final readonly class Verdict
{
    private function __construct(public HookResponse $response) {}

    /**
     * Nothing to say — the handler has no business with this moment.
     */
    public static function pass(): self
    {
        return new self(HookResponse::silent());
    }

    /**
     * Say $text where the moment is reported. The act still happens.
     */
    public static function note(string $text): self
    {
        return new self(HookResponse::injecting($text));
    }

    /**
     * Say $text to the agent alone, kept out of the user's transcript — the shape for a word that is
     * worth the agent reading and not worth a person watching it be said.
     */
    public static function quietly(string $text): self
    {
        return new self(HookResponse::injecting($text, quietly: true));
    }

    /**
     * STOP this moment, for $reason. It stands only on a {@see Vetoable} moment; anywhere else it is
     * demoted to a quiet note ({@see demoted}), because the act it refuses has already happened.
     */
    public static function refuse(string $reason): self
    {
        return new self(HookResponse::blocking($reason));
    }

    /**
     * Everything the handlers said for one moment, as the single answer the caller acts on — the merge
     * {@see HookResponse} already performs, since this is that response.
     *
     * @param  list<self>  $verdicts
     */
    public static function merge(array $verdicts): self
    {
        return new self(HookResponse::merge(array_map(static fn (self $verdict): HookResponse => $verdict->response, $verdicts)));
    }

    /**
     * This verdict with its refusal turned into a QUIET note — never dropped. A handler that refuses a
     * moment already true is wrong about what it can stop and right about what it saw, so the reason
     * still travels; only its force is taken away. It is {@see \JesseGall\CodeCommandments\Hooks\Handlers\WriteGate}'s
     * own move for a shell write that already landed.
     */
    public function demoted(): self
    {
        foreach ($this->response->blockReason as $reason) {
            return self::quietly($reason);
        }

        return $this;
    }

    /**
     * Why this moment was refused, absent when it was not.
     *
     * @return Option<string>
     */
    public function refusal(): Option
    {
        return $this->response->blockReason;
    }

    /**
     * What this verdict says without refusing, absent when it says nothing. A CLI has ONE channel, so a
     * note and a quiet note both land here and both are printed; the distinction is the hook transport's,
     * and a terminal that pretended to have it would simply lose one of them.
     *
     * @return Option<string>
     */
    public function message(): Option
    {
        return $this->response->context;
    }

    public function isSilent(): bool
    {
        return $this->response->isSilent();
    }
}
