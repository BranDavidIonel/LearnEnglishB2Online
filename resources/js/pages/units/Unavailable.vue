<template>
    <Head :title="title" />

    <EmptyState
        :icon="reason === 'locked' ? '🔒' : '🤷'"
        :title="title"
        :text="
            reason === 'locked'
                ? $t(
                      'Finish the previous unit first (its test with at least 80%).',
                  )
                : ''
        "
    >
        <template #actions>
            <Link class="btn primary" :href="learn().url">
                {{ $t('Back to lessons') }}
            </Link>
        </template>
    </EmptyState>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import EmptyState from '@/components/general/EmptyState.vue';
import { learn } from '@/routes';

/** A unit that cannot be opened: not in the course, or not unlocked yet. */
export default defineComponent({
    name: 'UnitUnavailablePage',

    components: { Head, Link, EmptyState },

    props: {
        reason: {
            type: String as PropType<'missing' | 'locked'>,
            required: true,
        },
    },

    computed: {
        title(): string {
            return this.reason === 'locked'
                ? this.$t('Unit locked')
                : this.$t("This unit doesn't exist");
        },
    },

    methods: {
        learn,
    },
});
</script>
