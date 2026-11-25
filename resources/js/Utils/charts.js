let chartPromise;

export function loadChart() {
    chartPromise ??= import("chart.js/auto").then((mod) => mod.default ?? mod.Chart);

    return chartPromise;
}