import { Controller } from '@hotwired/stimulus';

// Clone du split_form_controller.js (App\Controller\CourseController::receiptReview) : ajout/retrait
// de lignes de ticket via template clone + reindexation des attributs "name" en lines[N][champ].
export default class extends Controller {
    static targets = ['rows', 'template'];

    connect() { this.reindex(); }
    add() { const fragment = this.templateTarget.content.cloneNode(true); this.rowsTarget.appendChild(fragment); this.reindex(); }
    remove(event) {
        event.currentTarget.closest('[data-review-row]')?.remove();
        this.reindex();
    }
    reindex() {
        this.rowsTarget.querySelectorAll('[data-review-row]').forEach((row, index) => {
            row.querySelector('[data-review-legend]')?.replaceChildren(document.createTextNode(`Ligne ${index + 1}`));
            row.querySelectorAll('[name], [data-name]').forEach((input) => {
                const field = input.dataset.name || (input.name.match(/\[([^\]]+)\]$/)?.[1]);
                if (field) input.name = `lines[${index}][${field}]`;
            });
        });
    }
}
