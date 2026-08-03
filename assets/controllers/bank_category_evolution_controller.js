import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'canvas', 'customPeriod', 'error', 'empty', 'result', 'status', 'table'];
    static values = { endpoint: String, initial: Object, favoriteEndpoint: String, csrf: String };

    async connect() {
        this.abortController = null;
        this.timer = null;
        const { default: Chart } = await import('chart.js/auto');
        this.Chart = Chart;
        this.chart = null;
        this.toggleCustomPeriod();
        this.render(this.initialValue);
        this.currentData = this.initialValue;
    }

    disconnect() {
        this.abortController?.abort();
        window.clearTimeout(this.timer);
        this.chart?.destroy();
    }

    submit(event) {
        event.preventDefault();
        this.load();
    }

    change() {
        this.toggleCustomPeriod();
        window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => this.load(), 150);
    }

    toggleCustomPeriod() {
        const custom = this.formTarget.elements.period.value === 'custom';
        this.customPeriodTargets.forEach((target) => target.classList.toggle('hidden', !custom));
    }

    async load() {
        this.abortController?.abort();
        this.abortController = new AbortController();
        const params = new URLSearchParams(new FormData(this.formTarget));
        const pageUrl = new URL(window.location.href);
        pageUrl.search = params.toString();
        const endpoint = new URL(this.endpointValue, window.location.origin);
        endpoint.search = params.toString();
        this.element.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(endpoint, { signal: this.abortController.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            window.history.replaceState({}, '', pageUrl);
            this.errorTarget.textContent = payload.error || '';
            this.errorTarget.classList.toggle('hidden', !payload.error);
            this.render(payload.data);
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.errorTarget.textContent = 'Impossible de charger les statistiques pour le moment.';
                this.errorTarget.classList.remove('hidden');
            }
        } finally {
            this.element.removeAttribute('aria-busy');
        }
    }

    render(data) {
        this.currentData = data;
        const hasData = Boolean(data?.hasData);
        this.emptyTarget.classList.toggle('hidden', hasData || !this.errorTarget.classList.contains('hidden'));
        this.resultTarget.classList.toggle('hidden', !hasData);
        if (!hasData || !this.Chart) return;

        const euro = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });
        const chartData = {
            labels: data.labels,
            datasets: [
                { label: 'Gains', data: data.gains, borderColor: '#059669', backgroundColor: '#059669', tension: 0.25 },
                { label: 'Depenses', data: data.expenses, borderColor: '#e11d48', backgroundColor: '#e11d48', tension: 0.25 },
                { label: 'Solde', data: data.balance, borderColor: '#2563eb', backgroundColor: '#2563eb', tension: 0.25 },
            ],
        };
        if (this.chart) {
            this.chart.data = chartData;
            this.chart.update();
        } else {
            this.chart = new this.Chart(this.canvasTarget, {
                type: 'line', data: chartData,
                options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { tooltip: { callbacks: { label: (context) => `${context.dataset.label} : ${euro.format(context.parsed.y)}` } } }, scales: { y: { ticks: { callback: (value) => euro.format(value) } } } },
            });
        }

        this.tableTarget.replaceChildren(...data.rows.map((row) => {
            const tr = document.createElement('tr');
            [row.month, euro.format(row.gains), euro.format(row.expenses), euro.format(row.balance)].forEach((value, index) => {
                const td = document.createElement('td');
                td.className = `px-3 py-2 ${index > 0 ? 'text-right tabular-nums' : ''}`;
                td.textContent = value;
                tr.appendChild(td);
            });
            return tr;
        }));
        this.statusTarget.textContent = `${data.rows.length} mois affiches.`;
    }

    downloadPng() {
        const link = document.createElement('a'); link.download = 'evolution-categorie.png'; link.href = this.canvasTarget.toDataURL('image/png'); link.click();
    }

    downloadCsv() {
        const rows = [['Mois', 'Gains', 'Dépenses', 'Solde'], ...(this.currentData?.rows || []).map((row) => [row.month, row.gains, row.expenses, row.balance])];
        const blob = new Blob([`\uFEFF${rows.map((row) => row.map((cell) => `"${String(cell).replaceAll('"', '""')}"`).join(';')).join('\n')}`], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a'); link.download = 'evolution-categorie.csv'; link.href = URL.createObjectURL(blob); link.click(); URL.revokeObjectURL(link.href);
    }

    printPdf() { window.print(); }

    async saveFavorite() {
        const name = window.prompt('Nom de cette vue favorite'); if (!name) return;
        const form = new FormData(); form.append('_token', this.csrfValue); form.append('name', name);
        const filters = {}; new FormData(this.formTarget).forEach((value, key) => { const normalized = key.replace('[]', ''); if (key.endsWith('[]')) { filters[normalized] ||= []; filters[normalized].push(value); } else { filters[normalized] = value; } }); form.append('filters_json', JSON.stringify(filters));
        const response = await fetch(this.favoriteEndpointValue, { method: 'POST', body: form });
        if (!response.ok) { this.errorTarget.textContent = 'Impossible d’enregistrer cette vue.'; this.errorTarget.classList.remove('hidden'); return; }
        this.statusTarget.textContent = 'Vue favorite enregistrée.';
    }
}
