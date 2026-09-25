### The order is fixed

1. constants — `const` fields and `static readonly` values, facts about the type;
2. fields;
3. stored properties — auto-properties like `{ get; init; }`, and properties given a starting value;
4. constructors;
5. everything that does something — methods, and properties computed from the state above (`=> Net + Vat`),
   which are behaviour even though they read like data.

Within one group the order is yours: which constant comes first is the author's business, and a group of
related fields should stay together.

### The pull the other way

It is always the same, and always a mistake: a field added next to the method that uses it, because that is
where you were typing. It reads fine while the whole class is in your head. Afterwards it is a fact about
the object hidden among its behaviour, and the next reader, looking for what the class holds, cannot tell
when they have reached the end of the list.

If the top of the class feels too long to read, the class holds too much: split it. Don't fix a crowded
list by scattering it.