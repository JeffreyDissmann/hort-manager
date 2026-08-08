import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Chart.js paints to a canvas, so — unlike CSS — it cannot follow a custom property
 * that changes under it. Every chart therefore reads the app's palette itself and
 * redraws when the theme flips.
 *
 * Returns `theme`, a counter to touch inside a computed so it re-evaluates, and
 * `themeColor(name, alpha)`, which wraps a „126 190 195" variable into a real colour.
 */
export function useChartTheme() {
    const theme = ref(0);
    let observer = null;

    onMounted(() => {
        observer = new MutationObserver(() => theme.value++);
        observer.observe(document.documentElement, { attributeFilter: ['class'] });
    });

    onBeforeUnmount(() => observer?.disconnect());

    function themeColor(name, alpha = 1) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

        return value ? `rgb(${value} / ${alpha})` : 'currentColor';
    }

    return { theme, themeColor };
}

/** Axis and grid styling shared by every chart, in the app's ink at low opacity. */
export function axisStyle(themeColor) {
    const ink = (alpha) => themeColor('--color-ink', alpha);

    return {
        x: {
            grid: { display: false },
            border: { color: ink(0.15) },
            ticks: { color: ink(0.5), font: { size: 11 } },
        },
        y: {
            beginAtZero: true,
            grid: { color: ink(0.08) },
            border: { display: false },
            // Counts are whole children; „2.5" on the axis would be nonsense.
            ticks: { color: ink(0.4), font: { size: 11 }, precision: 0 },
        },
    };
}
