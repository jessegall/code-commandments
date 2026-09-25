The head of a class is its inventory. Reading the first screen should tell you everything the object
holds — what it is made of, what it is configured by, what it can never change — before a single line of
behaviour asks for your attention. That reading is only reliable if it is TOTAL: one property declared
two hundred lines down turns "the state is up here" into "the state is wherever you happen to find it",
and every future reader has to scan the whole class to be sure they have seen it all.

The order is fixed, so it costs nothing to follow and nothing to remember:

1. trait uses — they inject members, so they are read first;
2. enum cases, in an enum: the cases ARE the type;
3. constants;
4. static properties — class-level state, a different thing from an instance's;
5. properties, widest visibility first: public, then protected, then private;
6. hooked properties last, because a derived slot reads FROM the fields above it — a computed
   `$fullName` placed before `$first` and `$last` asks you to read the answer before the inputs;
7. the constructor, then the methods.

Promotion in the constructor signature is state at the top too — it sits above every method that reads
it, so a promoted property is never out of place. Within one group nothing is prescribed: which constant
comes first is the author's business, and a tight run of related fields should stay tight.

The pull the other way is always the same, and always a mistake: a field is added "next to the method
that uses it", because that is where the author was typing. It reads well for the ten minutes you hold
the whole class in your head. Afterwards it is a fact about the object hidden inside its behaviour — and
a second reader, looking for what this class holds, has no way to know they reached the end of the list.

If the head of the class genuinely feels too long to read, the class is holding too much: split it. Do
not solve a crowded inventory by scattering the inventory.