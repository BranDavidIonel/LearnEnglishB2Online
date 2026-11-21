import { describe, expect, it } from 'vite-plus/test';
import { saveQueue } from './saving';

describe('saveQueue', () => {
    it('sends the requests it starts with, in order', async () => {
        const sent: string[] = [];
        const queue = saveQueue([
            async () => sent.push('mistakes'),
            async () => sent.push('test'),
        ]);

        expect(await queue.flush()).toBe(true);
        expect(sent).toEqual(['mistakes', 'test']);
    });

    it('sends the requests added later in order, as they come', async () => {
        const sent: number[] = [];
        const queue = saveQueue();

        queue.add(async () => sent.push(1));
        queue.add(async () => sent.push(2));

        expect(await queue.flush()).toBe(true);
        expect(sent).toEqual([1, 2]);
    });

    it('stops at a failed request and sends only it and the rest on the next try', async () => {
        const sent: string[] = [];
        let online = false;
        const queue = saveQueue([
            async () => sent.push('mistakes'),
            async () => {
                if (!online) {
                    throw new Error('offline');
                }

                sent.push('test');
            },
            async () => sent.push('xp'),
        ]);

        expect(await queue.flush()).toBe(false);
        expect(sent).toEqual(['mistakes']);

        expect(await queue.flush()).toBe(false);
        expect(sent).toEqual(['mistakes']);

        online = true;
        expect(await queue.flush()).toBe(true);
        expect(sent).toEqual(['mistakes', 'test', 'xp']);

        // Everything is saved: nothing is sent twice.
        expect(await queue.flush()).toBe(true);
        expect(sent).toEqual(['mistakes', 'test', 'xp']);
    });

    it('keeps what is added after a failure until the next flush', async () => {
        const sent: string[] = [];
        let online = false;
        const queue = saveQueue();

        queue.add(async () => {
            if (!online) {
                throw new Error('offline');
            }

            sent.push('first');
        });
        expect(await queue.flush()).toBe(false);

        online = true;
        queue.add(async () => sent.push('second'));
        await Promise.resolve();
        expect(sent).toEqual([]);

        expect(await queue.flush()).toBe(true);
        expect(sent).toEqual(['first', 'second']);
    });

    it('waits for a request on its way instead of sending it twice', async () => {
        let calls = 0;
        let answer: () => void = () => undefined;
        const queue = saveQueue([
            () =>
                new Promise<void>((resolve) => {
                    calls++;
                    answer = resolve;
                }),
        ]);

        const saved = queue.flush();
        answer();

        expect(await saved).toBe(true);
        expect(calls).toBe(1);
    });

    it('is done at once without requests', async () => {
        expect(await saveQueue().flush()).toBe(true);
    });
});
