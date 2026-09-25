`render($order, true)` — true *what*? The caller already knows which of the two
things it wants; the flag is that decision, flattened into a truth value and
handed over for the callee to unpack again.