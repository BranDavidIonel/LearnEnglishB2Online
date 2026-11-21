// @vitest-environment happy-dom
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    canListen,
    isMicError,
    listen,
    listenStream,
    micError,
    speechEngine,
} from './recognition';

/** A stand-in for Chrome's SpeechRecognition that the tests drive by hand. */
class FakeRecognition {
    static instances: FakeRecognition[] = [];
    static available = vi.fn(async () => 'available');
    static install = vi.fn(async () => true);

    lang = '';
    interimResults = false;
    maxAlternatives = 1;
    continuous = false;
    processLocally?: boolean;
    onresult: ((event: { results: unknown }) => void) | null = null;
    onerror: ((event: { error: string }) => void) | null = null;
    onend: (() => void) | null = null;
    started = false;
    stop = vi.fn(() => this.onend?.());
    abort = vi.fn(() => this.onend?.());

    constructor() {
        FakeRecognition.instances.push(this);
    }

    start(): void {
        this.started = true;
    }

    say(alternatives: string[], isFinal = true): void {
        const result = Object.assign(
            alternatives.map((transcript) => ({ transcript })),
            { isFinal },
        );
        this.onresult?.({ results: [result] });
    }

    fail(error: string): void {
        this.onerror?.({ error });
        this.onend?.();
    }
}

const last = (): FakeRecognition =>
    FakeRecognition.instances[FakeRecognition.instances.length - 1];

beforeEach(() => {
    FakeRecognition.instances = [];
    localStorage.clear();
    vi.stubGlobal('webkitSpeechRecognition', FakeRecognition);
    // No microphone of our own: listen() goes straight to the recogniser.
    vi.stubGlobal('navigator', { mediaDevices: undefined });
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('listen', () => {
    it('resolves with the alternatives of the final result', async () => {
        const states: string[] = [];
        const session = listen((...update) => states.push(update[0]));
        await vi.waitFor(() => expect(last()?.started).toBe(true));

        expect(last().lang).toBe('en-US');
        expect(last().maxAlternatives).toBe(3);
        last().say(['I am fine', 'I am find'], false);
        last().say(['I am fine', 'I am find']);
        last().onend?.();

        await expect(session.done).resolves.toEqual(['I am fine', 'I am find']);
        expect(states).toEqual(['ready', 'interim']);
    });

    it('keeps what was heard when only interim results came', async () => {
        const session = listen();
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().say(['good morning'], false);
        session.stop();

        await expect(session.done).resolves.toEqual(['good morning']);
    });

    it('explains that nothing was understood', async () => {
        const session = listen();
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().fail('no-speech');

        const error: unknown = await session.done.catch((e: unknown) => e);
        expect(isMicError(error) && error.code).toBe('no-speech');
        expect(isMicError(error) && error.text).toContain(
            'no English words were heard',
        );
    });

    it('switches to on-device recognition when the online service is unreachable', async () => {
        const states: string[] = [];
        const session = listen((...update) => states.push(update[0]));
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().fail('network');
        await vi.waitFor(() =>
            expect(FakeRecognition.instances).toHaveLength(2),
        );

        expect(last().processLocally).toBe(true);
        expect(states).toContain('retry');
        expect(speechEngine()).toBe('local');
        last().say(['hello']);
        last().onend?.();
        await expect(session.done).resolves.toEqual(['hello']);
    });

    it('gives up on "network" when on-device recognition is unavailable', async () => {
        FakeRecognition.available.mockResolvedValueOnce('unavailable');
        const session = listen();
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().fail('network');

        await expect(session.done).rejects.toMatchObject({ code: 'network' });
        expect(speechEngine()).toBe('cloud');
    });

    it('cancels and frees the microphone when stopped while it opens', async () => {
        const track = { label: 'USB mic', stop: vi.fn() };
        const stream = {
            getAudioTracks: () => [track],
            getTracks: () => [track],
        };
        let open: (value: typeof stream) => void = () => {};
        vi.stubGlobal('navigator', {
            mediaDevices: {
                getUserMedia: () => new Promise((resolve) => (open = resolve)),
            },
        });
        const session = listen();
        session.stop();
        open(stream);

        await expect(session.done).rejects.toMatchObject({ code: 'aborted' });
        expect(FakeRecognition.instances).toHaveLength(0);
        await vi.waitFor(() => expect(track.stop).toHaveBeenCalled());
    });

    it('explains a blocked microphone', async () => {
        vi.stubGlobal('navigator', {
            mediaDevices: {
                getUserMedia: () =>
                    Promise.reject(
                        Object.assign(new Error('denied'), {
                            name: 'NotAllowedError',
                        }),
                    ),
            },
        });

        await expect(listen().done).rejects.toMatchObject({
            code: 'not-allowed',
        });
    });
});

describe('listenStream', () => {
    it('passes on what is heard and starts again after each pause until stopped', async () => {
        vi.useFakeTimers();
        const heard: string[] = [];
        const stream = listenStream((text) => heard.push(text));
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().say(['apple']);
        last().onend?.();
        await vi.advanceTimersByTimeAsync(120);

        expect(heard).toEqual(['apple']);
        expect(FakeRecognition.instances).toHaveLength(2);

        stream.stop();
        await vi.advanceTimersByTimeAsync(1000);
        expect(FakeRecognition.instances).toHaveLength(2);
    });

    it('reports an error it cannot recover from', async () => {
        const errors: string[] = [];
        listenStream(
            () => {},
            (...update) => {
                if (update[0] === 'error') {
                    errors.push(update[1].code);
                }
            },
        );
        await vi.waitFor(() => expect(last()?.started).toBe(true));
        last().fail('not-allowed');
        await vi.waitFor(() => expect(errors).toEqual(['not-allowed']));
    });
});

describe('errors', () => {
    it('names the page address in the microphone help', () => {
        expect(micError('not-allowed').text).toContain(window.location.host);
    });

    it('falls back to the code for unknown errors', () => {
        expect(micError('bad-grammar').text).toBe(
            'Speech recognition returned an error (bad-grammar).',
        );
    });

    it('knows when the browser can listen', () => {
        expect(canListen()).toBe(true);
    });
});
