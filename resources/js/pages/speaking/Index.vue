<template>
    <Head :title="$t('Speaking')" />
    <PageHeader
        :title="$t('🗣️ Speaking')"
        :sub="
            $t(
                'You speak aloud, the app listens and tells you what it understood. The more you speak, the more confident you get.',
            )
        "
        :back="home().url"
    />
    <div v-if="!recognition" class="alert warn">
        {{
            $t(
                'This browser has no speech recognition (it works in Chrome and Edge). You can still practise: record yourself and check yourself.',
            )
        }}
    </div>
    <div class="stats">
        <div class="card stat">
            <div class="stat-icon">🎙️</div>
            <div class="stat-num">{{ stats.said }}</div>
            <div class="muted">{{ $t('sentences said well') }}</div>
        </div>
        <div class="card stat">
            <div class="stat-icon">💬</div>
            <div class="stat-num">{{ stats.talks }}</div>
            <div class="muted">{{ $t('conversations finished') }}</div>
        </div>
        <div class="card stat">
            <div class="stat-icon">⚡</div>
            <div class="stat-num">{{ stats.best.sprint || 0 }}</div>
            <div class="muted">{{ $t('voice sprint record') }}</div>
        </div>
    </div>
    <div class="row wrap level-pick">
        <span class="muted">{{ $t('Level:') }}</span>
        <button
            v-for="item in levels"
            :key="item.id"
            type="button"
            class="tab"
            :class="{ on: item.id === chosen.id }"
            :disabled="!item.unlocked"
            :title="item.unlocked ? '' : $t('Locked level')"
            @click="choose(item.id)"
        >
            {{ levelName(item.id) }}{{ item.unlocked ? '' : ' 🔒' }}
        </button>
    </div>
    <div class="game-grid">
        <Link class="card game" :href="modeUrl('talk')">
            <div class="game-icon">🤝</div>
            <h3>{{ $t('Conversations') }}</h3>
            <p class="muted">
                {{
                    $t(
                        'The app is the client, the colleague or the receptionist. You answer aloud.',
                    )
                }}
            </p>
            <ChipTag
                v-if="chosen.scenarios !== null"
                :kind="chosen.talked ? 'ok' : 'lock'"
            >
                {{ chosen.talked }} / {{ chosen.scenarios }}
            </ChipTag>
            <ChipTag v-else kind="warn">{{ $t('coming soon') }}</ChipTag>
        </Link>
        <Link class="card game" :href="modeUrl('repeat')">
            <div class="game-icon">🔁</div>
            <h3>{{ $t('Repeat after me') }}</h3>
            <p class="muted">
                {{
                    $t(
                        'Hear a useful sentence and repeat it. See which words were understood.',
                    )
                }}
            </p>
        </Link>
        <Link class="card game" :href="modeUrl('say')">
            <div class="game-icon">💬</div>
            <h3>{{ $t('Say it in English') }}</h3>
            <p class="muted">
                {{
                    $t(
                        'See the sentence in Romanian and say it in English, without seeing it written.',
                    )
                }}
            </p>
        </Link>
        <Link class="card game" :href="modeUrl('answer')">
            <div class="game-icon">🙋</div>
            <h3>{{ $t('Answer the question') }}</h3>
            <p class="muted">
                {{
                    $t(
                        'Questions about you, like in an interview or small talk with a client.',
                    )
                }}
            </p>
            <ChipTag v-if="chosen.scenarios === null" kind="lock">
                {{ $t('coming soon') }}
            </ChipTag>
        </Link>
        <Link class="card game" :href="speaking({ mode: 'pairs' }).url">
            <div class="game-icon">👂</div>
            <h3>{{ $t('Sound-alike words') }}</h3>
            <p class="muted">
                {{
                    $t(
                        'ship or sheep? Train the sounds that are hard for Romanian speakers.',
                    )
                }}
            </p>
            <ChipTag v-if="stats.best.pairs" kind="ok">
                {{ $t('Best: :n/8', { n: stats.best.pairs }) }}
            </ChipTag>
        </Link>
        <Link class="card game" :href="speaking({ mode: 'sprint' }).url">
            <div class="game-icon">⚡</div>
            <h3>{{ $t('Voice sprint') }}</h3>
            <p class="muted">
                {{
                    $t(
                        '60 seconds: see the word in Romanian and say it in English, as many as you can.',
                    )
                }}
            </p>
            <ChipTag v-if="stats.best.sprint" kind="ok">
                {{ $t('Best: :n', { n: stats.best.sprint }) }}
            </ChipTag>
        </Link>
    </div>
    <div class="card">
        <h3>{{ $t('🎭 Role-play from the lessons') }}</h3>
        <p class="muted">
            {{
                $t(
                    'Play one character from a unit dialogue. The app says the other lines.',
                )
            }}
        </p>
        <div class="gram-list" :style="{ '--c': levelColor(chosen.id) }">
            <Link
                v-for="unit in chosen.units"
                :key="unit.id"
                class="gram-row"
                :href="speaking({ mode: 'role', arg: unit.id }).url"
            >
                <span class="gram-n">{{ unit.order }}</span>
                <span class="grow">
                    <strong>{{ unit.emoji }} {{ unit.title }}</strong>
                    <small class="muted">{{ unit.dialogue }}</small>
                </span>
                <ChipTag v-if="unit.score !== null" kind="ok">
                    {{ Math.round(unit.score * 100) }}%
                </ChipTag>
            </Link>
        </div>
    </div>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { home, speaking } from '@/routes';
