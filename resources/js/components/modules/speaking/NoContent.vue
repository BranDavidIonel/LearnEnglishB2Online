<template>
    <EmptyState
        icon="⏳"
        :title="title"
        :text="$t('Meanwhile, try the role-play from the lessons.')"
    >
        <template #actions>
            <Link class="btn primary" :href="speaking().url">
                {{ $t('Back') }}
            </Link>
        </template>
    </EmptyState>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import EmptyState from '@/components/general/EmptyState.vue';
import { speaking } from '@/routes';

/** A level without this kind of speaking practice: say so and send the learner back to the hub. */
export default defineComponent({
    name: 'NoContent',

    components: { EmptyState, Link },

    props: {
        title: { type: String, required: true },
    },

    methods: {
        speaking,
    },
});
</script>
