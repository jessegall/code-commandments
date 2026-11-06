---
name: Comments only on types
description: Only a type carries a comment here, saying what it is for; read before writing any comment in this project.
---

# Comments only on types

A comment here is one line above a type saying what the type is for. Nothing else carries one.

No comment above a function or a method, no comment above a const or a var, no comment on a field,
no comment inside a body, and no comment at the end of a line of code.

The reason is what comments did to this project. Sixteen thousand lines of them explained the
reasoning behind code that had since changed, and four of them were measurably false: the share of
a judging the parse takes, the per-file parse cost, the cost of the stat sweep, and the cost of an
edit. An agent read them as facts and built on them, and the design they pointed at was the wrong
one. A comment that is not checked is a claim nobody verifies, and it outranks the code in a
reader's mind.

So the code says what it does, and the names carry the intent. A test's name is its sentence. A
type's one line says what the type is for, and that is the whole of the prose.

What is not a comment for this rule: a `//go:` directive, a generated-file header, and the markers
in `tests/Fixtures`, which are the spec every detector is proved against.
