<template>
    <div class="vcard-in fade-in">
        <div class="verb-forms">
            <span>{{ verb.v1 }}</span>
            <i>–</i>
            <span>{{ verb.v2 }}</span>
            <i>–</i>
            <span>{{ verb.v3 }}</span>
            <SpeakButton :text="spoken" />
        </div>
        <div class="verb-labels">
            <span>V1</span>
            <span>{{ $t('V2 · past') }}</span>
            <span>{{ $t('V3 · participle') }}</span>
        </div>
        <div v-if="verb.ipa" class="vipa">{{ verb.ipa }}</div>
        <div class="vro">{{ verb.ro || '' }}</div>
        <div class="row center wrap">
            <LevelChip :level="verb.levelId" />
            <ChipTag kind="now">{{ patternLabel }}</ChipTag>
        </div>
        <div class="verb-usage">
            <div
                v-for="row in usageRows"
                :key="row.form"
                class="verb-usage-row"
            >
                <strong>{{ row.form }}</strong>
                <span class="muted small">{{ $t(row.when) }}</span>
                <span>{{ row.example }}</span>
            </div>
        </div>
        <div v-if="verb.ex2" class="vex">
            <SpeakButton :text="verb.ex2" />
            <div>
                <div class="en" v-html="mark(verb.ex2, verb.alts2)" />
                <div class="ro">{{ verb.ex2Ro || '' }}</div>
            </div>
        </div>
        <div v-if="verb.ex3" class="vex">
            <SpeakButton :text="verb.ex3" />
            <div>
                <div class="en" v-html="mark(verb.ex3, verb.alts3)" />
                <div class="ro">{{ verb.ex3Ro || '' }}</div>
            </div>
        </div>
        <div class="row center mt">
            <button
                type="button"
                class="btn help-btn"
                @click.stop="$emit('help')"
            >
                {{ $t('❓ How the three forms are used') }}
            </button>
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import LevelChip from '@/components/general/LevelChip.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import type { UsageRow, VerbItem } from './verbs';
import { PATTERNS, mark, spokenForms, usage } from './verbs';

/** The full card of a verb: forms, pronunciation, meaning, pattern, how the forms are used, two examples. */
export default defineComponent({
    name: 'VerbCard',

    components: { ChipTag, LevelChip, SpeakButton },

    props: {
        verb: { type: Object as PropType<VerbItem>, required: true },
    },

    emits: ['help'],

    computed: {
        spoken(): string {
            return spokenForms(this.verb);
        },

        patternLabel(): string {
            const pattern = PATTERNS[this.verb.pattern];

            return `${this.$t(pattern.name)} · ${pattern.example}`;
        },

        usageRows(): UsageRow[] {
            return usage(this.verb);
        },
    },

    methods: {
        // The examples come from verbs.json; mark() escapes them before adding the bold.
        mark,
    },
});
</script>
