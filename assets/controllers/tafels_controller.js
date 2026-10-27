import { Controller } from '@hotwired/stimulus';
import {
    appendDigit,
    checkAnswer,
    createRound,
    findTask,
    format,
    formatTime,
    isPassed,
    isRoundComplete,
    isTimeUp,
    READY_COUNTDOWN_SECONDS,
    remainingTasks,
    removeLastDigit,
    roundDuration,
    solveTask,
    startTimer,
    timeLeft,
} from '../games/rekenen/tafels.js';

const BUBBLE_HUES = [200, 280, 330, 30, 150, 50];
// Fallback for when no animationend fires (e.g. prefers-reduced-motion).
const POP_FALLBACK_MS = 600;
const CLOCK_TICK_MS = 200;
// How long "Start!" stays visible before the ready dialog closes.
const GO_VISIBLE_MS = 800;
// Delay between bubbles popping up one after another.
const APPEAR_STAGGER_MS = 40;

/*
 * Tafels bubbles: one bubble per sum; click a bubble, type the answer, pop it.
 * Each round opens with a "Ben je er klaar voor?" dialog and a 3-2-1 countdown; then the
 * one-minute clock starts. When it runs out the pupil can still finish.
 * DOM wiring only — the game rules live in assets/games/rekenen/tafels.js.
 *
 * Translated texts arrive as values with %placeholders%, filled in with format().
 */
export default class extends Controller {
    static targets = [
        'field',
        'bubbleTemplate',
        'keypad',
        'status',
        'progress',
        'complete',
        'restart',
        'timer',
        'time',
        'passedMessage',
        'lateMessage',
        'readyDialog',
        'readyTime',
        'readyButton',
        'readyBack',
        'countdown',
        'savedMessage',
        'saveFailedMessage',
    ];

    static values = {
        table: Number,
        timeLimit: Number,
        // Empty for guests: only logged-in players get their results saved.
        saveUrl: String,
        sumLabel: String,
        correct: String,
        tryAgain: String,
        remaining: String,
        timeUp: String,
        go: String,
    };

    connect() {
        this.timers = new Set();
        this.start();
    }

    disconnect() {
        this.stopClock();
        this.timers.forEach((timer) => clearTimeout(timer));
        this.timers.clear();
    }

    start() {
        this.stopClock();
        this.round = createRound(this.tableValue, Math.random, this.timeLimitValue);
        this.activeBubble = null;
        this.timerTarget.classList.remove('round-timer--expired');
        this.renderClock();
        this.showReady();

        // Bubbles only appear once the ready countdown is over (see revealBubbles).
        this.fieldTarget.replaceChildren();
        this.keypadTarget.hidden = true;
        this.completeTarget.hidden = true;
        this.savedMessageTargets.forEach((message) => (message.hidden = true));
        this.saveFailedMessageTargets.forEach((message) => (message.hidden = true));
        this.statusTarget.textContent = '';
        this.updateProgress();
    }

    restart() {
        this.start();
    }

    showReady() {
        this.readyDone = false;
        this.readyTimeTarget.textContent = formatTime(this.round.timeLimitMs);
        this.readyButtonTarget.hidden = false;
        this.readyBackTarget.hidden = false;
        this.countdownTarget.textContent = '';
        if (!this.readyDialogTarget.open) {
            this.readyDialogTarget.showModal();
        }
        this.readyButtonTarget.focus();
    }

    /** "Ik ben er klaar voor!" → 3, 2, 1, Start! → close the dialog and start the clock. */
    ready() {
        this.readyButtonTarget.hidden = true;
        this.readyBackTarget.hidden = true;

        for (let step = 0; step < READY_COUNTDOWN_SECONDS; step++) {
            this.schedule(step * 1000, () =>
                this.showCountdown(String(READY_COUNTDOWN_SECONDS - step)),
            );
        }
        this.schedule(READY_COUNTDOWN_SECONDS * 1000, () => this.showCountdown(this.goValue));
        this.schedule(READY_COUNTDOWN_SECONDS * 1000 + GO_VISIBLE_MS, () => {
            this.readyDone = true;
            this.readyDialogTarget.close();
            this.revealBubbles();
            this.startClock();
            this.fieldTarget.querySelector('.bubble__sum')?.focus();
        });
    }

    showCountdown(text) {
        const countdown = this.countdownTarget;
        countdown.textContent = text;
        countdown.classList.remove('ready-dialog__countdown--tick');
        // Force a reflow so the pop-in animation restarts for every step.
        void countdown.offsetWidth;
        countdown.classList.add('ready-dialog__countdown--tick');
    }

