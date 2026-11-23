<?php

namespace Shop\Http\Controllers\CommandActions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Marking an order note read and a delivery note read: two routes whose actions read alike, yet each runs the
 * command of the request Laravel resolves and validates for its own route, so neither copies the other.
 */
final class MarkOrderNoteReadRequest extends FormRequest
{
    public function command(): MarkOrderNoteRead
    {
        return new MarkOrderNoteRead($this->integer('note'));
    }
}

final class MarkDeliveryNoteReadRequest extends FormRequest
{
    public function command(): MarkDeliveryNoteRead
    {
        return new MarkDeliveryNoteRead($this->integer('note'), $this->string('courier')->toString());
    }
}

final class MarkOrderNoteRead
{
    public function __construct(public readonly int $note) {}
}

final class MarkDeliveryNoteRead
{
    public function __construct(public readonly int $note, public readonly string $courier) {}
}

final class NoteCommandBus
{
    public function run(object $command): void {}
}

final class NotePageAnswers
{
    public function back(): RedirectResponse
    {
        return redirect()->back()->with('status', 'note-read');
    }
}

final class MarkOrderNoteReadController
{
    #[Righteous(DuplicateFunction::class)]
    public function __invoke(MarkOrderNoteReadRequest $request, NoteCommandBus $commands, NotePageAnswers $answers): RedirectResponse
    {
        $commands->run($request->command());

        return $answers->back();
    }
}

final class MarkDeliveryNoteReadController
{
    #[Righteous(DuplicateFunction::class)]
    public function __invoke(MarkDeliveryNoteReadRequest $request, NoteCommandBus $commands, NotePageAnswers $answers): RedirectResponse
    {
        $commands->run($request->command());

        return $answers->back();
    }
}
