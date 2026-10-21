<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\UnreadCollaborator;
use JesseGall\CodeCommandments\Testing\Fixed;
use Shop\Contracts\Mailer;

/**
 * The announcer repaired: the constructor asks for what the class uses and nothing else, so
 * what it needs is plain from its signature and nobody has to read the body to be sure.
 */
final class RunSheetPoster
{
    #[Fixed(UnreadCollaborator::class)]
    public function __construct(private readonly Mailer $mailer) {}

    /**
     * @param array<string, string> $sheets
     */
    public function announce(array $sheets): int
    {
        $sent = 0;
        foreach ($sheets as $depot => $sheet) {
            $this->mailer->send($depot, 'Run sheet for ' . $depot, $sheet);
            $sent++;
        }

        return $sent;
    }
}
