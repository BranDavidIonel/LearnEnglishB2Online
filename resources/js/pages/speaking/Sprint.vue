<template>
    <Head :title="$t('Voice sprint')" />
    <PageHeader
        :title="$t('⚡ Voice sprint')"
        :sub="
            $t(
                'See the word in Romanian and say it in English. The microphone listens the whole time.',
            )
        "
        :back="speaking().url"
    />
    <VoiceSprint v-if="recognition" :words="words" :back="speaking().url" />
    <div v-else>
        <EmptyState
            icon="🎙"
            :title="$t('This game needs speech recognition')"
            :text="$t('It works in Chrome and Edge.')"
        >
            <template #actions>
                <Link class="btn primary" :href="speaking().url">
                    {{ $t('Back') }}
                </Link>
            </template>
        </EmptyState>
    </div>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import EmptyState from '@/components/general/EmptyState.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import VoiceSprint from '@/components/modules/speaking/VoiceSprint.vue';
import { speaking } from '@/routes';
import { canListen } from '@/utils/recognition';
import type { SprintWord } from '@/utils/speaking';

/** The 60-second voice sprint (it needs speech recognition). */
export default defineComponent({
    name: 'SpeakingSprint',

    components: { EmptyState, Head, Link, PageHeader, VoiceSprint },

    props: {
        /** Words the learner should know, short enough to say in one breath. */
        words: { type: Array as PropType<SprintWord[]>, required: true },
    },

    data() {
        return {
            recognition: canListen(),
        };
    },

    methods: {
        speaking,
    },
});
</script>
