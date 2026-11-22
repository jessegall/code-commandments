<script setup lang="ts">
import { ref } from 'vue'

interface Row {
    id: number
    label?: string
}

const props = defineProps<{ rows: Row[] | null }>()
const count = ref(0)
const picked = ref<Row | null>(null)

function choose(row: Row): void {
    picked.value = row
}
</script>

<template>
    <ul id="rows" class="table" hidden>
        <li
            v-for="({ id, label }, index) in props.rows ?? []"
            :key="id"
            :class="{ odd: index % 2 === 1, picked: picked?.id === id }"
            @mouseenter="++count"
            @click.stop="
                if (!picked) {
                    choose(props.rows![index])
                } else {
                    count += 1
                }
            "
        >
            {{ label ?? `row ${id}` }}
            <button type="button" @click="async () => { await choose(props.rows?.[index] as Row) }">
                {{ count > 0 ? 'again' : 'pick' }}
            </button>
        </li>
        <Cell
            v-slot="{ row, total }"
            :value="new Date()"
            :fn="(a?: number, ...rest: number[]) => a ?? 0"
            :list="[...(props.rows ?? [])]"
            :kind="picked instanceof Object"
        />
    </ul>
</template>
