<template>
    <div class="grammar">
        <div class="grammar-head">
            <h2>{{ grammar.title }}</h2>
            <div class="muted">{{ grammar.titleRo }}</div>
        </div>
        <section
            v-for="(section, i) in grammar.sections"
            :key="i"
            class="card g-sec"
        >
            <h3>{{ section.headingRo }}</h3>
            <p v-html="md(section.textRo)" />
            <div v-if="section.table" class="table-wrap">
                <table class="g-table">
                    <thead>
                        <tr>
                            <th
                                v-for="(header, h) in section.table.headers"
                                :key="h"
                            >
                                {{ header }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, r) in section.table.rows" :key="r">
                            <td
                                v-for="(cell, c) in row"
                                :key="c"
                                v-html="md(cell)"
                            />
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="examples">
                <div
                    v-for="(example, e) in section.examples"
                    :key="e"
                    class="example"
                >
                    <SpeakButton :text="example.en" />
                    <div>
                        <TapText class="en" tag="div" :text="example.en" />
                        <div class="ro">{{ example.ro }}</div>
                    </div>
                </div>
            </div>
        </section>
        <section v-if="grammar.tipsRo.length" class="card tips">
            <h3>{{ $t('⚠️ Watch out — common mistakes') }}</h3>
            <ul>
                <li
                    v-for="(tip, t) in grammar.tipsRo"
                    :key="t"
                    v-html="md(tip)"
                />
            </ul>
        </section>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import TapText from '@/components/content/TapText.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import type { Grammar } from '@/types';
import { md } from '@/utils/text';

/** The grammar lesson of a unit: sections with tables and spoken examples, then the common mistakes. */
export default defineComponent({
    name: 'GrammarLesson',

    components: { SpeakButton, TapText },

    props: {
        grammar: { type: Object as PropType<Grammar>, required: true },
    },

    methods: {
        // The texts come from the course files; md() escapes them before adding its own tags.
        md,
    },
});
</script>
