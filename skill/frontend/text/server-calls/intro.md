The server declares every address it answers and every header it expects. A component that writes a path by
hand, or calls `fetch` with its own headers, keeps a **second copy** of those facts. The copies drift: a
route is renamed and a link breaks without an error, a header is added and the hand-rolled calls forget it.
