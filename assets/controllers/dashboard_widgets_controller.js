import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { preferences: String, endpoint: String, csrf: String };

    connect() {
        let savedPreferences = {};
        try {
            const parsed = JSON.parse(this.preferencesValue || '{}');
            savedPreferences = parsed && !Array.isArray(parsed) ? parsed : {};
        } catch {
            savedPreferences = {};
        }
        this.preferences = { order: [], hidden: [], collapsed: [], ...savedPreferences };
        this.customizing = false;
        this.widgets = Array.from(this.element.querySelectorAll('[data-dashboard-widget]'));
        this.applyOrder();
        this.widgets.forEach((widget) => this.decorate(widget));
        this.renderHiddenPanel();
    }

    toggleCustomize(event) {
        this.customizing = !this.customizing;
        event.currentTarget.textContent = this.customizing ? 'Terminer' : 'Personnaliser';
        this.widgets.forEach((widget) => widget.querySelector('[data-widget-controls]')?.classList.toggle('hidden', !this.customizing));
    }

    decorate(widget) {
        const id = widget.dataset.dashboardWidget;
        widget.draggable = true;
        widget.classList.toggle('hidden', this.preferences.hidden.includes(id));
        const content = widget.querySelector('[data-widget-content]') || widget;
        if (this.preferences.collapsed.includes(id)) content.classList.add('dashboard-widget-collapsed');
        const controls = document.createElement('div');
        controls.dataset.widgetControls = '';
        controls.className = 'hidden mb-2 flex flex-wrap justify-end gap-1 rounded-md border border-dashed border-slate-300 bg-white p-2 text-xs';
        controls.innerHTML = '<button type="button" data-action="dashboard-widgets#up" class="rounded border px-2 py-1">↑ Monter</button><button type="button" data-action="dashboard-widgets#down" class="rounded border px-2 py-1">↓ Descendre</button><button type="button" data-action="dashboard-widgets#collapse" class="rounded border px-2 py-1">Réduire</button><button type="button" data-action="dashboard-widgets#hide" class="rounded border border-rose-200 px-2 py-1 text-rose-700">Masquer</button>';
        widget.prepend(controls);
        widget.addEventListener('dragstart', () => { if (this.customizing) this.dragged = widget; });
        widget.addEventListener('dragover', (event) => { if (this.customizing) event.preventDefault(); });
        widget.addEventListener('drop', (event) => { if (!this.customizing || !this.dragged || this.dragged === widget) return; event.preventDefault(); widget.before(this.dragged); this.persist(); });
    }

    up(event) { const widget = event.currentTarget.closest('[data-dashboard-widget]'); const previous = widget.previousElementSibling?.closest('[data-dashboard-widget]'); if (previous) previous.before(widget); this.persist(); }
    down(event) { const widget = event.currentTarget.closest('[data-dashboard-widget]'); const next = widget.nextElementSibling?.closest('[data-dashboard-widget]'); if (next) next.after(widget); this.persist(); }
    collapse(event) { const widget = event.currentTarget.closest('[data-dashboard-widget]'); const id = widget.dataset.dashboardWidget; const content = widget.querySelector('[data-widget-content]') || widget; content.classList.toggle('dashboard-widget-collapsed'); this.preferences.collapsed = this.toggle(this.preferences.collapsed, id, content.classList.contains('dashboard-widget-collapsed')); this.persist(); }
    hide(event) { const widget = event.currentTarget.closest('[data-dashboard-widget]'); const id = widget.dataset.dashboardWidget; widget.classList.add('hidden'); this.preferences.hidden = this.toggle(this.preferences.hidden, id, true); this.renderHiddenPanel(); this.persist(); }
    show(event) { const id = event.currentTarget.dataset.widgetId; this.widgets.find((widget) => widget.dataset.dashboardWidget === id)?.classList.remove('hidden'); this.preferences.hidden = this.toggle(this.preferences.hidden, id, false); this.renderHiddenPanel(); this.persist(); }
    renderHiddenPanel() { const panel = this.element.querySelector('[data-hidden-widgets]'); if (!panel) return; panel.innerHTML = this.preferences.hidden.map((id) => `<button type="button" data-action="dashboard-widgets#show" data-widget-id="${id}" class="rounded border border-slate-300 px-2 py-1 text-xs">Réafficher ${id}</button>`).join(''); }
    applyOrder() { const map = new Map(this.widgets.map((widget) => [widget.dataset.dashboardWidget, widget])); this.preferences.order.forEach((id) => { if (map.has(id)) this.element.appendChild(map.get(id)); }); }
    toggle(values, id, active) { return active ? Array.from(new Set([...values, id])) : values.filter((value) => value !== id); }
    persist() { this.preferences.order = Array.from(this.element.querySelectorAll('[data-dashboard-widget]')).map((widget) => widget.dataset.dashboardWidget); fetch(this.endpointValue, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfValue }, body: JSON.stringify(this.preferences) }); }
}
