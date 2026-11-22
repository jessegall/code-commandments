<script setup lang="ts">
const items = ['a', 'b']
const shown = true
const label = 'x'
const view = 'section'
function pick(name: string): void {}
</script>

<template>
    <!-- @contract kind/element -->
    <!-- @contract fact/element/tag = "section" -->
    <!-- @contract fact/element/form = "native" -->
    <!-- @contract role/element/attributes -> [classAttr] -->
    <!-- @label classAttr attribute -->
    <!-- @contract kind/attribute -->
    <!-- @contract fact/*/name on attribute = "class" -->
    <!-- @contract role/attribute/value -> listLit -->
    <!-- @label listLit literal -->
    <!-- @contract role/element/children -> [pEl, inputEl, ulEl, transitionEl, componentEl] -->
    <section class="list">
        <!-- @contract kind/element -->
        <!-- @label pEl element -->
        <!-- @contract fact/element/tag = "p" -->
        <!-- @contract role/element/children -> [plainText] -->
        <!-- @label plainText text -->
        <!-- @contract kind/text -->
        <!-- @contract fact/text/value = "plain" -->
        <p>plain</p>
        <!-- @contract kind/element -->
        <!-- @label inputEl element -->
        <!-- @contract fact/element/tag = "input" -->
        <!-- @contract role/element/attributes -> [modelDir, bindDir, onDir] -->
        <!-- @label modelDir directive 16 -->
        <!-- @label bindDir directive 32 -->
        <!-- @label onDir directive 51 -->
        <!-- @contract kind/directive at 16 -->
        <!-- @contract fact/*/name on directive at 16 = "model" -->
        <!-- @contract role/directive/value at 16 -> modelValue -->
        <!-- @label modelValue reference 25 -->
        <!-- @contract link/directive/body at 16 -> inputEl -->
        <!-- @contract fact/*/name on directive at 32 = "bind" -->
        <!-- @contract fact/directive/shorthand at 32 = true -->
        <!-- @contract role/directive/argument at 32 -> disabledArg -->
        <!-- @label disabledArg reference 33 -->
        <!-- @contract role/directive/value at 32 -> notShown -->
        <!-- @label notShown unary 43 -->
        <!-- @contract fact/*/name on directive at 51 = "on" -->
        <!-- @contract fact/directive/shorthand at 51 = true -->
        <!-- @contract role/directive/argument at 51 -> inputArg -->
        <!-- @label inputArg reference 52 -->
        <!-- @contract role/directive/value at 51 -> pickCall -->
        <!-- @label pickCall call 59 -->
        <input v-model="label" :disabled="!shown" @input="pick(label)">
        <!-- @contract kind/element -->
        <!-- @label ulEl element -->
        <!-- @contract fact/element/tag = "ul" -->
        <!-- @contract role/element/children -> [liEl] -->
        <ul>
            <!-- @contract kind/element -->
            <!-- @label liEl element -->
            <!-- @contract fact/element/tag = "li" -->
            <!-- @contract role/element/attributes -> [forDir, keyDir] -->
            <!-- @label forDir directive 17 -->
            <!-- @label keyDir directive 48 -->
            <!-- @contract fact/*/name on directive at 17 = "for" -->
            <!-- @contract role/directive/bindings at 17 -> [itemB, indexB] -->
            <!-- @label itemB binding 25 -->
            <!-- @label indexB binding 31 -->
            <!-- @contract role/directive/iterable at 17 -> itemsRef -->
            <!-- @label itemsRef reference 41 -->
            <!-- @contract fact/*/name on directive at 48 = "bind" -->
            <!-- @contract fact/directive/shorthand at 48 = true -->
            <!-- @contract role/directive/argument at 48 -> keyArg -->
            <!-- @label keyArg reference 49 -->
            <!-- @contract role/directive/value at 48 -> indexRef -->
            <!-- @label indexRef reference 54 -->
            <!-- @contract link/reference/binding at 54 -> indexB -->
            <!-- @contract role/element/children -> [itemInterp] -->
            <!-- @label itemInterp embedded-expression -->
            <!-- @contract kind/embedded-expression -->
            <!-- @contract role/embedded-expression/expression -> itemRead -->
            <!-- @label itemRead reference 64 -->
            <!-- @contract link/reference/binding at 64 -> itemB -->
            <li v-for="(item, index) in items" :key="index">{{ item }}</li>
        </ul>
        <!-- @contract kind/element at 9 -->
        <!-- @label transitionEl element 9 -->
        <!-- @contract fact/element/tag at 9 = "transition" -->
        <!-- @contract fact/element/form at 9 = "component" -->
        <!-- @contract role/element/attributes at 9 -> [ifDir, nameAttr] -->
        <!-- @label ifDir directive 21 -->
        <!-- @label nameAttr attribute 34 -->
        <!-- @contract fact/*/name on directive at 21 = "if" -->
        <!-- @contract role/directive/value at 21 -> shownCond -->
        <!-- @label shownCond reference 27 -->
        <!-- @contract link/directive/body at 21 -> transitionEl -->
        <!-- @contract fact/*/name on attribute at 34 = "name" -->
        <!-- @contract role/element/children at 9 -> [buttonEl] -->
        <!-- @label buttonEl element 46 -->
        <!-- @contract fact/element/tag at 46 = "button" -->
        <!-- @contract fact/element/form at 46 = "native" -->
        <!-- @contract role/element/attributes at 46 -> [showDir, clickDir] -->
        <!-- @label showDir directive 54 -->
        <!-- @label clickDir directive 69 -->
        <!-- @contract fact/*/name on directive at 54 = "show" -->
        <!-- @contract fact/*/name on directive at 69 = "on" -->
        <!-- @contract fact/*/modifiers on directive at 69 = ["stop"] -->
        <!-- @contract role/element/children at 46 -> [goText] -->
        <!-- @label goText text -->
        <!-- @contract kind/text -->
        <!-- @contract fact/text/value = "go" -->
        <transition v-if="shown" name="fade"><button v-show="shown" @click.stop="pick('x')">go</button></transition>
        <!-- @contract kind/element -->
        <!-- @label componentEl element -->
        <!-- @contract fact/element/tag = "component" -->
        <!-- @contract fact/element/form = "component" -->
        <!-- @contract role/element/attributes -> [isDir, slotDir] -->
        <!-- @label isDir directive 20 -->
        <!-- @label slotDir directive 31 -->
        <!-- @contract fact/*/name on directive at 20 = "bind" -->
        <!-- @contract fact/directive/shorthand at 20 = true -->
        <!-- @contract role/directive/argument at 20 -> isArg -->
        <!-- @label isArg reference 21 -->
        <!-- @contract role/directive/value at 20 -> viewRef -->
        <!-- @label viewRef reference 25 -->
        <!-- @contract fact/*/name on directive at 31 = "slot" -->
        <!-- @contract role/directive/pattern at 31 -> slotPattern -->
        <!-- @label slotPattern pattern 39 -->
        <!-- @contract kind/pattern at 39 -->
        <!-- @contract fact/pattern/form at 39 = "mapping" -->
        <!-- @label titleBinding binding 41 -->
        <!-- @contract role/element/children -> [titleInterp] -->
        <!-- @label titleInterp embedded-expression -->
        <!-- @contract role/embedded-expression/expression -> titleRef -->
        <!-- @label titleRef reference 53 -->
        <!-- @contract link/reference/binding at 53 -> titleBinding -->
        <component :is="view" v-slot="{ title }">{{ title }}</component>
    </section>
</template>
