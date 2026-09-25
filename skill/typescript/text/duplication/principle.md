### A copy is a decision made twice

Two functions with the same body are the same code whatever they are called — and the
case worth catching is exactly when they are NOT called the same, because then nobody
searching for one finds the other. Hoist the body to ONE home and let every caller use it:

- a plain function in a shared module (`utils/money.ts`) when it only computes;
- a composable (`useOrders()`) when it holds reactive state or lifecycle;
- a method on the class that owns the data, when it reads one object's fields.

### A near-copy is a missing parameter

Two bodies with the same control flow that differ only in a literal — an endpoint, a
field name, a label — are one function waiting for an argument. Name what differs and
pass it; do not keep two copies because the difference "is only a string". The string
is the parameter.

### What is NOT duplication

Short bodies are alike by coincidence: a one-line delegate, a getter, a `return x.y`
cannot be hoisted into anything smaller than itself. Neither can two constructors of
two different classes. Duplication is a body of real substance, twice.