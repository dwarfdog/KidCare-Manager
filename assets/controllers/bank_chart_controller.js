import { Controller } from '@hotwired/stimulus';

// Donut des depenses par categorie: couleurs des categories, total au centre,
// tooltips avec montant et pourcentage. La legende est rendue en HTML par le template.
export default class extends Controller {
    static targets = ['canvas'];
    static values = {
        type: { type: String, default: 'doughnut' },
        labels: Array,
        values: Array,
        colors: Array,
    };

    async connect() {
        if (!this.hasCanvasTarget || this.valuesValue.length === 0) {
            return;
        }

        const { default: Chart } = await import('chart.js/auto');

        if (this.typeValue === 'line') {
            this.renderLine(Chart);
            return;
        }

        this.renderDoughnut(Chart);
    }

    renderLine(Chart) {
        const euro = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });
        this.chart = new Chart(this.canvasTarget, {
            type: 'line',
            data: {
                labels: this.labelsValue,
                datasets: [{
                    data: this.valuesValue,
                    borderColor: '#0f766e',
                    backgroundColor: '#0f766e',
                    tension: 0.25,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (context) => euro.format(context.parsed.y) } },
                },
                scales: { y: { ticks: { callback: (value) => euro.format(value) } } },
            },
        });
    }

    renderDoughnut(Chart) {
        const euro = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });
        const total = this.valuesValue.reduce((sum, value) => sum + value, 0);
        const colors = this.colorsValue.length === this.valuesValue.length
            ? this.colorsValue
            : ['#0f766e', '#2563eb', '#ea580c', '#9333ea', '#dc2626', '#64748b', '#16a34a', '#db2777'];

        const centerTotal = {
            id: 'centerTotal',
            afterDraw(chart) {
                const { ctx, chartArea } = chart;
                const x = (chartArea.left + chartArea.right) / 2;
                const y = (chartArea.top + chartArea.bottom) / 2;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.font = '600 16px ui-sans-serif, system-ui, sans-serif';
                ctx.fillStyle = '#0f172a';
                ctx.fillText(euro.format(total), x, y - 9);
                ctx.font = '400 11px ui-sans-serif, system-ui, sans-serif';
                ctx.fillStyle = '#64748b';
                ctx.fillText('depenses ce mois', x, y + 10);
                ctx.restore();
            },
        };

        this.chart = new Chart(this.canvasTarget, {
            type: 'doughnut',
            data: {
                labels: this.labelsValue,
                datasets: [{
                    data: this.valuesValue,
                    backgroundColor: colors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 8,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                layout: { padding: 8 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                const percent = total > 0 ? Math.round((context.parsed / total) * 1000) / 10 : 0;
                                return `${context.label} : ${euro.format(context.parsed)} (${percent} %)`;
                            },
                        },
                    },
                },
            },
            plugins: [centerTotal],
        });
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy();
        }
    }
}
