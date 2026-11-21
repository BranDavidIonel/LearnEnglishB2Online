<template>
    <div class="row wrap filters">
        <input
            v-model="search"
            class="search"
            type="search"
            :placeholder="
                $t('🔍 Search: go, went, gone or a Romanian meaning…')
            "
        />
        <select v-model="level">
            <option value="all">{{ $t('All levels') }}</option>
            <option v-for="info in LEVELS" :key="info.id" :value="info.id">
                {{ info.name }}
            </option>
        </select>
        <select v-model="status">
            <option value="all">{{ $t('Any status') }}</option>
            <option v-for="id in STATUS_ORDER" :key="id" :value="id">
                {{ $t(STATUS[id].label) }}
            </option>
        </select>
        <select v-model="pattern">
            <option value="all">{{ $t('Any pattern') }}</option>
            <option v-for="(info, id) in PATTERNS" :key="id" :value="id">
                {{ $t(info.name) }} ({{ info.example }})
            </option>
        </select>
        <span class="muted">
            {{ $t(':count verb|:count verbs', { count: rows.length }) }}
        </span>
    </div>
    <div class="word-list">
        <div
            v-for="verb in rows"
            :key="verb.v1"
            class="word-row verb-row"
            @click="$emit('open', verb)"
        >
            <span class="verb-rank muted">#{{ verb.rank }}</span>
            <span class="word-en">
                <strong>{{ forms(verb) }}</strong>
                <small class="muted">{{ verb.ipa || '' }}</small>
            </span>
            <span class="word-ro">{{ verb.ro || '' }}</span>
            <LevelChip :level="verb.levelId" />
            <ChipTag :kind="STATUS[verb.status].kind">
                {{ $t(STATUS[verb.status].label) }}
            </ChipTag>
            <span class="word-box" :title="$t('How well you know it')">
                <i
                    v-for="n in BOX_MARKS"
                    :key="n"
                    :class="{ on: verb.box !== null && verb.box > n }"
                />
            </span>
            <span class="word-acts">
                <button
                    v-if="verb.status !== 'learned'"
                    class="icon-btn word-act"
                    :title="$t('Learn it now')"
                    @click.stop="$emit('learn', verb)"
                >
                    🧠
                </button>
                <button
                    class="icon-btn speak"
                    :title="$t('Listen')"
                    @click.stop="sayForms(verb)"
                >
                    🔊
                </button>
            </span>
        </div>
        <p v-if="!rows.length" class="muted center">
            {{ $t('No verb matches this search.') }}
        </p>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import LevelChip from '@/components/general/LevelChip.vue';
import type { ReviewStatus } from '@/types';
import { LEVELS } from '@/utils/levels';
import type { VerbItem } from './verbs';
import { PATTERNS, STATUS, forms, matchesSearch, sayForms } from './verbs';

// The four marks of "how well you know it": boxes 1 to 4 and up light them.
const BOX_MARKS = [0, 1, 2, 3];
const STATUS_ORDER: ReviewStatus[] = ['new', 'learning', 'learned'];

/** The verb list with its search and filters (level, status, pattern). A click on a row opens the verb. */
export default defineComponent({
    name: 'VerbList',

    components: { ChipTag, LevelChip },

    props: {
        verbs: { type: Array as PropType<VerbItem[]>, required: true },
    },

    emits: {
        open: (verb: VerbItem) => !!verb,
        learn: (verb: VerbItem) => !!verb,
    },

    data() {
        return {
            BOX_MARKS,
            LEVELS,
            PATTERNS,
            STATUS,
            STATUS_ORDER,
            search: '',
            level: 'all',
            status: 'all',
            pattern: 'all',
        };
    },

    computed: {
        rows(): VerbItem[] {
            const query = this.search.trim().toLowerCase();

            return this.verbs.filter(
                (verb) =>
                    matchesSearch(verb, query) &&
                    (this.level === 'all' || verb.levelId === this.level) &&
                    (this.status === 'all' || verb.status === this.status) &&
                    (this.pattern === 'all' || verb.pattern === this.pattern),
            );
        },
    },

    methods: {
        forms,
        sayForms,
    },
});
</script>
