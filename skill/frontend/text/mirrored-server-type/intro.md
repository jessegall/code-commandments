A TypeScript type that restates a backend `Data` class is a **second source of
truth for one contract**. The two can't be kept in sync by discipline — the day a
field is added, renamed, or retyped on the server, the hand-written twin lies, and
nothing tells you. The server already knows the shape; let it emit the type.