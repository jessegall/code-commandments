<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PageObjectMissingTypeScript;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ServiceLocationInPageObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * The Smart Farmers WorkflowEditorPage shape — a page that reaches into the container for its
 * collaborators (`app(AiService::class)`) instead of injecting them. The same reach is also a
 * container-reach, so the method carries both markers.
 */
#[Sinful(PageObjectMissingTypeScript::class)]
final class AiWorkflowPage extends Data
{
    public readonly MenuLink $trigger;

    public readonly MenuLink $action;

    /**
     * @var list<StatCard>
     */
    #[DataCollectionOf(StatCard::class)]
    public readonly array $nodes;

    #[Sinful(ServiceLocationInPageObject::class)]
    #[Sinful(ContainerReach::class)]
    public function aiEnabled(): bool
    {
        return app(AiService::class)->isEnabled();
    }

    public function activeNodes(): int
    {
        $count = 0;

        foreach ($this->nodes as $node) {
            if ($node->value !== '') {
                $count++;
            }
        }

        return $count;
    }
}
