`Render(order, true)` — true *what*? The caller already knows which of the two things it wants; the
flag is that decision, squeezed into a `bool` and handed over for the method to unpack again.