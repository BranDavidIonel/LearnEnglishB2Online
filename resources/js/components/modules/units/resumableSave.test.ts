import { describe, expect, it } from 'vite-plus/test';
import { resumableSave } from './resumableSave';

describe('resumableSave', () => {
    it('sends every request in order', async () => {
        const sent: string[] = [];
        const save = resumableSave([
            async () => sent.push('mistakes'),
            async () => sent.push('test'),
        ]);

        expect(await save.run()).toBe(true);
        expect(sent).toEqual(['mistakes', 'test']);
    });

    it('stops at a failed request and sends only the rest on the next try', async () => {
        const sent: string[] = [];
        let online = false;
        const save = resumableSave([
            async () => sent.push('mistakes'),
            async () => {
                if (!online) {
                    throw new Error('offline');
                }

                sent.push('test');
            },
            async () => sent.push('xp'),
        ]);

        expect(await save.run()).toBe(false);
        expect(sent).toEqual(['mistakes']);

        expect(await save.run()).toBe(false);
        expect(sent).toEqual(['mistakes']);

        online = true;
        expect(await save.run()).toBe(true);
        expect(sent).toEqual(['mistakes', 'test', 'xp']);

        // Everything is saved: nothing is sent twice.
        expect(await save.run()).toBe(true);
        expect(sent).toEqual(['mistakes', 'test', 'xp']);
    });

    it('is done at once without requests', async () => {
        expect(await resumableSave([]).run()).toBe(true);
    });
});
