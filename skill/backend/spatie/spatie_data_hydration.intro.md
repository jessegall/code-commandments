A `Data` object hydrates itself: `::from([...])` builds nested `Data`, `#[DataCollectionOf]`
collections, and enum/date casts straight from a plain array. Feed it the **simplest input** and let it
build. The moment you re-create a nested type, a cast, or a derivation at the call site, you've duplicated
the mapping the class owns — and coupled every caller to it.