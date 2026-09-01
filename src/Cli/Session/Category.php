<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Session;

/**
 * What one line of a transcript IS. The harness writes a great deal under `type: "user"` that no human
 * typed — a tool result, a synthesized turn, an injected reminder — so a reader that takes the type at
 * face value drowns: one real session holds 1467 `user` lines of which 38 are somebody speaking. Every
 * category here is decided by the FIELDS a line carries ({@see Transcript}), never by what its text
 * looks like.
 */
enum Category: string
{
    /**
     * A human actually typed (or queued) this. The rarest line in a transcript and the whole reason for
     * reading one.
     */
    case Prompt = 'prompt';

    /**
     * The agent speaking — the prose the user saw, without the tool calls around it.
     */
    case Reply = 'reply';

    case ToolResult = 'tool-result';

    /**
     * A reminder, hook feedback or attachment the harness put in front of the model. Addressed to the
     * agent, written by nobody.
     */
    case Injected = 'injected';

    /**
     * Where a compaction happened — the line the chunks are counted between.
     */
    case Boundary = 'boundary';

    /**
     * What a compaction rewrote the conversation INTO, so a reader can see what it claimed to keep.
     */
    case Summary = 'summary';

    /**
     * Bookkeeping the harness keeps beside the conversation — the title, the mode, a file snapshot.
     */
    case Bookkeeping = 'bookkeeping';

    /**
     * Is this somebody speaking, rather than the machinery around them? These are the lines a digest is
     * built from; everything else is context for them.
     */
    public function isSpeech(): bool
    {
        return $this === self::Prompt || $this === self::Reply;
    }
}
