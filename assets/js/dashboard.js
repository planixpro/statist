(function () {
  const canvas = document.getElementById('chart');
  if (!canvas) return;

  const labels    = JSON.parse(canvas.dataset.labels    || '[]');
  const pageviews = JSON.parse(canvas.dataset.pageviews || '[]');
  const sessions  = JSON.parse(canvas.dataset.sessions  || '[]');
  const visitors  = JSON.parse(canvas.dataset.visitors  || '[]');

  new Chart(canvas.getContext('2d'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: canvas.dataset.labelPageviews || 'Page views',
          data: pageviews,
          borderColor: '#4f46e5',
          backgroundColor: 'rgba(79,70,229,0.07)',
          borderWidth: 1.5,
          pointRadius: 3,
          pointBackgroundColor: '#4f46e5',
          fill: true,
          tension: 0.35,
          order: 3,
        },
        {
          label: canvas.dataset.labelSessions || 'Sessions',
          data: sessions,
          borderColor: '#0ea5e9',
          backgroundColor: 'transparent',
          borderWidth: 1.5,
          pointRadius: 3,
          pointBackgroundColor: '#0ea5e9',
          fill: false,
          tension: 0.35,
          order: 2,
        },
        {
          label: canvas.dataset.labelVisitors || 'Visitors',
          data: visitors,
          borderColor: '#34c759',
          backgroundColor: 'transparent',
          borderWidth: 1.5,
          pointRadius: 3,
          pointBackgroundColor: '#34c759',
          fill: false,
          tension: 0.35,
          order: 1,
        },
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          display: true,
          position: 'top',
          align: 'start',
          labels: {
            boxWidth: 12,
            boxHeight: 2,
            padding: 18,
            color: '#48484a',
            font: { family: 'Inter', size: 11 },
          }
        },
        tooltip: {
          callbacks: {
            label: function(ctx) {
              return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString();
            }
          }
        }
      },
      scales: {
        x: {
          grid: { color: '#e5e5ea' },
          ticks: { color: '#8e8e93', font: { family: 'JetBrains Mono', size: 10 } }
        },
        y: {
          beginAtZero: true,
          grid: { color: '#e5e5ea' },
          ticks: { color: '#8e8e93', font: { family: 'JetBrains Mono', size: 10 }, precision: 0 }
        }
      }
    }
  });
})();
