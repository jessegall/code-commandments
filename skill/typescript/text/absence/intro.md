TypeScript can say a value is missing in two ways, and the difference matters.
`null` is an absence someone WROTE; `undefined` is an absence that simply happened —
a property never set, an argument never passed. A codebase that treats them as
interchangeable has no idea which it is looking at, and the `?? default` written to
cope with either is the moment a real absence stops being handled and starts being
hidden.