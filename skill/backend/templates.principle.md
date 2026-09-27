The test is simple: **can you see the output by reading the source?**

A heredoc can be read as the thing it produces. Its indentation is real whitespace, its blank lines
are blank lines, and the parts that vary are interpolations sitting exactly where they will land. The
source and the output have the same shape, so a reader checks them against each other at a glance.

An array of line fragments joined with a newline has neither property:

- The **delimiters come apart.** `'/**'` and `' */'` are separate elements with the body between
  them, so a docblock is not visibly a docblock — it is three unrelated strings that happen to be
  adjacent.
- The **indentation is spelled**, not laid out. `'    $router->middleware('` asks the reader to count
  characters inside a quote instead of seeing alignment.
- The **escapes fight the interpolation.** `"\${$var} = function (Router \$router): void {"` has to
  be decoded before you know what it emits.
- The **join is somewhere else.** `implode("\n", $lines)` can be ten lines away, so nothing at the
  array itself says these are LINES at all. Change the separator and every element silently means
  something different.

### The fix

State it once, as a heredoc, and interpolate what varies:

```php
$body = implode("\n", $entries);   // the part that is genuinely computed

return <<<PHP
    /**
     * {$purpose}
     */
    \${$var} = function (Router \$router): void {
        \$router->middleware(
    {$body}
        );
    };
    PHP;
```

The fixed shape is now visible and the computed part is one hole in it.

### What is NOT this sin

- **Joining values the program computed.** `implode("\n", array_map(fn ($f) => $f->label, $findings))`
  is a list being presented, not a template — there is no fixed shape for a heredoc to state.
- **Joining into ONE line.** `implode(', ', $columns)` builds a value, not a layout.
- **A pair.** Two lines is not a template; its shape is already visible.
- **A genuinely dynamic set of lines**, where which lines appear is the whole point. Reach for a
  heredoc per line-kind if the lines themselves have shape; otherwise leave it.

### `sprintf` is the small sibling

For a ONE-line string with holes, `sprintf('%s (%d)', $name, $count)` states the shape the same way
concatenation does not. The principle is identical: put the literal in one piece and let the values
sit in it.