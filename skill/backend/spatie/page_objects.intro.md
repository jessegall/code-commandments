A page object is a `Data` class that assembles one page's whole payload and hands it back to the
frontend. Let it build ITSELF: seed it from an id, pull its collaborators from the container (hidden), and
let each slot be a computed projection — not a constructor that imperatively fills field after field.