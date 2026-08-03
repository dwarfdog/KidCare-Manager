import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['rows', 'template'];

    connect() { this.reindex(); }
    add() { const fragment = this.templateTarget.content.cloneNode(true); this.rowsTarget.appendChild(fragment); this.reindex(); }
    remove(event) { event.currentTarget.closest('[data-split-row]')?.remove(); this.reindex(); }
    reindex() {
        this.rowsTarget.querySelectorAll('[data-split-row]').forEach((row, index) => {
            row.querySelector('[data-split-legend]')?.replaceChildren(document.createTextNode(`Ligne de ventilation ${index + 1}`));
            row.querySelectorAll('[name], [data-name]').forEach((input) => {
                const field = input.dataset.name || (input.name.match(/\[([^\]]+)\]$/)?.[1]);
                if (field) input.name = `lines[${index}][${field}]`;
            });
        });
    }
}
