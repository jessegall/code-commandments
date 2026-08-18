<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Evals;

/**
 * Asks the `claude` CLI which skills it would consult, shown only the metadata a loader sees. The
 * model answers as JSON so the reply is data rather than prose to be interpreted; anything it says
 * that is not a known id is dropped, because a hallucinated skill is not a trigger.
 */
final readonly class ClaudeOracle implements Oracle
{
    public function __construct(
        private ?string $model = null,
        private string $binary = 'claude',
    ) {}

    public function consulted(string $query, array $skills): array
    {
        $catalogue = '';

        foreach ($skills as $id => $description) {
            $catalogue .= "- {$id}: {$description}\n";
        }

        $prompt = <<<PROMPT
            You are choosing which SKILLS to consult for a user's request. Here is the whole list you
            can see, each as `id: description`:

            {$catalogue}
            The user's request is:

            {$query}

            Answer with a JSON array of the ids you would consult, and nothing else. Answer `[]` if
            none of them applies. Judge only from the descriptions above.
            PROMPT;

        $command = escapeshellarg($this->binary) . ' -p'
            . ($this->model === null ? '' : ' --model ' . escapeshellarg($this->model))
            . ' ' . escapeshellarg($prompt) . ' 2>/dev/null';

        return $this->known((string) shell_exec($command), array_keys($skills));
    }

    /**
     * The ids in $reply that are really in the catalogue. The reply is scanned for the JSON array it
     * was asked for; a model that wraps it in a sentence or a fence still answers usefully.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function known(string $reply, array $ids): array
    {
        $found = [];

        foreach ($ids as $id) {
            if (str_contains($reply, '"' . $id . '"')) {
                $found[] = $id;
            }
        }

        return $found;
    }
}
