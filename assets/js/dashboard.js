(function () {

  function renderChart() {

    const canvas = document.getElementById('chart');
    if (!canvas) return;

    function safeParse(v) {
      try { return JSON.parse(v || '[]'); }
      catch { return []; }
    }

    const labels    = safeParse(canvas.dataset.labels);
    const pageviews = safeParse(canvas.dataset.pageviews);
    const sessions  = safeParse(canvas.dataset.sessions);
    const visitors  = safeParse(canvas.dataset.visitors);

    if (!labels.length) return;

    const css = getComputedStyle(document.documentElement);
    const colorText = css.getPropertyValue('--muted').trim() || '#8e8e93';
    const colorGrid = css.getPropertyValue('--border').trim() || '#e5e5ea';

    if (canvas._chart) {
      canvas._chart.destroy();
    }

    canvas._chart = new Chart(canvas.getContext('2d'), {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: canvas.dataset.labelPageviews || 'Page views',
            data: pageviews,
            borderColor: css.getPropertyValue('--accent').trim(),
            backgroundColor: 'rgba(99,102,241,0.15)',
            borderWidth: 1.5,
            pointRadius: 3,
            fill: true,
            tension: 0.35,
          },
          {
            label: canvas.dataset.labelSessions || 'Sessions',
            data: sessions,
            borderColor: css.getPropertyValue('--accent2').trim(),
            borderWidth: 1.5,
            pointRadius: 3,
            fill: false,
            tension: 0.35,
          },
          {
            label: canvas.dataset.labelVisitors || 'Visitors',
            data: visitors,
            borderColor: css.getPropertyValue('--success').trim(),
            borderWidth: 1.5,
            pointRadius: 3,
            fill: false,
            tension: 0.35,
          },
        ]
      },
      options: {
        plugins: {
          legend: {
            labels: { color: colorText }
          }
        },
        scales: {
          x: {
            grid: { color: colorGrid },
            ticks: { color: colorText }
          },
          y: {
            grid: { color: colorGrid },
            ticks: { color: colorText }
          }
        }
      }
    });
  }

  renderChart();

  // 🔥 перерисовка при смене темы
  document.addEventListener('themeChanged', renderChart);

})();