// A burst of confetti over the whole page, to celebrate a passed test or a finished round.

const COLORS = [
    '#22c55e',
    '#38bdf8',
    '#a78bfa',
    '#fb923c',
    '#f472b6',
    '#facc15',
];
const PIECES = 160;

interface Piece {
    x: number;
    y: number;
    r: number;
    vx: number;
    vy: number;
    rot: number;
    vr: number;
    col: string;
}

function pieces(width: number, height: number): Piece[] {
    return Array.from({ length: PIECES }, () => ({
        x: Math.random() * width,
        y: -20 - Math.random() * height * 0.4,
        r: 4 + Math.random() * 6,
        vx: -2 + Math.random() * 4,
        vy: 2 + Math.random() * 5,
        rot: Math.random() * 6,
        vr: -0.2 + Math.random() * 0.4,
        col: COLORS[Math.floor(Math.random() * COLORS.length)],
    }));
}

/**
 * Throws confetti for `ms` milliseconds; the canvas removes itself afterwards. Returns a function
 * that ends it at once (nothing to call when the confetti may simply run out).
 */
export function confetti(ms = 2200): () => void {
    const canvas = document.createElement('canvas');
    canvas.style.cssText =
        'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:9999';
    document.body.append(canvas);
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const ctx = canvas.getContext('2d');
    const all = pieces(canvas.width, canvas.height);
    const t0 = performance.now();
    let request = 0;

    const end = (): void => {
        cancelAnimationFrame(request);
        canvas.remove();
    };

    const frame = (t: number): void => {
        if (!ctx) {
            return end();
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        for (const p of all) {
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.05;
            p.rot += p.vr;
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.rot);
            ctx.fillStyle = p.col;
            ctx.fillRect(-p.r / 2, -p.r / 2, p.r, p.r * 1.6);
            ctx.restore();
        }

        if (t - t0 < ms) {
            request = requestAnimationFrame(frame);
        } else {
            end();
        }
    };

    frame(t0);

    return end;
}
