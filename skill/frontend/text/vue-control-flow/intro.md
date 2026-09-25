A `v-if="status === 'paid'"` / `v-else-if="status === 'pending'"` / `v-else` chain
is a `switch` in disguise: one subject (`status`), tested case by case. Each
`v-else-if` restates the subject and reads as a fresh, independent decision when
there is really only one — *which case is this value?*