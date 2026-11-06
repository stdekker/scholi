/*
 * Confetti of a popped bubble. A DOM helper of the bubble games' controller (not a Stimulus
 * controller itself: the file name has no _controller suffix).
 */

/** Number of pieces in a burst. */
const CONFETTI_PIECES = 32;
/** How long a burst stays in the DOM: the length of its animation. */
export const CONFETTI_MS = 700;

/**
 * Builds the confetti of a correctly answered bubble: pieces flying out in the bubble's own
 * colour, positioned on the bubble. Decoration only (aria-hidden). The caller adds it to the page
 * and removes it after CONFETTI_MS.
 *
 * @param {HTMLElement} bubble
 * @returns {HTMLElement | null} null under prefers-reduced-motion
 */
export function createConfetti(bubble) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return null;
    }

    const { left, top, width, height } = bubble.getBoundingClientRect();
    const confetti = document.createElement('div');
    confetti.className = 'confetti';
    confetti.setAttribute('aria-hidden', 'true');
    confetti.style.left = `${left + width / 2}px`;
    confetti.style.top = `${top + height / 2}px`;
    confetti.style.setProperty('--bubble-hue', bubble.style.getPropertyValue('--bubble-hue'));

    for (let i = 0; i < CONFETTI_PIECES; i++) {
        const piece = document.createElement('span');
        const angle = ((i + Math.random() * 0.5) / CONFETTI_PIECES) * 2 * Math.PI;
        const distance = width * (0.8 + Math.random() * 0.9);
        piece.className = 'confetti__piece';
        piece.style.setProperty('--x', `${(Math.cos(angle) * distance).toFixed(1)}px`);
        piece.style.setProperty('--y', `${(Math.sin(angle) * distance).toFixed(1)}px`);
        piece.style.setProperty('--turn', `${Math.round(Math.random() * 720 - 360)}deg`);
        piece.style.setProperty('--lightness', `${45 + Math.round(Math.random() * 30)}%`);
        confetti.append(piece);
    }

    return confetti;
}
