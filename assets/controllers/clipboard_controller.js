import { Controller } from '@hotwired/stimulus';

/*
 * Copies the value of a readonly input (e.g. the player link) to the clipboard.
 * Falls back to selecting the text when the Clipboard API is unavailable or refused.
 */
export default class extends Controller {
    static targets = ['source', 'status'];

    static values = {
        copied: String,
        failed: String,
    };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.sourceTarget.value);
            this.statusTarget.textContent = this.copiedValue;
        } catch {
            this.select();
            this.statusTarget.textContent = this.failedValue;
        }
    }

    select() {
        this.sourceTarget.select();
    }
}
