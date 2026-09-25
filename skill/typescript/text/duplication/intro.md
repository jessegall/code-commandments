A function written twice is one decision living in two places. The day it has to
change, one copy gets the fix and the other keeps the bug — and nothing in either file
says the other exists. In a Vue codebase this happens one component at a time: each
`<script setup>` grows its own `formatPrice`, its own `loadPage`, until the behaviour
the app depends on is spread across files that do not know about each other.