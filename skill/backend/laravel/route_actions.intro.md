A route action is the thin boundary between an HTTP request and your domain. Its job is to
translate the request and hand off — once. Two routes that answer the same operation, or two controllers
that do the same thing, are duplication at the boundary: one operation deserves exactly one way in.