    /** The countdown is the only way out of the ready dialog (no Escape). */
    preventCancel(event) {
        event.preventDefault();
    }

    /**
     * Browsers may still close the dialog (e.g. Escape before any user interaction, where
     * `cancel` is not cancelable and `closedby` is unsupported): reopen it.
     */
    readyClosed() {
        if (this.readyDone) {
            return;
        }

        this.readyDialogTarget.showModal();
        if (!this.readyButtonTarget.hidden) {
            this.readyButtonTarget.focus();
        }
    }

    schedule(delay, callback) {
        const timer = setTimeout(() => {
            this.timers.delete(timer);
            callback();
        }, delay);
        this.timers.add(timer);
    }

    revealBubbles() {
        this.fieldTarget.replaceChildren(
            ...this.round.tasks.map((task, index) => this.createBubble(task, index)),
        );
    }

    /**
     * @param {import('../games/rekenen/tafels.js').Task} task
     * @param {number} index position in the field, for the staggered pop-up
     */
    createBubble(task, index) {
        const bubble = this.bubbleTemplateTarget.content.firstElementChild.cloneNode(true);
        const params = { multiplier: task.multiplier, table: task.table };

        bubble.dataset.taskId = String(task.id);
        bubble.classList.add('bubble--appearing');
        bubble.style.setProperty('--appear-delay', `${index * APPEAR_STAGGER_MS}ms`);
        bubble.style.setProperty('--bubble-hue', String(BUBBLE_HUES[task.id % BUBBLE_HUES.length]));
        bubble.style.setProperty('--bubble-delay', `${(-Math.random() * 3).toFixed(2)}s`);
        bubble.querySelector('.bubble__question').textContent =
            `${task.multiplier} × ${task.table} =`;
        bubble
            .querySelector('.bubble__sum')
            .setAttribute('aria-label', format(this.sumLabelValue, params));

        bubble.addEventListener('animationend', (event) => this.animationEnded(event, bubble));

        return bubble;
    }

    open(event) {
        this.activate(event.currentTarget.closest('.bubble'));
    }

    activate(bubble) {
        if (bubble === this.activeBubble || bubble.classList.contains('bubble--popping')) {
            this.answerInput()?.focus();
            return;
        }

        this.close();
        this.activeBubble = bubble;
        bubble.classList.add('bubble--active');
        bubble.querySelector('.bubble__sum').setAttribute('aria-expanded', 'true');

        const input = this.answerInput();
        input.value = '';
        input.hidden = false;
        this.keypadTarget.hidden = false;
        this.statusTarget.textContent = '';
        input.focus({ preventScroll: true });
        // scroll-margin on .bubble--active keeps the bubble clear of the sticky keypad dock.
        bubble.scrollIntoView({ block: 'nearest' });
    }

    close() {
        if (!this.activeBubble) {
            return;
        }

        this.activeBubble.classList.remove('bubble--active', 'bubble--wrong');
        this.activeBubble.querySelector('.bubble__sum').setAttribute('aria-expanded', 'false');
        this.answerInput().hidden = true;
        this.activeBubble = null;
        this.keypadTarget.hidden = true;
    }

    press({ params: { digit } }) {
        const input = this.answerInput();
        if (!input) {
            return;
        }

        input.value = appendDigit(input.value, String(digit));
        input.focus();
    }

    erase() {
        const input = this.answerInput();
        if (!input) {
            return;
        }

        input.value = removeLastDigit(input.value);
        input.focus();
    }

    /**
     * Typing a digit anywhere on the page answers a bubble: the open one, else the focused
     * bubble, else the first remaining bubble.
     */
    typeAnywhere(event) {
        const digit = event.key;
        if (
            !/^\d$/.test(digit) ||
            event.ctrlKey ||
            event.metaKey ||
            event.altKey ||
            this.readyDialogTarget.open ||
            event.target.classList?.contains('bubble__answer')
        ) {
            return;
        }

        const bubble = this.activeBubble ?? this.bubbleToActivate();
        if (!bubble) {
            return;
        }

        // Handle the digit ourselves: focus moves to the answer field during this keydown.
        event.preventDefault();
        this.activate(bubble);
        const input = this.answerInput();
        input.value = appendDigit(input.value, digit);
        input.focus();
    }

    bubbleToActivate() {
        const available = (bubble) => bubble && !bubble.classList.contains('bubble--popping');
        const focused = document.activeElement?.closest('.bubble');

        if (available(focused) && this.fieldTarget.contains(focused)) {
            return focused;
        }

        return [...this.fieldTarget.children].find(available) ?? null;
    }

