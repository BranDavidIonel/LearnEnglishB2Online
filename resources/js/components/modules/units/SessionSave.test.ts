// @vitest-environment happy-dom
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import { trans } from '@/i18n';
import type { Replacements } from '@/i18n';
import { resumableSave } from './resumableSave';
import SessionSave from './SessionSave.vue';

const global = {
    mocks: {
        $t: (key: string, replacements?: Replacements) =>
            trans(key, replacements, 'en'),
    },
};

function mountSave(requests: (() => Promise<unknown>)[]) {
    return mount(SessionSave, {
        props: { save: resumableSave(requests) },
        slots: { default: '<div class="result">Unit passed!</div>' },
        global,
    });
}

describe('SessionSave', () => {
    it('shows the result once everything is saved', async () => {
        const wrapper = mountSave([async () => 'ok']);
        expect(wrapper.find('.result').exists()).toBe(false);

        await flushPromises();

        expect(wrapper.find('.result').exists()).toBe(true);
        expect(wrapper.find('.alert').exists()).toBe(false);
        expect(wrapper.emitted('saved')).toHaveLength(1);
    });

    it('offers to send the result again when the network is down, without losing it', async () => {
        const sent: string[] = [];
        let online = false;
        const wrapper = mountSave([
            async () => sent.push('mistakes'),
            async () => {
                if (!online) {
                    throw new TypeError('Failed to fetch');
                }

                sent.push('test');
            },
        ]);
        await flushPromises();

        expect(wrapper.find('.result').exists()).toBe(false);
        expect(wrapper.find('.alert.warn').text()).toContain(
            'Your result could not be saved',
        );
        expect(wrapper.emitted('saved')).toBeUndefined();

        online = true;
        await wrapper.find('.alert .btn.primary').trigger('click');
        await flushPromises();

        expect(sent).toEqual(['mistakes', 'test']);
        expect(wrapper.find('.alert').exists()).toBe(false);
        expect(wrapper.find('.result').exists()).toBe(true);
        expect(wrapper.emitted('saved')).toHaveLength(1);
    });
});
