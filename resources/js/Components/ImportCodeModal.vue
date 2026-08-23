<script setup>
import { ref } from 'vue'
import { Terminal as TerminalIcon, X as CloseIcon } from '@lucide/vue'

const props = defineProps({
  show: {
    type: Boolean,
    required: true
  }
})

const emit = defineEmits(['close', 'import'])

const importCodeText = ref('')
const importFormat = ref('auto')

const handleClose = () => {
  emit('close')
}

const handleImport = () => {
  if (!importCodeText.value.trim()) {
    alert('請貼上代碼後再點擊載入！')
    return
  }
  emit('import', {
    text: importCodeText.value,
    format: importFormat.value
  })
  importCodeText.value = ''
}
</script>

<template>
  <transition
    enter-active-class="transition-all duration-300 ease-out"
    leave-active-class="transition-all duration-200 ease-in"
    enter-from-class="opacity-0 scale-95"
    leave-to-class="opacity-0 scale-95"
  >
    <div 
      v-if="show"
      class="fixed inset-0 bg-neutral-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-6 no-select"
    >
      <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col overflow-hidden border border-neutral-100">
        <div class="p-5 border-b border-neutral-100 flex items-center justify-between shrink-0 bg-neutral-50/50">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center text-white">
              <TerminalIcon class="w-4 h-4" />
            </div>
            <div>
              <h2 class="text-sm font-semibold text-neutral-800">匯入 Mermaid / JSON 原始碼</h2>
              <p class="text-[11px] text-neutral-400">貼上 Mermaid 流程圖或 JSON 原始碼，直接轉換為心智圖</p>
            </div>
          </div>
          <button @click="handleClose" class="p-1 hover:bg-neutral-200/50 rounded-lg text-neutral-400 hover:text-neutral-700 transition-colors">
            <CloseIcon class="w-4 h-4" />
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div class="flex items-center justify-between">
            <label class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">選擇原始碼格式</label>
            <select 
              v-model="importFormat"
              class="bg-neutral-50 border border-neutral-200 rounded-lg px-3 py-1 text-xs text-neutral-700 font-medium focus:outline-none focus:border-neutral-300 transition-colors"
            >
              <option value="auto">自動偵測 (Auto)</option>
              <option value="mermaid">Mermaid Flowchart</option>
              <option value="json">JSON 結構代碼</option>
            </select>
          </div>

          <textarea 
            v-model="importCodeText"
            rows="12"
            placeholder="範例 (Mermaid):&#10;graph TD&#10;  A[專案設計] --> B(系統架構)&#10;  A --> C(資料庫)&#10;  B --> D[Vue 前端]&#10;&#10;或是直接貼上心智圖對應的 JSON 代碼..."
            class="w-full p-4 bg-neutral-50 border border-neutral-200 rounded-xl focus:outline-none focus:border-neutral-300 font-mono text-xs text-neutral-700 placeholder:text-neutral-300 resize-none select-text"
          ></textarea>
        </div>

        <div class="p-5 border-t border-neutral-100 flex items-center justify-end gap-3 bg-neutral-50/50">
          <button @click="handleClose" class="px-4 py-2 border border-neutral-200 hover:bg-neutral-100 text-neutral-700 rounded-xl text-xs font-semibold transition-colors">取消</button>
          <button @click="handleImport" class="px-5 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5">
            <span>載入至畫布</span>
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>
