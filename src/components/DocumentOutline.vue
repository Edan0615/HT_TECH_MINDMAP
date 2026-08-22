<template>
  <div class="h-full flex flex-col bg-white border-r border-neutral-100 w-80 text-sm no-select">
    <!-- Header -->
    <div class="p-4 border-b border-neutral-100 flex items-center justify-between">
      <span class="font-medium text-neutral-800 flex items-center gap-1.5">
        <FileTextIcon class="w-4 h-4 text-neutral-500" />
        <span>文件大綱</span>
      </span>
      <span class="text-xs text-neutral-400 font-mono">
        Tab/Shift+Tab 調整階層
      </span>
    </div>

    <!-- Flattened outline items list -->
    <div class="flex-1 overflow-y-auto p-2 space-y-0.5">
      <div 
        v-for="(item, index) in flatItems" 
        :key="item.id"
        :style="{ paddingLeft: `${item.depth * 1.25 + 0.5}rem` }"
        class="flex items-center gap-1.5 py-1 px-2 rounded-md transition-colors relative group"
        :class="[
          selectedNodeIds.includes(item.id) 
            ? 'bg-neutral-50 border-l-2 border-neutral-900 font-medium' 
            : 'hover:bg-neutral-50/50 border-l-2 border-transparent'
        ]"
        @click="selectNode(item.id, $event)"
      >
        <!-- Toggle button for children -->
        <button 
          v-if="item.nodeRef.children && item.nodeRef.children.length > 0"
          @click.stop="toggleExpand(item.id)"
          class="w-4 h-4 flex items-center justify-center text-neutral-400 hover:text-neutral-600 rounded transition-colors"
        >
          <ChevronRightIcon 
            class="w-3 h-3 transition-transform" 
            :class="{ 'rotate-90': item.expanded }"
          />
        </button>
        <div v-else class="w-4 h-4"></div>

        <!-- Editable input -->
        <input 
          :ref="el => { if(el) inputRefs[item.id] = el }"
          v-model="item.nodeRef.text"
          type="text"
          class="flex-1 bg-transparent py-0.5 border-b border-transparent focus:border-neutral-200 focus:outline-none text-neutral-800 text-[13px] placeholder:text-neutral-300"
          placeholder="空白大綱主題..."
          @input="onInput(item.id, $event.target.value)"
          @keydown.enter.prevent="handleEnter(item, index)"
          @keydown.tab.prevent="handleTab(item, index, $event.shiftKey)"
          @keydown.backspace="handleBackspace(item, index, $event)"
          @keydown.up.prevent="focusNeighbor(index, -1)"
          @keydown.down.prevent="focusNeighbor(index, 1)"
          @focus="selectNode(item.id, $event)"
        />

        <!-- Hover node manipulation buttons -->
        <div class="opacity-0 group-hover:opacity-100 flex items-center gap-1 transition-opacity absolute right-2 bg-neutral-50 pl-2">
          <button 
            @click.stop="addChild(item.id)"
            class="p-0.5 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-200/50 rounded"
            title="新增子節點"
          >
            <PlusIcon class="w-3.5 h-3.5" />
          </button>
          <button 
            @click.stop="emit('open-properties', item.id)"
            class="p-0.5 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-200/50 rounded"
            title="編輯細節與屬性"
          >
            <SettingsIcon class="w-3.5 h-3.5" />
          </button>
          <button 
            v-if="item.id !== 'root'"
            @click.stop="deleteNode(item.id)"
            class="p-0.5 text-neutral-400 hover:text-red-500 hover:bg-red-50 rounded"
            title="刪除節點"
          >
            <TrashIcon class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, nextTick } from 'vue'
import { 
  FileText as FileTextIcon, 
  ChevronRight as ChevronRightIcon,
  Plus as PlusIcon,
  Trash as TrashIcon,
  Settings as SettingsIcon
} from '@lucide/vue'

const props = defineProps({
  mindmap: {
    type: Object,
    required: true
  },
  selectedNodeIds: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits([
  'select-node',
  'add-node',
  'add-sibling',
  'delete-node',
  'update-text',
  'toggle-expand',
  'nest-node',
  'unnest-node',
  'open-properties'
])

const inputRefs = ref({})

// Flatten the tree recursively
const flatItems = computed(() => {
  const list = []
  const traverse = (node, depth = 0) => {
    if (!node) return
    list.push({
      id: node.id,
      text: node.text,
      expanded: node.expanded,
      depth,
      nodeRef: node
    })
    if (node.expanded && node.children && node.children.length > 0) {
      node.children.forEach(child => traverse(child, depth + 1))
    }
  }
  traverse(props.mindmap)
  return list
})

const selectNode = (id, event) => {
  emit('select-node', { id, isMulti: event ? (event.ctrlKey || event.metaKey) : false })
}

const toggleExpand = (id) => {
  emit('toggle-expand', id)
}

const onInput = (id, text) => {
  emit('update-text', id, text)
}

const addChild = (parentId) => {
  emit('add-node', parentId)
  focusNodeLater()
}

const deleteNode = (id) => {
  emit('delete-node', id)
}

const handleEnter = (item, index) => {
  if (item.id === 'root') {
    emit('add-node', 'root')
  } else {
    emit('add-sibling', item.id)
  }
  focusNodeLater()
}

const handleTab = (item, index, isShift) => {
  if (item.id === 'root') return
  
  if (isShift) {
    emit('unnest-node', item.id)
  } else {
    emit('nest-node', item.id)
  }
  
  focusNodeLater(item.id)
}

const handleBackspace = (item, index, event) => {
  if (item.id === 'root') return
  
  if (item.nodeRef.text === '') {
    event.preventDefault()
    const targetIndex = index - 1
    const prevItem = flatItems.value[targetIndex]
    
    emit('delete-node', item.id)
    
    if (prevItem) {
      nextTick(() => {
        const input = inputRefs.value[prevItem.id]
        if (input) input.focus()
      })
    }
  }
}

const focusNeighbor = (currentIndex, direction) => {
  const targetIndex = currentIndex + direction
  const targetItem = flatItems.value[targetIndex]
  if (targetItem) {
    const input = inputRefs.value[targetItem.id]
    if (input) input.focus()
  }
}

const focusNodeLater = (nodeId = null) => {
  nextTick(() => {
    // Pick the first selected ID to focus
    const id = nodeId || (props.selectedNodeIds.length > 0 ? props.selectedNodeIds[0] : null)
    if (id) {
      const input = inputRefs.value[id]
      if (input) {
        input.focus()
      }
    }
  })
}
</script>
