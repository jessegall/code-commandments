### The order is fixed

It costs nothing to follow and nothing to remember:

1. the docstring;
2. constants — `UPPER_CASE` names and `ClassVar`s, class-level facts;
3. fields — a dataclass's annotated names, a plain class's class attributes;
4. `__init__` and `__post_init__`;
5. the methods — properties among them, since a property is behaviour that reads the fields above.

Within one group nothing is prescribed: which constant comes first is the author's business, and a tight
run of related fields should stay tight.

### The pull the other way

It is always the same, and always a mistake: a class attribute added next to the method that uses it,
because that is where the author was typing. It reads well while the whole class is in your head.
Afterwards it is a fact about the object hidden inside its behaviour, and the next reader, looking for
what the class holds, has no way to know they reached the end of the list.

If the head of the class feels too long to read, the class is holding too much: split it. Do not solve a
crowded inventory by scattering it.