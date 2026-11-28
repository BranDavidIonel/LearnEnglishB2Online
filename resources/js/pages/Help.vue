<template>
    <Head :title="$t('Help')" />
    <PageHeader
        :title="$t('❓ Help')"
        :sub="
            $t(
                'How to read the pronunciation, what to do every day and what helps besides the app.',
            )
        "
        :back="homeUrl"
    />
    <div class="tabs">
        <Link
            v-for="item in tabs"
            :key="item.id"
            :href="item.href"
            class="tab"
            :class="{ on: item.id === tab }"
        >
            {{ item.label }}
        </Link>
    </div>
    <SoundGuide v-if="tab === 'sounds'" :guide="sounds" />
    <StudyPlan v-else-if="tab === 'plan'" :plan="plan" />
    <StudyTips v-else :tips="tips" />
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import SoundGuide from '@/components/modules/help/SoundGuide.vue';
import StudyPlan from '@/components/modules/help/StudyPlan.vue';
import StudyTips from '@/components/modules/help/StudyTips.vue';
import type {
    HelpTab,
    SoundGuideData,
    StudyPlanData,
    StudyTip,
} from '@/components/modules/help/types';
import { help, home } from '@/routes';

/** Help: the pronunciation signs explained, the daily plan and the advice, one tab each. */
export default defineComponent({
    name: 'HelpPage',

    components: { Head, Link, PageHeader, SoundGuide, StudyPlan, StudyTips },

    props: {
        tab: { type: String as PropType<HelpTab>, required: true },
        sounds: { type: Object as PropType<SoundGuideData>, required: true },
        plan: { type: Object as PropType<StudyPlanData>, required: true },
        tips: { type: Array as PropType<StudyTip[]>, required: true },
    },

    computed: {
        homeUrl(): string {
            return home().url;
        },

        tabs(): { id: HelpTab; href: string; label: string }[] {
            return [
                {
                    id: 'sounds',
                    href: help().url,
                    label: this.$t('🔤 Sounds'),
                },
                {
                    id: 'plan',
                    href: help('plan').url,
                    label: this.$t('🗓️ Daily plan'),
                },
                {
                    id: 'tips',
                    href: help('tips').url,
                    label: this.$t('💡 Advice'),
                },
            ];
        },
    },
});
</script>
