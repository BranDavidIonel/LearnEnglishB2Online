import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import type { Learner } from '@/types';
import { HttpError, RETRY_DELAYS, send } from './http';
import { learnerState } from './learner';
import { toastState } from './toast';

const learner: Learner = {
    xp: 120,
    xpToday: 30,
    goal: 50,
    streak: 4,
    level: { id: 'b1', name: 'B1', color: '#a78bfa' },
    due: { words: 7, verbs: 2, mistakes: 1 },
};

function answer(status: number, body: unknown): Response {
    return new Response(body === null ? null : JSON.stringify(body), {
        status,
    });
}

const fetchMock = vi.fn<typeof fetch>();
const reload = vi.fn();
const assign = vi.fn();

beforeEach(() => {
    fetchMock.mockReset();
    reload.mockReset();
    assign.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal('window', { location: { reload, assign } });
    toastState.toasts = [];
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('send', () => {
    it('sends JSON with the method of the route and returns the answer', async () => {
        fetchMock.mockResolvedValue(answer(200, { ok: true }));

        const result = await send<{ ok: boolean }>(
            { url: '/api/words/grade', method: 'post' },
            { word: 'customer', grade: 2 },
        );

        expect(result).toEqual({ ok: true });
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/api/words/grade');
        expect(init?.method).toBe('POST');
        expect(init?.body).toBe('{"word":"customer","grade":2}');
        expect(init?.headers).toMatchObject({
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        });
    });

    it('uses POST for a plain URL and the given method when there is one', async () => {
        fetchMock.mockImplementation(async () => answer(204, null));

        await send('/api/a', { a: 1 });
        await send('/api/b', { b: 1 }, 'put');

        expect(fetchMock.mock.calls[0][1]?.method).toBe('POST');
        expect(fetchMock.mock.calls[1][1]?.method).toBe('PUT');
    });

    it('puts the learner and the settings of the answer into the shared state', async () => {
        fetchMock.mockResolvedValue(
            answer(200, {
                learner,
                settings: {
                    theme: 'sepia',
                    rate: 1,
                    voice: null,
                    sound: false,
                },
            }),
        );

        await send('/api/xp', { amount: 10 });

        expect(learnerState.learner).toEqual(learner);
        expect(learnerState.settings.theme).toBe('sepia');
    });

    it('sends the requests one after another, in the order they were made', async () => {
        const order: string[] = [];
        let finishFirst: (value: Response) => void = () => undefined;

        fetchMock
            .mockImplementationOnce(
                () =>
                    new Promise<Response>((resolve) => {
                        finishFirst = resolve;
                    }),
            )
            .mockResolvedValueOnce(answer(200, { n: 2 }));

        const first = send('/api/1', {}).then(() => order.push('first'));
        const second = send('/api/2', {}).then(() => order.push('second'));

        await Promise.resolve();
        expect(fetchMock).toHaveBeenCalledTimes(1);

        finishFirst(answer(200, { n: 1 }));
        await Promise.all([first, second]);

        expect(order).toEqual(['first', 'second']);
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('rejects a validation error with its errors and keeps the queue going', async () => {
        fetchMock
            .mockResolvedValueOnce(
                answer(422, {
                    message: 'The grade field is required.',
                    errors: { grade: ['The grade field is required.'] },
                }),
            )
            .mockResolvedValueOnce(answer(200, { ok: true }));

        const error = await send('/api/words/grade', {}).catch(
            (caught: unknown) => caught,
        );

        expect(error).toBeInstanceOf(HttpError);
        expect((error as HttpError).status).toBe(422);
        expect((error as HttpError).errors).toEqual({
            grade: ['The grade field is required.'],
        });
        await expect(send('/api/next', {})).resolves.toEqual({ ok: true });
    });

    it('tells the learner about a server error, but leaves a validation error to the caller', async () => {
        fetchMock
            .mockResolvedValueOnce(answer(500, { message: 'Server Error' }))
            .mockResolvedValueOnce(answer(422, { message: 'Invalid.' }));

        await expect(send('/api/a', {})).rejects.toMatchObject({ status: 500 });
        expect(toastState.toasts.map((item) => item.message)).toEqual([
            'Your progress could not be saved. Check the connection.',
        ]);

        await expect(send('/api/b', {})).rejects.toMatchObject({ status: 422 });
        expect(toastState.toasts).toHaveLength(1);
    });

    it('reloads the page when the session expired and goes to the login page on 401', async () => {
        fetchMock
            .mockResolvedValueOnce(answer(419, { message: 'Page Expired' }))
            .mockResolvedValueOnce(
                answer(401, { message: 'Unauthenticated.' }),
            );

        await expect(send('/api/a', {})).rejects.toBeInstanceOf(HttpError);
        await expect(send('/api/b', {})).rejects.toBeInstanceOf(HttpError);

        expect(reload).toHaveBeenCalledOnce();
        expect(assign).toHaveBeenCalledWith('/login');
        expect(toastState.toasts).toHaveLength(0);
    });

    it('retries a network failure and succeeds when the connection is back', async () => {
        vi.useFakeTimers();
        fetchMock
            .mockRejectedValueOnce(new TypeError('Failed to fetch'))
            .mockResolvedValueOnce(answer(200, { ok: true }));

        const result = send('/api/a', {});
        await vi.advanceTimersByTimeAsync(RETRY_DELAYS[0]);

        await expect(result).resolves.toEqual({ ok: true });
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(toastState.toasts).toHaveLength(0);
    });

    it('gives up after the last retry with a toast', async () => {
        vi.useFakeTimers();
        fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));

        const result = send('/api/a', {}).catch((caught: unknown) => caught);
        await vi.advanceTimersByTimeAsync(
            RETRY_DELAYS.reduce((total, delay) => total + delay, 0),
        );

        expect(await result).toBeInstanceOf(TypeError);
        expect(fetchMock).toHaveBeenCalledTimes(RETRY_DELAYS.length + 1);
        expect(toastState.toasts.map((item) => item.message)).toEqual([
            'Your progress could not be saved. Check the connection.',
        ]);
    });
});
