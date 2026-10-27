import { Controller } from '@hotwired/stimulus';

/*
 * Emoji popup on the registration form. The popover and the radio buttons work natively;
 * this only shows the chosen emoji on the toggle button and closes the popup after a choice.
 */
export default class extends Controller {
    static targets = ['preview', 'popover'];

    select(event) {
        if (event.target.type !== 'radio') {
            return;
        }

        this.previewTarget.textContent = event.target.value;
        if (this.popoverTarget.matches(':popover-open')) {
            this.popoverTarget.hidePopover();
            this.element.querySelector('[popovertarget]')?.focus();
        }
    }
}
