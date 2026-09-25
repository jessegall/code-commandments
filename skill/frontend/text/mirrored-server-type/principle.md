When a backend `Data` class and a frontend `type` describe the SAME shape, exactly one of them may be
authored by hand — and it must be the one that already validates and hydrates the real payload: the server's.
The frontend type is then **derived**, not written.

`spatie/laravel-typescript-transformer` does the deriving. Mark the class `#[TypeScript]`, point the
transformer at an output file, and run the generator (`php artisan typescript:transform`). It emits a
`.d.ts` the whole frontend imports. Delete the hand-written twin and repoint its importers at the generated
type. From then on a change to the `Data` class is a change to the type — the compiler catches the drift the
hand-copy used to hide.

The tell that you have a duplicate, not a coincidence, is a NAME and a FIELD SET that line up (spelling
aside — `first_name` on one side, `firstName` on the other, is still the same field). A purely-frontend
view-model that no server class backs is not this sin: it has one source of truth, itself. The sin is
specifically the COPY of a shape the server already owns.