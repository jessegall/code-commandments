**Fail hard, fix once** beats *fail gracefully, debug forever.* A loud, named, contextual
exception thrown where the problem is born tells the next reader exactly what broke and why; a
swallowed one hands them a wrong value three calls later and no idea where it came from.