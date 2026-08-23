<script setup>
import { Sparkles as SparklesIcon, ChevronDown as ChevronDownIcon, Trash2 as TrashIcon } from '@lucide/vue'

const props = defineProps({
  show: {
    type: Boolean,
    required: true
  },
  selectedNode: {
    type: Object,
    default: null
  },
  aiDetailsLoading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits([
  'close', 
  'generateDetails', 
  'updateDetails', 
  'addProperty', 
  'renameProperty', 
  'updateProperty', 
  'deleteProperty'
])

const handleClose = () => {
  emit('close')
}

const handleGenerateDetails = () => {
  emit('generateDetails')
}

const handleUpdateDetails = (val) => {
  emit('updateDetails', val)
}

const handleAddProperty = () => {
  emit('addProperty')
}

const handleRenameProperty = (oldKey, newKey) => {
  emit('renameProperty', { oldKey, newKey })
}

const handleUpdateProperty = (key, val) => {
  emit('updateProperty', { key, val })
}

const handleDeleteProperty = (key) => {
  emit('deleteProperty', key)
}
</script>

<template>
  <transition
    enter-active-class="transition-all duration-300 ease-out"
    leave-active-class="transition-all duration-200 ease-in"
    enter-from-class="translate-y-full opacity-0"
    leave-to-class="translate-y-full opacity-0"
  >
    <div 
      v-if="show && selectedNode"
      class="h-72 bg-white border-t border-neutral-100 flex flex-col shrink-0 relative z-20 shadow-lg"
    >
      <!-- Drawer Header -->
      <div class="h-10 px-6 border-b border-neutral-100 bg-neutral-50/50 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: selectedNode.color || '#a855f7' }"></div>
          <span class="text-xs font-semibold text-neutral-700">節點設計明細與實作計劃：{{ selectedNode.text }}</span>
        </div>
        <div class="flex items-center gap-3">
          <button 
            @click="handleGenerateDetails"
            :disabled="aiDetailsLoading"
            class="flex items-center gap-1.5 px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-[10px] font-semibold transition-colors disabled:opacity-40 disabled:pointer-events-none"
          >
            <SparklesIcon class="w-3 h-3 animate-pulse" />
            <span>{{ aiDetailsLoading ? 'AI 正在撰寫計畫...' : '🤖 AI 產生詳細實作計劃與 Mermaid 流程圖' }}</span>
          </button>
          <button 
            @click="handleClose"
            class="p-0.5 hover:bg-neutral-200/50 rounded-md text-neutral-400 hover:text-neutral-700"
          >
            <ChevronDownIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Drawer Content Panel -->
      <div class="flex-1 flex min-h-0 divide-x divide-neutral-100 p-4 gap-4">
        <!-- Left side: Detailed Description and Plan -->
        <div class="flex-1 flex flex-col space-y-2 min-w-0">
          <label class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">📝 功能明細與開發計畫</label>
          <textarea 
            :value="selectedNode.details || ''"
            @input="e => handleUpdateDetails(e.target.value)"
            placeholder="貼入或在這裡撰寫該節點的詳細功能描述、規格說明、執行計劃、或 Mermaid 流程代碼..."
            class="w-full flex-1 p-3 bg-neutral-50 border border-neutral-100 rounded-xl focus:outline-none focus:border-neutral-200 font-mono text-xs text-neutral-700 placeholder:text-neutral-300 resize-none select-text animate-fade-in"
          ></textarea>
        </div>

        <!-- Right side: JSON Custom Attributes -->
        <div class="w-80 pl-4 flex flex-col space-y-2 shrink-0">
          <div class="flex items-center justify-between">
            <label class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">📊 節點自訂屬性 (JSON Properties)</label>
            <button 
              @click="handleAddProperty" 
              class="px-2 py-1 bg-purple-50 hover:bg-purple-100 border border-purple-200 text-purple-700 rounded-lg text-[9px] font-bold transition-all cursor-pointer"
            >
              ➕ 新增欄位
            </button>
          </div>
          
          <!-- Properties List -->
          <div class="flex-1 overflow-y-auto space-y-2 pr-1 text-xs">
            <div v-if="!selectedNode.properties || Object.keys(selectedNode.properties || {}).length === 0" class="h-full flex items-center justify-center text-[10px] text-neutral-400 italic">
              尚未設定自訂欄位。點擊上方新增。
            </div>
            <div 
              v-else
              v-for="(val, key) in selectedNode.properties" 
              :key="key"
              class="flex items-center gap-1.5 bg-neutral-50 p-2 rounded-lg border border-neutral-100"
            >
              <!-- Key Input -->
              <input 
                type="text" 
                :value="key" 
                @blur="e => handleRenameProperty(key, e.target.value.trim())"
                placeholder="欄位名"
                class="w-20 bg-white border border-neutral-200 rounded px-1.5 py-0.5 text-[10px] font-bold text-neutral-700 focus:border-purple-300 outline-none"
              />
              <span class="text-neutral-400 font-bold">:</span>
              <!-- Value Input -->
              <input 
                type="text" 
                :value="val" 
                @input="e => handleUpdateProperty(key, e.target.value)"
                placeholder="值內容"
                class="flex-1 bg-white border border-neutral-200 rounded px-1.5 py-0.5 text-[10px] font-medium text-neutral-600 focus:border-purple-300 outline-none select-text"
              />
              <!-- Delete Button -->
              <button 
                @click="handleDeleteProperty(key)"
                class="p-1 hover:bg-red-50 text-neutral-400 hover:text-red-500 rounded transition-all cursor-pointer"
              >
                <TrashIcon class="w-3 h-3" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>
