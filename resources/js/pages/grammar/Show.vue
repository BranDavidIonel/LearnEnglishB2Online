<template>
    <Head :title="unit.grammar.title" />
    <Link class="back" :href="indexUrl">{{ $t('← All grammar') }}</Link>
    <GrammarLesson :grammar="unit.grammar" />
    <div class="row center mt">
        <Link class="btn primary" :href="unitUrl">
            {{ $t('Go to unit :n →', { n: unit.order }) }}
        </Link>
    </div>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import GrammarLesson from '@/components/content/GrammarLesson.vue';
import type { GrammarUnit } from '@/components/modules/home/types';
import { grammar } from '@/routes';
import units from '@/routes/units';

/** One unit's grammar lesson, read on its own, with a way to the unit. */
export default defineComponent({
    name: 'GrammarShowPage',

    components: { GrammarLesson, Head, Link },

    props: {
        unit: { type: Object as PropType<GrammarUnit>, required: true },
    },

    computed: {
        indexUrl(): string {
            return grammar().url;
        },

        unitUrl(): string {
            return units.show(this.unit.id).url;
        },
    },
});
</script>
