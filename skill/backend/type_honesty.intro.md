A `?T` that is never actually null is a lie the whole codebase pays for. Every reader has to re-prove the
value is there — `?->`, `?? <default>`, an `if ($x === null)` — and one of those defaults silently answers
a question for a state that can't happen. Make the type say what the design guarantees.