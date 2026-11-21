# Fix at the source — what fires, and why

Each row is one rule: the sin's id, the symptom its detector flags, and the detector that flags it. The id is what `{{binary}} info <sin>` takes, and the detector name is what `--detector=` takes if the rule turns out to be wrong.

- **`constructor-side-effect`** — A constructor that performs a side effect on a collaborator and throws away the result, so simply creating the object changes something outside it. — `constructor-side-effect`
- **`mutable-static-state`** — A write to a static property — really a global variable with a namespace attached — where whichever write happens last wins, so the order code runs in changes the result. — `mutable-static-state`
- **`unread-collaborator`** — a collaborator promoted into a private field by the constructor and never read anywhere in the class — the class asks for something it does not use, and only a search of the whole type says so — `unread-collaborator`
