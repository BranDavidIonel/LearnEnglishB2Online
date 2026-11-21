<template>
    <div class="tabs">
        <Link
            v-for="tab in tabs"
            :key="tab.id"
            class="tab"
            :class="{ on: tab.id === active }"
            :href="tab.href"
            :only="['tab']"
            preserve-state
        >
            {{ $t(tab.label, { n: tab.count }) }}
        </Link>
        <Link
            v-if="verbs"
            class="tab tab-verbs"
            :class="{ on: active === 'verbs' }"
            :href="verbsUrl"
        >
            {{ $t('🔁 Irregular verbs (:n)', { n: verbs }) }}
        </Link>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { verbs as verbsRoute, words } from '@/routes';
import type { WordCounts, WordTab } from './types';

interface TabLink {
    id: WordTab;
    label: string;
    count: number;
    href: string;
}

// The labels are translation keys with the count in them (the tabs are plural in Romanian,
// the status chips singular); `count` names the number shown.
const TABS: { id: WordTab; label: string; count: keyof WordCounts }[] = [
    { id: 'all', label: 'All (:n)', count: 'total' },
    { id: 'learned', label: '✅ Learned (:n)', count: 'learned' },
    { id: 'learning', label: '🌱 Learning (:n)', count: 'learning' },
    { id: 'new', label: '🆕 To learn (:n)', count: 'new' },
];

/**
 * The tab row of "My words": all / learned / learning / to learn with their counts, and the
 * irregular verbs. The word tabs only swap the `tab` prop when the words page is already
 * open (the long word list stays); from the verbs page they open the words page.
 */
export default defineComponent({
    name: 'WordTabs',

    components: { Link },

    props: {
        counts: { type: Object as PropType<WordCounts>, required: true },
        /** How many irregular verbs there are (the tab is hidden without any). */
        verbs: { type: Number, default: 0 },
        active: {
            type: String as PropType<WordTab | 'verbs'>,
            required: true,
        },
    },

    computed: {
        tabs(): TabLink[] {
            return TABS.map((tab) => ({
                id: tab.id,
                label: tab.label,
                count: this.counts[tab.count],
                href: (tab.id === 'all' ? words() : words(tab.id)).url,
            }));
        },

        verbsUrl(): string {
            return verbsRoute().url;
        },
    },
});
</script>
