A method written twice is one decision living in two places. The day it has to
change, one copy gets the fix and the other keeps the bug — and nothing in either class
says the other exists. In a .NET solution it happens one project at a time: each handler
grows its own `ToDto`, each service its own `BuildQuery`, until the behaviour the system
depends on is spread across classes that do not know about each other.