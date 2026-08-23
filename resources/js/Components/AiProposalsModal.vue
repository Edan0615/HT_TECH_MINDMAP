<script setup>
import { Sparkles as SparklesIcon, X as CloseIcon, Check as CheckIcon, AlertCircle as AlertIcon } from '@lucide/vue'

const props = defineProps({
  show: {
    type: Boolean,
    required: true
  },
  proposals: {
    type: Array,
    required: true
  },
  selectedCount: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['close', 'apply', 'toggleAll'])

const handleClose = () => {
  emit('close')
}

const handleToggleAll = (status) => {
  emit('toggleAll', status)
}

const handleApply = () => {
  emit('apply')
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
      <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[85vh] shadow-2xl flex flex-col overflow-hidden border border-neutral-100">
        <div class="p-5 border-b border-neutral-100 flex items-center justify-between shrink-0 bg-neutral-50/50">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-purple-600 flex items-center justify-center text-white">
              <SparklesIcon class="w-4 h-4 animate-pulse" />
            </div>
            <div class="flex items-center justify-between">
              <button @click="handleToggleAll(true)" class="text-[10px] text-purple-600 hover:underline font-semibold">全選</button>
              <span class="text-neutral-300 text-[10px] mx-1.5">|</span>
              <button @click="handleToggleAll(false)" class="text-[10px] text-neutral-500 hover:underline font-semibold">全不選</button>
            </div>
          </div>
          <button @click="handleClose" class="p-1 hover:bg-neutral-200/50 rounded-lg text-neutral-400 hover:text-neutral-700 transition-colors">
            <CloseIcon class="w-4 h-4" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-2.5">
          <div 
            v-for="proposal in proposals" 
            :key="proposal.id"
            class="flex items-start gap-3 p-3 rounded-xl border border-neutral-100 hover:border-neutral-200 transition-all"
            :class="proposal.selected ? 'bg-white shadow-sm border-purple-100' : 'bg-neutral-50/50 opacity-60'"
          >
            <input v-model="proposal.selected" type="checkbox" class="mt-1 rounded text-purple-600 border-neutral-300 focus:ring-purple-400 w-4 h-4 cursor-pointer" />
            <div class="flex-1 text-xs">
              <div class="flex items-center gap-1.5 mb-1.5">
                <span v-if="proposal.type === 'add'" class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">新增</span>
                <span v-else-if="proposal.type === 'delete'" class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-red-50 text-red-700 border border-red-100">刪除</span>
                <span v-else-if="proposal.type === 'update'" class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-amber-50 text-amber-700 border border-amber-100">修改</span>
                <span class="text-[10px] text-neutral-400">目標位置: 「{{ proposal.target }}」</span>
              </div>
              <div class="text-neutral-700 font-medium select-text">
                <template v-if="proposal.type === 'add'">在「{{ proposal.target }}」底下新增子項目：「{{ proposal.text }}」</template>
                <template v-else-if="proposal.type === 'delete'">將節點「{{ proposal.target }}」及其所有子項目刪除</template>
                <template v-else-if="proposal.type === 'update'">將節點「{{ proposal.target }}」文字修改為：「{{ proposal.text }}」</template>
              </div>
            </div>
          </div>
          <div v-if="proposals.length === 0" class="h-48 flex flex-col items-center justify-center border border-dashed border-neutral-200 rounded-xl text-neutral-400 gap-1.5">
            <AlertIcon class="w-5 h-5 text-neutral-300" />
            <span class="text-xs">AI 報告無提出結構性的調整清單</span>
          </div>
        </div>

        <div class="p-5 border-t border-neutral-100 flex items-center justify-between bg-neutral-50/50 shrink-0">
          <span class="text-xs text-neutral-500">已選取套用 <strong class="text-purple-600 font-semibold">{{ selectedCount }}</strong> / {{ proposals.length }} 個變更動作</span>
          <div class="flex items-center gap-3">
            <button @click="handleClose" class="px-4 py-2 border border-neutral-200 hover:bg-neutral-100 text-neutral-700 rounded-xl text-xs font-semibold transition-colors">取消</button>
            <button @click="handleApply" :disabled="selectedCount === 0" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition-colors disabled:opacity-40 disabled:pointer-events-none flex items-center gap-1.5">
              <CheckIcon class="w-3.5 h-3.5" />
              <span>套用所選變更</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>
