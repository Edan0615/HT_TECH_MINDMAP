<script setup>
import { ref, onMounted, watch } from 'vue'

const props = defineProps({
  scores: {
    type: Object,
    required: true
  }
})

const canvasRef = ref(null)
let chartInstance = null

const renderChart = async () => {
  if (!canvasRef.value) return
  
  const loadC = () => {
    if (window.Chart) return Promise.resolve(window.Chart)
    return new Promise(r => setTimeout(() => r(loadC()), 200))
  }
  
  const Chart = await loadC()
  
  if (chartInstance) {
    chartInstance.destroy()
  }

  const ctx = canvasRef.value.getContext('2d')
  chartInstance = new Chart(ctx, {
    type: 'radar',
    data: {
      labels: ['Laravel 整合度', 'Vue 匹配密合度', '資料庫適配性', '技術團隊維護度', '開發迭代效能'],
      datasets: [{
        label: '技術可行性分析評分 (越接近 Laravel Vue 越高分)',
        data: [
          props.scores.laravel_integration || 80,
          props.scores.vue_compatibility || 80,
          props.scores.database_performance || 80,
          props.scores.maintenance_ease || 80,
          props.scores.development_speed || 80
        ],
        backgroundColor: 'rgba(139, 92, 246, 0.2)',
        borderColor: 'rgb(139, 92, 246)',
        pointBackgroundColor: 'rgb(139, 92, 246)',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgb(139, 92, 246)',
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        r: {
          angleLines: { display: true },
          suggestedMin: 0,
          suggestedMax: 100,
          ticks: { stepSize: 20 }
        }
      }
    }
  })
}

onMounted(renderChart)
watch(() => props.scores, renderChart, { deep: true })
</script>

<template>
  <div class="mt-4 border border-blue-100 rounded-xl bg-blue-50/10 p-4">
    <div class="text-[10px] font-bold text-blue-600 uppercase tracking-wide mb-2">技術可行性評分雷達圖 (Chart.js Radar)：</div>
    <div class="w-full h-72 bg-white p-4 border border-neutral-100 rounded-lg">
      <canvas ref="canvasRef"></canvas>
    </div>
  </div>
</template>
