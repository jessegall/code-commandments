Every line a reader scans costs them something, and a line that repeats the code, or tells how it came to
be, costs them and gives nothing back. Write documentation only where it tells the reader something the code
does not.

### A doc comment

One sentence in `<summary>` saying what the type or member IS or DOES, present tense, about the code as it is
now. A `<param name="order">The order.</param>` that only repeats the parameter's name and type says nothing
the signature does not; keep a tag only for what a type cannot say: a unit, a limit, which exception and when.
A summary that runs to paragraphs usually means the thing it describes does too much.

### A `//` comment

Rare. The code already says what it does; a comment earns its place by saying *why*, when the reason cannot be
read off the code: a hidden invariant, a workaround for a bug somewhere else, a constraint from outside. A
comment that repeats the line below it (`// add the rate` above `total += rate;`) is noise. Delete it.

### A `cref` points at something real

`<see cref="OrderService"/>` must name a type or member that exists. When the thing it named is renamed or
deleted, the reference is left pointing at nothing — point it at what replaced it, or remove it.

### Never the past

`// formerly lived in Checkout`, `// refactored to use the cache`, `// no longer a dictionary` describe a
version of the code nobody is reading. Git keeps the history. When you replace code, just replace it — don't
leave a comment explaining what it used to be.

### Never argue with a reader who isn't there

`// not random`, `// no magic here` defend the code against a reading nobody made. Say what it IS, or make it
obvious and write nothing.