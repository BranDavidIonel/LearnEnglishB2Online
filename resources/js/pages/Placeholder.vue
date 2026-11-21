<template>
    <Head :title="$t('This page is being built')" />

    <section class="card">
        <h1>🚧 {{ $t('This page is being built') }}</h1>
        <p class="muted">
            {{ $t('It will be here soon.') }} <code>{{ page }}</code>
        </p>
    </section>
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';

/** Stands in for a page that is not built yet (PlaceholderController). */
export default defineComponent({
    name: 'PlaceholderPage',

    components: { Head },

    props: {
        /** The name of the route that was opened, e.g. "units.show". */
        page: { type: String as PropType<string>, required: true },
    },
});
</script>
