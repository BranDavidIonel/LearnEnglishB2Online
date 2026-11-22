// JSON requests to the progress API (routes/web/api.php). Every answer of that API carries the
// fresh learner summary (and the settings when they changed): it goes straight into the shared
// learner state, so the top bar and the menu badges update by themselves.
import { getLocale, trans } from '@/i18n';
import { login } from '@/routes';
import type { Learner, Settings } from '@/types';
import { setLearner, setSettings } from '@/utils/learner';
import { toast } from '@/utils/toast';

/** What the Wayfinder route helpers return: `send(gradeWord(), { … })`. */
export interface RouteDefinition {
    url: string;
    method: string;
}

/** A request the server answered with an error (422 carries the validation errors). */
export class HttpError extends Error {
    status: number;
    errors: Record<string, string[]>;

    constructor(
        status: number,
        message: string,
        errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'HttpError';
        this.status = status;
        this.errors = errors;
    }
}

/** Waits before each new try when the network is down (a laptop waking up, a short Wi-Fi drop). */
export const RETRY_DELAYS = [500, 1000, 2000];

// Progress writes must reach the server in the order they were made (a word graded twice,
// XP then the streak), so every request waits for the one before it.
let queue: Promise<unknown> = Promise.resolve();

/**
 * Sends `data` as JSON and resolves with the JSON answer.
 * The method is the route's (Wayfinder), else `method`, else POST.
 */
export function send<T = unknown>(
    route: RouteDefinition | string,
    data?: object,
    method?: string,
): Promise<T> {
    const url = typeof route === 'string' ? route : route.url;
    const verb = (
        method ?? (typeof route === 'string' ? 'post' : route.method)
    ).toUpperCase();

    const result = queue.then(() => request<T>(url, verb, data));
    queue = result.catch(() => undefined);

    return result;
}

/**
 * Reads JSON with a GET (the word window's lookup). A read is not a save: it goes out at once
 * instead of waiting behind the saves on their way, it is tried once, and a failure never shows
 * "could not be saved" — the caller shows its own state. An expired session is handled as for
 * a save. Nothing of the answer goes into the learner state.
 */
export async function read<T = unknown>(
    route: RouteDefinition | string,
): Promise<T> {
    const url = typeof route === 'string' ? route : route.url;
    const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: headers(),
    });
    const body = await readJson(response);

    if (!response.ok) {
        throw failure(response.status, body, false);
    }

    return body as T;
}

async function request<T>(
    url: string,
    method: string,
    data?: object,
): Promise<T> {
    const response = await fetchWithRetry(url, {
        method,
        credentials: 'same-origin',
        headers: headers(),
        body:
            data === undefined || method === 'GET'
                ? undefined
                : JSON.stringify(data),
    });

    const body = await readJson(response);

    if (!response.ok) {
        throw failure(response.status, body);
    }

    remember(body);

    return body as T;
}

async function fetchWithRetry(
    url: string,
    init: RequestInit,
): Promise<Response> {
    for (let attempt = 0; ; attempt++) {
        try {
            return await fetch(url, init);
        } catch (error) {
            if (attempt >= RETRY_DELAYS.length) {
                notSaved();

                throw error;
            }

            await wait(RETRY_DELAYS[attempt]);
        }
    }
}

function headers(): Record<string, string> {
    const list: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = xsrfToken();

    if (token) {
        list['X-XSRF-TOKEN'] = token;
    }

    return list;
}

/** Laravel puts the CSRF token in the XSRF-TOKEN cookie (URL-encoded) on every response. */
function xsrfToken(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const cookie = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie
        ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
        : null;
}

async function readJson(response: Response): Promise<unknown> {
    const text = await response.text();

    if (text === '') {
        return null;
    }

    try {
        return JSON.parse(text) as unknown;
    } catch {
        return null;
    }
}

/** The error of an answer; `saving` tells the learner when a save did not reach the server. */
function failure(status: number, body: unknown, saving = true): HttpError {
    // The session expired (419: old CSRF token) or the login is gone (401):
    // a fresh page, or the login page, is the only way on.
    if (status === 419) {
        window.location.reload();
    } else if (status === 401) {
        window.location.assign(login.url());
    } else if (status !== 422 && saving) {
        // A server error is not retried: the learner is told, as after a network failure.
        // Validation errors (422) are the caller's to show.
        notSaved();
    }

    const answer = isObject(body) ? body : {};
    const message =
        typeof answer.message === 'string'
            ? answer.message
            : `Request failed (${status})`;
    const errors = isObject(answer.errors)
        ? (answer.errors as Record<string, string[]>)
        : {};

    return new HttpError(status, message, errors);
}

/** The one message for a write that did not reach the database. */
function notSaved(): void {
    toast(
        trans(
            'Your progress could not be saved. Check the connection.',
            {},
            getLocale(),
        ),
        'warn',
    );
}

/** Puts the learner summary and the settings of an answer into the shared learner state. */
function remember(body: unknown): void {
    if (!isObject(body)) {
        return;
    }

    if (isObject(body.learner)) {
        setLearner(body.learner as unknown as Learner);
    }

    if (isObject(body.settings)) {
        setSettings(body.settings as unknown as Settings);
    }
}

function isObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function wait(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
