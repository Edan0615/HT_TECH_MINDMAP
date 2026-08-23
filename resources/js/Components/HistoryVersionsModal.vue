<script setup>
import { ref } from 'vue'

const props = defineProps({
  show: { type: Boolean, required: true },
  historyVersions: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'loadVersion', 'deleteVersion', 'renameVersion'])

const editingIndex = ref(-1)
const editInputName = ref('')

const startEditing = (index, version) => {
  editingIndex.value = index
  editInputName.value = version.label || version.timestamp
}

const saveEditing = (index) => {
  if (editInputName.value.trim()) {
    emit('renameVersion', { index, newName: editInputName.value.trim() })
  }
  editingIndex.value = -1
}

const handleLoad = (version) => {
  emit('loadVersion', version)
  emit('close')
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
      class="fixed inset-0 bg-neutral-900/60 backdrop-blur-sm z-[60] flex items-center justify-center p-4 no-select"
    >
      <div class="bg-white rounded-2xl w-full max-w-xl shadow-2xl flex flex-col overflow-hidden border border-neutral-100 max-h-[80vh]">
        <!-- Modal Header -->
        <div class="p-5 border-b border-neutral-100 flex items-center justify-between bg-neutral-50/50">
          <div class="flex items-center gap-2">
            <span class="text-lg">📜</span>
            <div>
              <h3 class="text-sm font-bold text-neutral-800">歷史分析版本時光機</h3>
              <p class="text-[11px] text-neutral-400 m-0">管理您的十三層架構分析存檔，可自由載入、重命名或刪除</p>
            </div>
          </div>
          <button 
            @click="emit('close')"
            class="w-6 h-6 rounded-full hover:bg-neutral-100 text-neutral-400 hover:text-neutral-600 transition-colors flex items-center justify-center text-sm cursor-pointer"
          >
            ✕
          </button>
        </div>

        <!-- Versions List -->
        <div class="p-5 overflow-y-auto space-y-3.5 flex-1 min-h-[250px]">
          <div v-if="historyVersions.length === 0" class="text-center py-10 space-y-2">
            <span class="text-3xl block">📁</span>
            <div class="text-xs font-bold text-neutral-400">目前尚無任何儲存的歷史版本</div>
            <div class="text-[10px] text-neutral-400">請在分析暫停或完成時，點擊右下角「儲存為新版本」</div>
          </div>

          <div 
            v-for="(v, index) in historyVersions" 
            :key="index"
            class="p-4 border border-neutral-200/80 rounded-xl hover:border-purple-300 hover:bg-purple-50/5 transition-all flex items-center justify-between gap-4"
          >
            <!-- Title & Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <!-- Inline Edit Name Input -->
                <div v-if="editingIndex === index" class="flex items-center gap-1.5 w-full">
                  <input 
                    v-model="editInputName" 
                    type="text" 
                    class="flex-1 text-xs border border-purple-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400 font-semibold"
                    @keyup.enter="saveEditing(index)"
                    @blur="saveEditing(index)"
                    v-focus
                    placeholder="輸入版本備註名稱..."
                  />
                  <button 
                    @click="saveEditing(index)" 
                    class="px-2 py-1 bg-purple-600 text-white text-[10px] font-bold rounded hover:bg-purple-700 transition-colors cursor-pointer"
                  >
                    儲存
                  </button>
                </div>
                <div v-else class="flex items-center gap-1.5 group cursor-pointer max-w-full" @click="startEditing(index, v)">
                  <span class="text-xs font-bold text-neutral-700 truncate font-mono">
                    {{ v.label || v.timestamp }}
                  </span>
                  <span class="text-[10px] text-neutral-400 opacity-0 group-hover:opacity-100 transition-opacity">✏️ 修改名稱</span>
                </div>
              </div>
              <div class="flex items-center gap-2 mt-1 text-[10px] text-neutral-400">
                <span class="bg-neutral-100 px-1.5 py-0.5 rounded text-neutral-600 font-mono">{{ v.timestamp }}</span>
                <span>•</span>
                <span>口吻：{{ v.mbtiStyle }}</span>
                <span>•</span>
                <span>讀者：{{ v.isUserEngineer ? '工程師' : '通俗' }}</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 shrink-0">
              <button 
                @click="handleLoad(v)"
                class="px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg text-xs font-bold border border-purple-200 transition-colors cursor-pointer"
              >
                載入此版本
              </button>
              <button 
                @click="emit('deleteVersion', index)"
                class="p-2 border border-neutral-200 hover:border-red-200 hover:bg-red-50 text-neutral-400 hover:text-red-600 rounded-lg transition-colors cursor-pointer"
                title="刪除此版本"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-neutral-100 flex justify-end bg-neutral-50/50">
          <button 
            @click="emit('close')"
            class="px-4 py-2 border border-neutral-200 hover:bg-neutral-100 text-neutral-700 rounded-xl text-xs font-semibold transition-colors cursor-pointer"
          >
            關閉
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
// Directive for auto-focus on rename input activation
export const focus = {
  mounted: (el) => el.focus()
}
export default {
  directives: {
    focus
  }
}
</script>
