import { Controller } from '@hotwired/stimulus';

/*
 * "Andere naam" button on the registration form: fills in the next fun name and its animal
 * emoji from a list the server suggested (App\Player\FunNameGenerator), skipping the current name.
 */
export default class extends Controller {
    static targets = ['input', 'button', 'status'];

    static values = {
        // [{ name, emoji }, …]
        suggestions: Array,
        // Screen-reader announcement, e.g. "Nieuwe naam: %name%".
        announce: String,
    };

    connect() {
        const names = this.suggestionsValue.map((suggestion) => suggestion.name);
        this.index = Math.max(0, names.indexOf(this.inputTarget.value));
        this.buttonTarget.hidden = names.length < 2;
    }

    next() {
        const suggestions = this.suggestionsValue;
        let suggestion;
        do {
            this.index = (this.index + 1) % suggestions.length;
            suggestion = suggestions[this.index];
        } while (suggestion.name === this.inputTarget.value && suggestions.length > 1);

        this.inputTarget.value = suggestion.name;
        this.chooseEmoji(suggestion.emoji);
        this.statusTarget.textContent = this.announceValue.replace(
            '%name%',
            `${suggestion.name} ${suggestion.emoji}`,
        );
    }

    chooseEmoji(emoji) {
        const radio = [...this.element.querySelectorAll('input[type=radio]')].find(
            (input) => input.value === emoji,
        );
        if (radio) {
            radio.checked = true;
            // Lets the emoji picker update its button.
            radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
}
