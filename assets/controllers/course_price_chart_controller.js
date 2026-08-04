import { Controller } from '@hotwired/stimulus';

// Graphique d'evolution du prix d'un produit (App\Controller\CourseAnalyticsController), rendu a partir
// des donnees embarquees au chargement de la page (pas d'appel AJAX : le changement de produit recharge
// la page via le formulaire de selection). Meme idiome Chart.js que bank_category_evolution_controller.js.
export default class extends Controller {
    static targets = ['canvas'];
    static values = { data: Object };

    async connect() {
        const data = this.dataValue;
        if (!data || !data.hasData) return;

        const { default: Chart } = await import('chart.js/auto');
        const euro = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });

        new Chart(this.canvasTarget, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{ label: 'Prix unitaire', data: data.prices, borderColor: '#0d9488', backgroundColor: '#0d9488', spanGaps: true, tension: 0.25 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { tooltip: { callbacks: { label: (context) => `Prix unitaire : ${euro.format(context.parsed.y)}` } } },
                scales: { y: { ticks: { callback: (value) => euro.format(value) } } },
            },
        });
    }
}
