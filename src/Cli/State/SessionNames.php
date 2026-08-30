<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\State;

use JesseGall\PhpTypes\Option;

/**
 * The names a project has given its sessions. A session folder is a five-character hash by default,
 * which is unreadable to the person picking one out of a dozen — and now that a session holds its own
 * tasks, it is the thing you come BACK to, so it needs a name you can say. Naming renames the folder;
 * this file records which name belongs to which id, because the id is the only thing an agent knows
 * about itself.
 */
final class SessionNames
{
    private const string FILE = 'sessions/.names';

    private const string SEPARATOR = "\t";

    public function __construct(private readonly StateFile $file) {}

    public static function in(string $dir): self
    {
        return new self(new StateFile($dir . '/' . self::FILE, self::legend()));
    }

    public static function legend(): Legend
    {
        return new Legend(
            'The names this project has given its sessions (`commandments session name "<name>"`). A '
                . 'session folder is a hash by default; a named one IS the name, and this says which id '
                . 'it belongs to so an agent can still find its own folder from the id it was given.',
            [],
            list: 'one `name<TAB>session-id` per line. The NAME is the folder under `sessions/`, and the '
                . 'id is what the harness calls the session it holds.',
            safe: 'a named session becomes findable by its hash again — nothing inside it is lost',
        );
    }

    /**
     * The name this session goes by, if it has been given one.
     *
     * @return Option<string>
     */
    public function nameOf(string $sessionId): Option
    {
        foreach ($this->pairs() as $name => $id) {
            if ($id === $sessionId) {
                return Option::some($name);
            }
        }

        return Option::none();
    }

    /**
     * The session $name belongs to — the reverse lookup, for a person who knows only the name.
     *
     * @return Option<string>
     */
    public function idOf(string $name): Option
    {
        $pairs = $this->pairs();

        return array_key_exists($name, $pairs) ? Option::some($pairs[$name]) : Option::none();
    }

    /**
     * Give $sessionId the name $name, replacing whatever name it had. A name belongs to ONE session:
     * naming a second session with a taken name would make the folder ambiguous, so this answers false
     * rather than quietly dropping the older mapping.
     */
    public function name(string $sessionId, string $name): bool
    {
        $taken = $this->idOf($name);

        if ($taken->isSome() && $taken->unwrap() !== $sessionId) {
            return false;
        }

        $pairs = $this->pairs();

        foreach ($pairs as $existing => $id) {
            if ($id === $sessionId) {
                unset($pairs[$existing]);
            }
        }

        $pairs[$name] = $sessionId;
        $this->save($pairs);

        return true;
    }

    /**
     * Drop $name, so its session answers to its hash again.
     */
    public function forget(string $name): bool
    {
        $pairs = $this->pairs();

        if (! array_key_exists($name, $pairs)) {
            return false;
        }

        unset($pairs[$name]);
        $this->save($pairs);

        return true;
    }

    /**
     * Every name, in the order they were given.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->pairs();
    }

    /**
     * @return array<string, string>
     */
    private function pairs(): array
    {
        $pairs = [];

        foreach ($this->file->read()->items() as $line) {
            [$name, $id] = array_pad(explode(self::SEPARATOR, $line, 2), 2, '');

            if ($name !== '' && $id !== '') {
                $pairs[$name] = $id;
            }
        }

        return $pairs;
    }

    /**
     * @param  array<string, string>  $pairs
     */
    private function save(array $pairs): void
    {
        $lines = [];

        foreach ($pairs as $name => $id) {
            $lines[] = $name . self::SEPARATOR . $id;
        }

        $this->file->write($this->file->read()->withItems($lines));
    }
}
