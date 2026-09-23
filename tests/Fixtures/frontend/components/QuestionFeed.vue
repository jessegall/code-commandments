<script setup lang="ts">
// A copy of the review loader from the review-feed module, pointed at another endpoint.
import { ref } from 'vue'

interface Entry {
    id: number
    published: boolean
}

const props = defineProps<{ productId: number }>()
const questionIds = ref<number[]>([])

// @sin NearDuplicateFunction
const loadQuestions = async (productId: number): Promise<number[]> => {
    const result = await fetch(`/api/products/${productId}/questions`)
    if (!result.ok) {
        throw new Error(result.statusText)
    }
    const questions: Entry[] = await result.json()
    const answered = questions.filter((question) => question.published)
    return answered.map((question) => question.id)
}

loadQuestions(props.productId).then((ids) => (questionIds.value = ids))
</script>

<template>
    <span class="questions">{{ questionIds.length }}</span>
</template>
