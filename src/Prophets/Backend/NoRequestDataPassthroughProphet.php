<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindRequestToDataPassthrough;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Commandment: No passing request computed values through to Data::from() - Inject the request in the Data class instead.
 */
class NoRequestDataPassthroughProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Do not pass request computed values to Data::from() in controllers';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Do not pass computed values from the request to a Data object's from()
method in controllers. Instead, inject the request in the Data class
constructor using #[FromContainer] and use #[Computed] property hooks
to derive values from the request.

This keeps the controller thin and moves the request data resolution
logic into the Data class where it belongs.

Bad:
    // In controller:
    public function create(EditorRequest $request): Response
    {
        return Inertia::render('Editor', EditorPage::from([
            'templateId' => $template->id,
            'previewColumns' => $request->getPreviewColumns(),
            'previewLimit' => $request->getPreviewLimit(),
        ]));
    }

Good:
    // In controller:
    public function create(EditorRequest $request): Response
    {
        return Inertia::render('Editor', EditorPage::from([
            'templateId' => $template->id,
        ]));
    }

    // In Data class:
    class EditorPage extends Data
    {
        #[Computed]
        public array $previewColumns {
            get => $this->request->getPreviewColumns();
        }

        #[Computed]
        public int $previewLimit {
            get => $this->request->getPreviewLimit();
        }

        public function __construct(
            #[Hidden]
            #[FromContainer(EditorRequest::class)]
            public readonly EditorRequest $request,

            public readonly string $templateId,
        ) {}
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->onlyControllers()
            ->pipe(ExtractUseStatements::class)
            ->pipe(new FindRequestToDataPassthrough)
            ->sinsFromMatches(
                fn ($match) => sprintf(
                    'Passing request computed values to %s::from() via keys: %s',
                    $match->name,
                    implode(', ', $match->groups),
                ),
                fn ($match) => sprintf(
                    'Inject the request in %s\'s constructor using #[FromContainer] and use #[Computed] property hooks to derive values from the request',
                    $match->name,
                ),
            )
            ->judge();
    }
}
