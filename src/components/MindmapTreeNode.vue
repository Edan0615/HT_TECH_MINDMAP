<template>
  <div class="flex items-center gap-12 relative py-2">
    <!-- Node Box -->
    <div 
      :id="'node-' + node.id"
      class="node-box flex items-center gap-2 px-3 py-1.5 bg-white border rounded-lg shadow-sm hover:shadow-md transition-all z-10 select-none group min-w-[100px] max-w-[320px]"
      :class="[
        isSelected 
          ? 'border-neutral-950 ring-1 ring-neutral-950 bg-neutral-50/50' 
          : 'border-neutral-200',
        mode === 'pan' ? 'cursor-grab active:cursor-grabbing' : 'cursor-default'
      ]"
      :style="{ borderLeftColor: node.color, borderLeftWidth: '4px' }"
      @mousedown.stop="selectNode"
    >
      <!-- Expand/Collapse toggle for tree -->
      <button 
        v-if="node.children && node.children.length > 0"
        @click.stop="toggleExpand"
        class="w-5 h-5 flex items-center justify-center text-neutral-400 hover:text-neutral-700 rounded-md hover:bg-neutral-100 transition-colors z-20 shrink-0 cursor-pointer"
      >
        <ChevronRightIcon 
          class="w-3.5 h-3.5 transition-transform" 
          :class="{ 'rotate-90': node.expanded }"
        />
      </button>
      <div v-else class="w-1.5 h-1.5 rounded-full mx-1.5 shrink-0" :style="{ backgroundColor: node.color || '#e5e5e5' }"></div>

      <!-- Contenteditable span for dynamic width based on text content -->
      <span
        ref="textEl"
        contenteditable="true"
        class="flex-1 bg-transparent text-[13px] text-neutral-800 focus:outline-none p-0 font-medium placeholder:text-neutral-300 min-w-[40px] max-w-[240px] break-words whitespace-pre-wrap outline-none"
        :class="mode === 'pan' ? 'pointer-events-none select-none' : 'select-text cursor-text'"
        @blur="onBlur"
        @keydown.enter.prevent="onEnter"
        @keydown.tab.prevent="addChild"
      >{{ node.text }}</span>

      <!-- Node controls on hover -->
      <div 
        v-if="mode !== 'pan'"
        class="opacity-0 group-hover:opacity-100 flex items-center gap-0.5 transition-opacity pl-1 shrink-0"
      >
        <button 
          @click.stop="addChild"
          class="p-0.5 text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 rounded"
          title="新增子節點"
        >
          <PlusIcon class="w-3.5 h-3.5" />
        </button>
        <button 
          v-if="node.id !== 'root'"
          @click.stop="deleteNode"
          class="p-0.5 text-neutral-400 hover:text-red-500 hover:bg-red-50 rounded"
          title="刪除節點"
        >
          <TrashIcon class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Recursive children -->
    <div 
      v-if="node.expanded && node.children && node.children.length > 0" 
      class="flex flex-col gap-4 relative justify-center"
    >
      <MindmapTreeNode 
        v-for="child in node.children" 
        :key="child.id" 
        :node="child"
        :selected-node-ids="selectedNodeIds"
        :mode="mode"
        @select-node="$emit('select-node', $event)"
        @add-node="$emit('add-node', $event)"
        @add-sibling="$emit('add-sibling', $event)"
        @delete-node="$emit('delete-node', $event)"
        @update-text="$emit('update-text', $event.id, $event.text)"
        @toggle-expand="$emit('toggle-expand', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { 
  ChevronRight as ChevronRightIcon, 
  Plus as PlusIcon, 
  Trash as TrashIcon
} from '@lucide/vue'

const props = defineProps({
  node: {
    type: Object,
    required: true
  },
  selectedNodeIds: {
    type: Array,
    default: () => []
  },
  mode: {
    type: String,
    default: 'edit'
  }
})

const emit = defineEmits([
  'select-node',
  'add-node',
  'add-sibling',
  'delete-node',
  'update-text',
  'toggle-expand'
])

const textEl = ref(null)

const isSelected = computed(() => {
  return props.selectedNodeIds.includes(props.node.id)
})

watch(() => props.node.text, (newText) => {
  if (textEl.value && textEl.value.innerText !== newText) {
    textEl.value.innerText = newText
  }
})

const selectNode = (e) => {
  emit('select-node', { id: props.node.id, isMulti: e.ctrlKey || e.metaKey })
}

const toggleExpand = () => {
  emit('toggle-expand', props.node.id)
}

const onBlur = (e) => {
  const text = e.target.innerText.trim()
  emit('update-text', { id: props.node.id, text: text || '主題' })
}

const onEnter = (e) => {
  e.target.blur()
}

const addChild = () => {
  emit('add-node', props.node.id)
}

const addSibling = () => {
  if (props.node.id === 'root') {
    emit('add-node', 'root')
  } else {
    emit('add-sibling', props.node.id)
  }
}

const deleteNode = () => {
  emit('delete-node', props.node.id)
}
</script>
