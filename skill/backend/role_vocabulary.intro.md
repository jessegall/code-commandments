Three shapes recur everywhere: a keyed store, a membership set, a first-match dispatcher. Each has a
name and a contract. Use the name, extend the base, and honour the contract — a `*Registry` that returns
`null`, or a `*Resolver` that doesn't dispatch, is a lie.