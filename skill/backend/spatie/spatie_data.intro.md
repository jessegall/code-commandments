A `Data` class already knows how to be built from an array, copied, serialised, and collected. Your job
is to declare **honest, typed, readonly** properties and let the framework do the mapping. The moment
you hand-roll the array↔object plumbing, you've taken work the library does declaratively — and made it
wrong.