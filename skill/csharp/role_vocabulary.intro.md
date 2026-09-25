Three shapes recur everywhere: a keyed store, a membership set, a first-match dispatcher. Each has a name
and a contract. Name the class for the role and keep the contract — a `*Registry` whose `Get` returns `null`,
or a `*Resolver` that does not dispatch, is a lie every caller pays for.