    /** Keeps physical-keyboard input to digits only. */
    typed(event) {
        const input = event.currentTarget;
        input.value = [...input.value].reduce((value, char) => appendDigit(value, char), '');
    }

    keydown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            this.submit();
        } else if (event.key === 'Escape') {
            const button = this.activeBubble?.querySelector('.bubble__sum');
            this.close();
            button?.focus();
        }
    }

    submit() {
        const bubble = this.activeBubble;
        if (!bubble) {
            return;
        }

        const input = this.answerInput();
        const task = findTask(this.round, Number(bubble.dataset.taskId));
        if (!task || input.value === '') {
            input.focus();
            return;
        }

        if (!checkAnswer(task, input.value)) {
            this.statusTarget.textContent = this.tryAgainValue;
            bubble.classList.remove('bubble--wrong');
            // Force a reflow so the wiggle animation restarts on repeated wrong answers.
            void bubble.offsetWidth;
            bubble.classList.add('bubble--wrong');
            input.value = '';
            input.focus();
            return;
        }

        this.round = solveTask(this.round, task.id, Date.now());
        this.statusTarget.textContent = format(this.correctValue, {
            multiplier: task.multiplier,
            table: task.table,
            answer: task.answer,
        });

        this.close();
        bubble.classList.add('bubble--popping');
        bubble.querySelector('.bubble__sum').disabled = true;
        this.updateProgress();
        this.focusNext(bubble);

        const timer = setTimeout(() => this.removeBubble(bubble), POP_FALLBACK_MS);
        this.timers.add(timer);
        bubble.dataset.popTimer = String(timer);
    }

    animationEnded(event, bubble) {
        if (event.target !== bubble) {
            return;
        }

        if (event.animationName === 'bubble-pop') {
            this.removeBubble(bubble);
        } else if (event.animationName === 'bubble-wiggle') {
            bubble.classList.remove('bubble--wrong');
        } else if (event.animationName === 'bubble-appear') {
            bubble.classList.remove('bubble--appearing');
        }
    }

    removeBubble(bubble) {
        const timer = Number(bubble.dataset.popTimer);
        clearTimeout(timer);
        this.timers.delete(timer);
        bubble.remove();
    }

    focusNext(poppedBubble) {
        if (isRoundComplete(this.round)) {
            this.finish();
            return;
        }

        const next = [...this.fieldTarget.children].find(
            (bubble) => bubble !== poppedBubble && !bubble.classList.contains('bubble--popping'),
        );
        next?.querySelector('.bubble__sum').focus();
    }

    finish() {
        this.stopClock();
        this.renderClock();

        const passed = isPassed(this.round);
        this.passedMessageTarget.hidden = !passed;
        this.lateMessageTarget.hidden = passed;
        this.completeTarget.hidden = false;
        this.restartTarget.focus();
        this.saveResult();
    }

    async saveResult() {
        const durationMs = roundDuration(this.round);
        if (!this.saveUrlValue || durationMs === null) {
            return;
        }

        try {
            const response = await fetch(this.saveUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ durationMs }),
                credentials: 'same-origin',
                keepalive: true,
            });
            if (!response.ok) {
                throw new Error(`Saving the result failed: HTTP ${response.status}`);
            }
            this.savedMessageTargets.forEach((message) => (message.hidden = false));
        } catch {
            this.saveFailedMessageTargets.forEach((message) => (message.hidden = false));
        }
    }

    /** Starts the round's clock (once per round). */
    startClock() {
        if (this.round.startedAt !== null) {
            return;
        }

        this.round = startTimer(this.round, Date.now());
        this.clock = setInterval(() => this.tick(), CLOCK_TICK_MS);
    }

    stopClock() {
        clearInterval(this.clock);
        this.clock = null;
    }

    tick() {
        const now = Date.now();
        this.renderClock(now);

        if (isTimeUp(this.round, now)) {
            this.stopClock();
            this.timerTarget.classList.add('round-timer--expired');
            this.statusTarget.textContent = this.timeUpValue;
        }
    }

    renderClock(now = Date.now()) {
        const left = timeLeft(this.round, now);
        this.timeTarget.textContent = formatTime(left);
        this.timerTarget.style.setProperty('--time-left', String(left / this.round.timeLimitMs));
    }

    updateProgress() {
        const count = remainingTasks(this.round).length;
        this.progressTarget.textContent = count > 0 ? format(this.remainingValue, { count }) : '';
    }

    answerInput() {
        return this.activeBubble?.querySelector('.bubble__answer') ?? null;
    }
}
