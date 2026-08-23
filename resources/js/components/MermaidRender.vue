<script setup>
import { ref, watch, nextTick } from 'vue'

const props = defineProps({
  code: {
    type: String,
    required: true
  },
  id: {
    type: [String, Number],
    required: true
  }
})

const svgHtml = ref('')
const loading = ref(false)

const render = async () => {
  if (!props.code.trim()) return
  loading.value = true
  try {
    const loadM = () => {
      if (window.mermaid) return Promise.resolve(window.mermaid)
      return new Promise(r => setTimeout(() => r(loadM()), 200))
    }
    const m = await loadM()
    const elementId = `mermaid-svg-${props.id}-${Math.random().toString(36).substr(2, 9)}`
    const { svg } = await m.render(elementId, props.code)
    svgHtml.value = svg
  } catch (e) {
    svgHtml.value = `<div class="text-neutral-400 text-[10px] italic">Mermaid 語法編譯中...</div>`
  } finally {
    loading.value = false
  }
}

watch(() => props.code, render, { immediate: true })
</script>

<template>
  <div class="mt-4 border border-purple-100 rounded-xl bg-purple-50/10 p-4">
    <div class="text-[10px] font-bold text-purple-600 uppercase tracking-wide mb-2">Mermaid 流程圖即時渲染：</div>
    <div
      class="flex justify-center bg-white p-4 border border-neutral-100 rounded-lg overflow-x-auto select-text"
      v-html="svgHtml"
    ></div>
  </div>
</template>
