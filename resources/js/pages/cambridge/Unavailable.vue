<template>
    <Head :title="$t('Exam not available')" />

    <EmptyState icon="🤷" :title="$t('Exam not available')">
        <template #actions>
            <Link class="btn primary" :href="cambridge().url">
                {{ $t('Back') }}
            </Link>
        </template>
    </EmptyState>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import EmptyState from '@/components/general/EmptyState.vue';
import { cambridge } from '@/routes';

/** A Cambridge exam that is not in the course. */
export default defineComponent({
    name: 'CambridgeUnavailablePage',

    components: { Head, Link, EmptyState },

    methods: {
        cambridge,
    },
});
</script>