import { levelInfo } from '@/utils/levels';
import { canListen } from '@/utils/recognition';
import { pickLevel, rememberLevel, storedLevel } from '@/utils/speaking';

interface RoleUnit {
    id: string;
    order: number;
    emoji: string;
    title: string;
    /** The Romanian title of its dialogue. */
    dialogue: string;
    /** Best role-play score, 0..1, null when never played. */
    score: number | null;
}

interface HubLevel {
    id: string;
    unlocked: boolean;
    /** Conversations of the level, null without speaking content. */
    scenarios: number | null;
    /** Conversations of the level finished. */
    talked: number;
    /** Its open units (empty for a locked level). */
    units: RoleUnit[];
}

interface HubStats {
    said: number;
    talks: number;
    best: { pairs?: number; sprint?: number };
}

/** Speaking hub: totals, the level picker, the practice modes and the role-plays of the open units. */
export default defineComponent({
    name: 'SpeakingIndex',

    components: { ChipTag, Head, Link, PageHeader },

    props: {
        levels: { type: Array as PropType<HubLevel[]>, required: true },
        /** The level being worked on: shown unless this device chose another open level. */
        current: { type: String, required: true },
        stats: { type: Object as PropType<HubStats>, required: true },
    },

    data() {
        return {
            recognition: canListen(),
            stored: storedLevel(),
        };
    },

    computed: {
        chosen(): HubLevel {
            const open = this.levels.filter((l) => l.unlocked).map((l) => l.id);
            const id = pickLevel(this.stored, open, this.current);

            return (
                this.levels.find((l) => l.id === id) ?? {
                    id,
                    unlocked: true,
                    scenarios: null,
                    talked: 0,
                    units: [],
                }
            );
        },
    },

    methods: {
        home,
        speaking,

        choose(id: string): void {
            rememberLevel(id);
            this.stored = id;
        },

        /** A practice mode in the chosen level. */
        modeUrl(mode: string): string {
            return speaking({ mode }, { query: { level: this.chosen.id } }).url;
        },

        levelName(id: string): string {
            return levelInfo(id)?.name ?? id.toUpperCase();
        },

        levelColor(id: string): string {
            return levelInfo(id)?.color ?? 'var(--primary)';
        },
    },
});
</script>
