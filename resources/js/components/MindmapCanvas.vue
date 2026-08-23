<template>
  <div 
    ref="viewportEl"
    class="flex-1 h-full overflow-hidden bg-neutral-50 relative select-none"
    :class="[
      effectiveMode === 'pan' 
        ? (isDragging ? 'cursor-grabbing' : 'cursor-grab') 
        : 'cursor-default'
    ]"
    @mousedown="onMouseDown"
    @mousemove="onMouseMove"
    @mouseup="onMouseUp"
    @mouseleave="onMouseUp"
    @wheel="onWheel"
    @touchstart.passive="onTouchStart"
    @touchmove.passive="onTouchMove"
    @touchend="onTouchEnd"
  >
    <!-- Grid pattern background for a premium UI feel -->
    <div class="absolute inset-0 bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:16px_16px] pointer-events-none opacity-60"></div>

    <!-- Scalable and Pannable Canvas Container (Removed transition classes for lag-free node tracking) -->
    <div 
      ref="canvasEl"
      class="absolute origin-top-left"
      :style="{ transform: `translate(${panX}px, ${panY}px) scale(${zoom})` }"
    >
      <!-- SVG Overlay for Connecting Lines -->
      <svg class="absolute inset-0 pointer-events-none w-[5000px] h-[5000px]">
        <path 
          v-for="conn in connections" 
          :key="conn.id" 
          :d="conn.path" 
          :stroke="conn.color" 
          stroke-width="1.8" 
          fill="none" 
          class="opacity-60 hover:opacity-100 transition-opacity"
        />
      </svg>

      <!-- Centered visual content root -->
      <div class="p-48 flex items-center justify-start min-h-screen">
        <MindmapTreeNode 
          v-if="mindmap"
          :node="mindmap"
          :selected-node-ids="selectedNodeIds"
          :mode="effectiveMode"
          @select-node="$emit('select-node', $event)"
          @add-node="$emit('add-node', $event)"
          @add-sibling="$emit('add-sibling', $event)"
          @delete-node="$emit('delete-node', $event)"
          @move-node="$emit('move-node', $event)"
          @update-text="(id, text) => $emit('update-text', id, text)"
          @toggle-expand="$emit('toggle-expand', $event)"
        />
      </div>
    </div>

    <!-- Toolbar controls -->
    <div class="absolute bottom-6 left-6 bg-white border border-neutral-100 px-3 py-2 rounded-lg shadow-sm flex items-center gap-3 text-xs text-neutral-500 font-medium z-20 no-select">
      <!-- Mode Toggle (Edit / Pan) -->
      <div class="flex items-center bg-neutral-100 p-0.5 rounded-md gap-0.5">
        <button 
          @click="canvasMode = 'edit'"
          class="p-1 rounded transition-colors flex items-center gap-1"
          :class="canvasMode === 'edit' ? 'bg-white text-neutral-800 shadow-sm' : 'text-neutral-400 hover:text-neutral-600'"
          title="編輯模式 (快捷鍵: Alt)"
        >
          <MousePointerIcon class="w-3.5 h-3.5" />
          <span>編輯 (Alt)</span>
        </button>
        <button 
          @click="canvasMode = 'pan'"
          class="p-1 rounded transition-colors flex items-center gap-1"
          :class="canvasMode === 'pan' ? 'bg-white text-neutral-800 shadow-sm' : 'text-neutral-400 hover:text-neutral-600'"
          title="拖手/瀏覽模式 (快捷鍵: Alt / 可按住空白鍵暫時啟用)"
        >
          <HandIcon class="w-3.5 h-3.5" />
          <span>拖手 (Alt)</span>
        </button>
      </div>

      <div class="w-px h-4 bg-neutral-200"></div>

      <!-- Zoom and Centering -->
      <button @click="zoomIn" class="p-1 hover:bg-neutral-100 rounded text-neutral-600 font-bold">+</button>
      <span>{{ Math.round(zoom * 100) }}%</span>
      <button @click="zoomOut" class="p-1 hover:bg-neutral-100 rounded text-neutral-600 font-bold">-</button>
      <div class="w-px h-3 bg-neutral-200"></div>
      <button @click="recenter" class="hover:text-neutral-800 flex items-center gap-1">
        <Maximize2Icon class="w-3 h-3" />
        <span>重新置中</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick, computed } from 'vue'
import { 
  Maximize2 as Maximize2Icon,
  MousePointer as MousePointerIcon,
  Hand as HandIcon
} from '@lucide/vue'
import MindmapTreeNode from './MindmapTreeNode.vue'

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

defineEmits([
  'select-node',
  'add-node',
  'add-sibling',
  'delete-node',
  'move-node',
  'update-text',
  'toggle-expand'
])

const viewportEl = ref(null)
const canvasEl = ref(null)

const canvasMode = ref('edit')
const isSpacePressed = ref(false)

const effectiveMode = computed(() => {
  return (canvasMode.value === 'pan' || isSpacePressed.value) ? 'pan' : 'edit'
})

const zoom = ref(1)
const panX = ref(100)
const panY = ref(150)
const isDragging = ref(false)
const dragStart = ref({ x: 0, y: 0 })
const touchStartDist = ref(0)
const touchStartZoom = ref(1)

const connections = ref([])

const getHash = (str) => {
  let hash = 0
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash)
  }
  return Math.abs(hash)
}

const onMouseDown = (e) => {
  if (effectiveMode.value === 'edit') {
    if (e.target !== viewportEl.value && !e.target.classList.contains('absolute')) {
      return
    }
  } else {
    if (e.target.closest('button')) return
  }

  isDragging.value = true
  dragStart.value = { x: e.clientX - panX.value, y: e.clientY - panY.value }
}

