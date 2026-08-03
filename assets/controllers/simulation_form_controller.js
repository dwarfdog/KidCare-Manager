import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['rows'];
    add() { const row = document.createElement('fieldset'); row.className = 'grid grid-cols-1 gap-2 rounded-md border border-slate-200 p-3 sm:grid-cols-[1fr_1fr_8rem_auto]'; row.dataset.simulationRow = ''; row.innerHTML = '<legend class="sr-only" data-row-legend>Hypothèse</legend><input type="date" data-field="date" aria-label="Date de l’hypothèse" class="rounded border border-slate-300 px-2 py-1"><input data-field="label" aria-label="Libellé de l’hypothèse" placeholder="Hypothèse" class="rounded border border-slate-300 px-2 py-1"><input type="number" step="0.01" data-field="amount" aria-label="Montant de l’hypothèse" placeholder="± montant" class="rounded border border-slate-300 px-2 py-1"><button type="button" aria-label="Retirer l’hypothèse" data-action="simulation-form#remove">×</button>'; this.rowsTarget.appendChild(row); this.reindex(); }
    remove(event) { event.currentTarget.closest('[data-simulation-row]')?.remove(); this.reindex(); }
    reindex() { this.rowsTarget.querySelectorAll('[data-simulation-row]').forEach((row, index) => { row.querySelector('[data-row-legend]')?.replaceChildren(document.createTextNode(`Hypothèse ${index + 1}`)); row.querySelectorAll('[data-field]').forEach((input) => { input.name = `assumptions[${index}][${input.dataset.field}]`; }); }); }
}
