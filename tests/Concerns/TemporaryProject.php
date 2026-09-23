<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Concerns;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * A {@see TemporaryFolder} that is a project: it holds `.commandments/custom`, `CLAUDE_PROJECT_DIR` names
 * it for the length of the test, and the session a test sets in `CLAUDE_CODE_SESSION_ID` is put back.
 */
trait TemporaryProject
{
    use TemporaryFolder;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    #[Before]
    protected function makeTemporaryProject(): void
    {
        mkdir($this->root . '/.commandments/custom', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    #[After]
    protected function restoreEnvironment(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
    }

    /**
     * Write the project's config: `$body` is the closure's body, handed the `Config` as `$config`.
     */
    private function writeConfig(string $body): void
    {
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n    {$body}\n};\n",
        );
    }
}