const onMouseMove = (e) => {
  if (!isDragging.value) return
  panX.value = e.clientX - dragStart.value.x
  panY.value = e.clientY - dragStart.value.y
  updateConnections()
}

const onMouseUp = () => {
  isDragging.value = false
}

const onWheel = (e) => {
  e.preventDefault()
  if (e.ctrlKey) {
    // Zooming using trackpad pinch or Ctrl + Mouse Wheel
    const zoomFactor = 0.05
    const nextZoom = e.deltaY < 0 ? zoom.value + zoomFactor : zoom.value - zoomFactor
    zoom.value = Math.min(Math.max(nextZoom, 0.4), 2.5)
  } else {
    // Panning using trackpad scroll drag (Figma style!)
    panX.value -= e.deltaX
    panY.value -= e.deltaY
  }
  nextTick(updateConnections)
}

// Touch controls for phones & tablets (touch screens)
const onTouchStart = (e) => {
  if (e.target.closest('button')) return
  if (e.touches.length === 1) {
    isDragging.value = true
    const touch = e.touches[0]
    dragStart.value = { x: touch.clientX - panX.value, y: touch.clientY - panY.value }
  } else if (e.touches.length === 2) {
    isDragging.value = false
    const t1 = e.touches[0]
    const t2 = e.touches[1]
    const dist = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY)
    touchStartDist.value = dist
    touchStartZoom.value = zoom.value
  }
}

const onTouchMove = (e) => {
  if (e.touches.length === 1 && isDragging.value) {
    const touch = e.touches[0]
    panX.value = touch.clientX - dragStart.value.x
    panY.value = touch.clientY - dragStart.value.y
    updateConnections()
  } else if (e.touches.length === 2) {
    const t1 = e.touches[0]
    const t2 = e.touches[1]
    const dist = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY)
    if (touchStartDist.value > 0) {
      const factor = dist / touchStartDist.value
      zoom.value = Math.min(Math.max(touchStartZoom.value * factor, 0.4), 2.5)
      nextTick(updateConnections)
    }
  }
}

const onTouchEnd = () => {
  isDragging.value = false
  touchStartDist.value = 0
}

const zoomIn = () => {
  zoom.value = Math.min(zoom.value + 0.1, 2.5)
  nextTick(updateConnections)
}

const zoomOut = () => {
  zoom.value = Math.max(zoom.value - 0.1, 0.4)
  nextTick(updateConnections)
}

const recenter = () => {
  zoom.value = 1
  panX.value = 100
  panY.value = 150
  nextTick(updateConnections)
}

const handleKeyDown = (e) => {
  if (e.code === 'Space') {
    if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') {
      return
    }
    e.preventDefault()
    isSpacePressed.value = true
  }

  if (e.key === 'Alt') {
    if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') {
      return
    }
    e.preventDefault()
    canvasMode.value = canvasMode.value === 'edit' ? 'pan' : 'edit'
  }
}

const handleKeyUp = (e) => {
  if (e.code === 'Space') {
    isSpacePressed.value = false
  }
}

const updateConnections = () => {
  if (!canvasEl.value || !props.mindmap) return
  
  const canvasRect = canvasEl.value.getBoundingClientRect()
  const paths = []

  const traverse = (node) => {
    if (!node || !node.expanded || !node.children) return

    const parentEl = document.getElementById(`node-${node.id}`)
    if (!parentEl) return

    const parentRect = parentEl.getBoundingClientRect()
    const startX = (parentRect.right - canvasRect.left) / zoom.value
    const startY = ((parentRect.top + parentRect.bottom) / 2 - canvasRect.top) / zoom.value

    node.children.forEach(child => {
      const childEl = document.getElementById(`node-${child.id}`)
      if (!childEl) return

      const childRect = childEl.getBoundingClientRect()
      const endX = (childRect.left - canvasRect.left) / zoom.value
      const endY = ((childRect.top + childRect.bottom) / 2 - canvasRect.top) / zoom.value

      const hashVal = getHash(`${node.id}-${child.id}`)
      const curvatureFactor = 0.35 + (hashVal % 25) / 100
      const wobbleY1 = ((hashVal % 40) - 20)
      const wobbleY2 = (((hashVal >> 2) % 40) - 20)

      const controlOffset = Math.min(Math.max(Math.abs(endX - startX) * 0.4, 30), 120)
      const pathStr = `M ${startX},${startY} C ${startX + controlOffset},${startY + wobbleY1} ${endX - controlOffset},${endY + wobbleY2} ${endX},${endY}`
      
      paths.push({
        id: `${node.id}-${child.id}`,
        path: pathStr,
        color: child.color || '#e5e5e7'
      })

      traverse(child)
    })
  }

  nextTick(() => {
    traverse(props.mindmap)
    connections.value = paths
  })
}

watch(() => props.mindmap, () => {
  nextTick(updateConnections)
}, { deep: true })

let resizeObserver = null
let mutationObserver = null

onMounted(() => {
  updateConnections()
  window.addEventListener('resize', updateConnections)
  window.addEventListener('keydown', handleKeyDown)
  window.addEventListener('keyup', handleKeyUp)
  
  if (canvasEl.value) {
    resizeObserver = new ResizeObserver(updateConnections)
    resizeObserver.observe(canvasEl.value)
    
    mutationObserver = new MutationObserver(() => {
      updateConnections()
    })
    mutationObserver.observe(canvasEl.value, {
      childList: true,
      subtree: true,
      characterData: true,
      attributes: true
    })
  }
})

onUnmounted(() => {
  window.removeEventListener('resize', updateConnections)
  window.removeEventListener('keydown', handleKeyDown)
  window.removeEventListener('keyup', handleKeyUp)
  if (resizeObserver) resizeObserver.disconnect()
  if (mutationObserver) mutationObserver.disconnect()
})
</script>
