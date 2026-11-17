import '@fontsource-variable/inter';
import Alpine from 'alpinejs';
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

window.Alpine = Alpine;

/**
 * A transactions-table row whose category and person can be changed in place.
 * Each change is saved at once; the little state flag shows saving / saved / failed.
 */
Alpine.data('quickRow', (url, category, owner) => ({
    category: category ?? '',
    owner,
    state: '',
    async save() {
        this.state = 'saving';
        try {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ category_id: this.category || null, owner: this.owner }),
            });
            this.state = response.ok ? 'saved' : 'error';
        } catch (e) {
            this.state = 'error';
        }
        if (this.state === 'saved') {
            setTimeout(() => { this.state = ''; }, 1500);
        }
    },
}));

Alpine.start();

/**
 * Charts are described by data attributes so Blade stays in charge of the data:
 * <canvas data-chart="bar" data-config='{"labels":[],"datasets":[],"horizontal":true}'>
 */
function css(name) {
    return `rgb(${getComputedStyle(document.documentElement).getPropertyValue(name).trim().split(' ').join(',')})`;
}

function money(value) {
    const lang = document.documentElement.lang || 'pt';
    return new Intl.NumberFormat(lang === 'pt' ? 'pt-PT' : lang, {
        style: 'currency',
        currency: document.documentElement.dataset.currency || 'EUR',
    }).format(value / 100);
}

document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
    const config = JSON.parse(canvas.dataset.config);
    const muted = css('--muted');
    const grid = css('--line');
    const horizontal = !!config.horizontal;

    new Chart(canvas, {
        type: 'bar',
        data: { labels: config.labels, datasets: config.datasets.map((d) => ({ ...d, borderRadius: 3, borderSkipped: false })) },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : undefined,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: ${money(ctx.parsed[horizontal ? 'x' : 'y'])}`,
                    },
                },
            },
            scales: {
                // The index axis lists the categories, the other one carries the money.
                [horizontal ? 'y' : 'x']: { type: 'category', stacked: true, grid: { display: false }, ticks: { color: muted }, border: { display: false } },
                [horizontal ? 'x' : 'y']: { type: 'linear', stacked: true, grid: { color: grid }, ticks: { color: muted, callback: (v) => money(v) }, border: { display: false } },
            },
        },
    });
});
