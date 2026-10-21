<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\UnreadCollaborator;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\Mailer;

/**
 * Mails each depot the run sheets it is responsible for, one message per depot, and says how
 * many went out. Nothing here reads the registry the constructor asks for.
 */
final class RunSheetAnnouncer
{
    #[Sinful(UnreadCollaborator::class)]
    public function __construct(
        private readonly Mailer $mailer,
        private readonly CarrierRegistry $carriers,
    ) {}

    /**
     * @param array<string, string> $sheets
     */
    public function announce(array $sheets): int
    {
        $sent = 0;
        foreach ($sheets as $depot => $sheet) {
            $this->mailer->send($depot, $this->subjectFor($depot), $sheet);
            $sent++;
        }

        return $sent;
    }

    private function subjectFor(string $depot): string
    {
        return 'Run sheet for ' . $depot;
    }
}
