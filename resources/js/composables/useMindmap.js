import { ref, watch, nextTick } from 'vue'

const STORAGE_KEY = 'minimalist_mindmap_data'

const COLORS = [
  '#8b5cf6', // 紫色
  '#3b82f6', // 藍色
  '#10b981', // 綠色
  '#f59e0b', // 黃色
  '#ec4899', // 粉色
  '#06b6d4', // 青色
  '#f43f5e'  // 紅色
]

const TEMPLATES = {
  software_design: {
    id: 'root',
    text: '軟體設計文件',
    color: '#1b1b1f',
    details: '本文件說明系統設計大綱與實作計劃。',
    expanded: true,
    children: [
      {
        id: 'overview',
        text: '1. 概述與目標',
        color: '#8b5cf6',
        details: '專案背景：為解決現有系統效能瓶頸。\n目標：提升響應速度，最佳化資料庫檢索。',
        expanded: true,
        children: [
          { id: 'problem-statement', text: '問題定義', color: '#8b5cf6', details: '', expanded: true, children: [] },
          { id: 'goals', text: '目標與非目標', color: '#8b5cf6', details: '', expanded: true, children: [] }
        ]
      },
      {
        id: 'architecture',
        text: '2. 系統架構',
        color: '#3b82f6',
        details: '採用 Laravel (Backend) 與 Vue 3 (Frontend) 的混合式架構。',
        expanded: true,
        children: [
          { id: 'components', text: '關鍵元件', color: '#3b82f6', details: '', expanded: true, children: [] },
          { id: 'data-flow', text: '資料流與整合', color: '#3b82f6', details: '', expanded: true, children: [] }
        ]
      },
      {
        id: 'database-design',
        text: '3. 資料庫設計',
        color: '#10b981',
        details: '使用 MySQL 作為主要儲存體，優化索引結構。',
        expanded: true,
        children: [
          { id: 'entities', text: '實體與關聯', color: '#10b981', details: '', expanded: true, children: [] },
          { id: 'indexing', text: '索引與效能', color: '#10b981', details: '', expanded: true, children: [] }
        ]
      },
      {
        id: 'api-design',
        text: '4. API 介面',
        color: '#f59e0b',
        details: '提供標準的 REST API 供前端非同步存取。',
        expanded: true,
        children: [
          { id: 'endpoints', text: 'REST 端點', color: '#f59e0b', details: '', expanded: true, children: [] },
          { id: 'authentication', text: '身份驗證與安全性', color: '#f59e0b', details: '', expanded: true, children: [] }
        ]
      }
    ]
  },
  ai_workflow: {
    id: 'root',
    text: 'AI 代理系統規範',
    color: '#1b1b1f',
    details: 'AI Agent 系統設計圖。',
    expanded: true,
    children: [
      {
        id: 'agent-goal',
        text: '1. 代理目標',
        color: '#8b5cf6',
        details: '',
        expanded: true,
        children: [
          { id: 'target-audience', text: '目標使用者與角色定義', color: '#8b5cf6', details: '', expanded: true, children: [] },
          { id: 'success-criteria', text: '成功標準', color: '#8b5cf6', details: '', expanded: true, children: [] }
        ]
      },
      {
        id: 'prompts',
        text: '2. 提示詞工程',
        color: '#ec4899',
        details: '',
        expanded: true,
        children: [
          { id: 'system-prompt', text: '系統指令', color: '#ec4899', details: '', expanded: true, children: [] },
          { id: 'few-shot', text: '少樣本範例 (Few-shot)', color: '#ec4899', details: '', expanded: true, children: [] }
        ]
      },
      {
        id: 'tools',
        text: '3. 核心技能與 API',
        color: '#06b6d4',
        details: '',
        expanded: true,
        children: [
          { id: 'web-search', text: '網頁搜尋能力', color: '#06b6d4', details: '', expanded: true, children: [] },
          { id: 'code-sandbox', text: '沙箱執行環境', color: '#06b6d4', details: '', expanded: true, children: [] }
        ]
      }
    ]
  },
  blank: {
    id: 'root',
    text: '新專案大綱',
    color: '#1b1b1f',
    details: '',
    expanded: true,
    children: []
  }
}

export function useMindmap() {
  const mindmap = ref(null)
  const selectedNodeIds = ref([])
  
  // History for Undo/Redo
  const history = ref([])
  const historyIndex = ref(-1)
  let isUndoRedoAction = false

  const generateId = () => 'node-' + Math.random().toString(36).substr(2, 9)

  const findNode = (id, node = mindmap.value) => {
    if (!node) return null
    if (node.id === id) return node
    if (node.children) {
      for (const child of node.children) {
        const found = findNode(id, child)
        if (found) return found
      }
    }
    return null
  }

  const findNodeByText = (text, node = mindmap.value) => {
    if (!node) return null
    if (node.text.trim().toLowerCase() === text.trim().toLowerCase()) return node
    if (node.children) {
      for (const child of node.children) {
        const found = findNodeByText(text, child)
        if (found) return found
      }
    }
    return null
  }

  const findParent = (id, node = mindmap.value, parent = null) => {
    if (!node) return null
    if (node.id === id) return parent
    if (node.children) {
      for (const child of node.children) {
        const found = findParent(id, child, node)
        if (found) return found
      }
    }
    return null
  }

  const isDescendant = (checkNodeId, nodeId) => {
    const parentNode = findNode(nodeId)
    if (!parentNode) return false
    
    const check = (n) => {
      if (n.id === checkNodeId) return true
      if (n.children) {
        for (const child of n.children) {
          if (check(child)) return true
        }
      }
      return false
    }
    
    if (parentNode.children) {
      for (const child of parentNode.children) {
        if (check(child)) return true
      }
    }
    return false
  }

  const recordHistory = () => {
    if (isUndoRedoAction) return
    const stateStr = JSON.stringify(mindmap.value)
    
    if (historyIndex.value < history.value.length - 1) {
      history.value = history.value.slice(0, historyIndex.value + 1)
    }
    
    history.value.push(stateStr)
    if (history.value.length > 50) {
      history.value.shift()
    }
    historyIndex.value = history.value.length - 1
  }

  const undo = () => {
    if (historyIndex.value > 0) {
      isUndoRedoAction = true
      historyIndex.value--
      mindmap.value = JSON.parse(history.value[historyIndex.value])
      saveToSession()
      nextTick(() => { isUndoRedoAction = false })
    }
  }

  const redo = () => {
    if (historyIndex.value < history.value.length - 1) {
      isUndoRedoAction = true
      historyIndex.value++
      mindmap.value = JSON.parse(history.value[historyIndex.value])
      saveToSession()
      nextTick(() => { isUndoRedoAction = false })
    }
  }

  const canUndo = () => historyIndex.value > 0
  const canRedo = () => historyIndex.value < history.value.length - 1

  const selectNode = (nodeId, isMulti = false) => {
    if (isMulti) {
      const idx = selectedNodeIds.value.indexOf(nodeId)
      if (idx !== -1) {
        selectedNodeIds.value.splice(idx, 1)
      } else {
        selectedNodeIds.value.push(nodeId)
      }
    } else {
      selectedNodeIds.value = [nodeId]
    }
  }

  const getNodeColor = (parent) => {
    if (!parent) return COLORS[0]
    if (parent.id === 'root') {
      const index = (parent.children ? parent.children.length : 0) % COLORS.length
      return COLORS[index]
    }
    return parent.color || COLORS[0]
  }

  const addNode = (parentId) => {
    const parent = findNode(parentId)
    if (!parent) return null
    
    const newId = generateId()
    const nodeColor = getNodeColor(parent)
    const newNode = {
      id: newId,
      text: '新子節點',
      color: nodeColor,
      details: '',
      expanded: true,
      children: []
    }
    
    if (!parent.children) {
      parent.children = []
    }
    parent.children.push(newNode)
    parent.expanded = true
    
    selectedNodeIds.value = [newId]
    saveToSession()
    recordHistory()
    return newNode
  }

  const addSiblingNode = (nodeId) => {
    if (nodeId === 'root') return null
    const parent = findParent(nodeId)
    if (!parent) return null
    
    const index = parent.children.findIndex(c => c.id === nodeId)
    const newId = generateId()
    const nodeColor = getNodeColor(parent)
    const newNode = {
      id: newId,
      text: '新節點',
      color: nodeColor,
      details: '',
      expanded: true,
      children: []
    }
    
    parent.children.splice(index + 1, 0, newNode)
    selectedNodeIds.value = [newId]
    saveToSession()
    recordHistory()
    return newNode
  }

  const deleteNode = (nodeId) => {
    if (nodeId === 'root') {
      mindmap.value.children = []
      saveToSession()
      recordHistory()
      return
    }
    
    const parent = findParent(nodeId)
    if (!parent) return
    
    const index = parent.children.findIndex(c => c.id === nodeId)
    if (index !== -1) {
      parent.children.splice(index, 1)
      selectedNodeIds.value = selectedNodeIds.value.filter(id => id !== nodeId)
      if (selectedNodeIds.value.length === 0) {
        selectedNodeIds.value = [parent.id]
      }
      saveToSession()
      recordHistory()
    }
  }

  const moveNode = (nodeId, newParentId) => {
    if (nodeId === 'root' || nodeId === newParentId) return
    if (isDescendant(newParentId, nodeId)) return

    const oldParent = findParent(nodeId)
    const newParent = findNode(newParentId)
    
    if (oldParent && newParent) {
      const idx = oldParent.children.findIndex(c => c.id === nodeId)
      if (idx !== -1) {
        const [nodeToMove] = oldParent.children.splice(idx, 1)
        
        nodeToMove.color = newParentId === 'root' 
          ? COLORS[Math.floor(Math.random() * COLORS.length)] 
          : (newParent.color || COLORS[0])
        
        const applyColor = (n, c) => {
          n.color = c
          if (n.children) n.children.forEach(child => applyColor(child, c))
        }
        applyColor(nodeToMove, nodeToMove.color)

        if (!newParent.children) newParent.children = []
        newParent.children.push(nodeToMove)
        newParent.expanded = true
        
        saveToSession()
        recordHistory()
      }
    }
  }

  const deleteSelectedNodes = () => {
    const targets = [...selectedNodeIds.value].filter(id => id !== 'root')
    if (targets.length === 0) return

    targets.forEach(id => {
      const parent = findParent(id)
      if (parent) {
        const idx = parent.children.findIndex(c => c.id === id)
        if (idx !== -1) {
          parent.children.splice(idx, 1)
        }
      }
    })
    
    selectedNodeIds.value = ['root']
    saveToSession()
    recordHistory()
  }

  const changeSelectedNodesColor = (color) => {
    const applyColor = (node, c) => {
      node.color = c
      if (node.children) {
        node.children.forEach(child => applyColor(child, c))
      }
    }

    selectedNodeIds.value.forEach(id => {
      const node = findNode(id)
      if (node) {
        applyColor(node, color)
      }
    })
    
    saveToSession()
    recordHistory()
  }

  const updateNodeText = (nodeId, text) => {
    const node = findNode(nodeId)
    if (node) {
      node.text = text
      saveToSession()
      recordHistory()
    }
  }

  const updateNodeDetails = (nodeId, details) => {
    const node = findNode(nodeId)
    if (node) {
      node.details = details
      saveToSession()
      recordHistory()
    }
  }

  const updateNodeProperties = (nodeId, properties) => {
    const node = findNode(nodeId)
    if (node) {
      node.properties = properties
      saveToSession()
      recordHistory()
    }
  }

  const toggleNodeExpand = (nodeId) => {
    const node = findNode(nodeId)
    if (node) {
      node.expanded = !node.expanded
      saveToSession()
      recordHistory()
    }
  }

  const nestNode = (nodeId) => {
    const parent = findParent(nodeId)
    if (!parent) return
    
    const index = parent.children.findIndex(c => c.id === nodeId)
    if (index > 0) {
      const siblingAbove = parent.children[index - 1]
      const [nodeToMove] = parent.children.splice(index, 1)
      
      if (!siblingAbove.children) {
        siblingAbove.children = []
      }
      nodeToMove.color = siblingAbove.color
      siblingAbove.children.push(nodeToMove)
      siblingAbove.expanded = true
      
      saveToSession()
      recordHistory()
    }
  }

  const unnestNode = (nodeId) => {
    const parent = findParent(nodeId)
    if (!parent || parent.id === 'root') return
    
    const grandparent = findParent(parent.id)
    if (!grandparent) return
    
    const index = parent.children.findIndex(c => c.id === nodeId)
    const [nodeToMove] = parent.children.splice(index, 1)
    
    nodeToMove.color = grandparent.id === 'root' ? COLORS[Math.floor(Math.random() * COLORS.length)] : grandparent.color
    
    const parentIndex = grandparent.children.findIndex(c => c.id === parent.id)
    grandparent.children.splice(parentIndex + 1, 0, nodeToMove)
    
    saveToSession()
    recordHistory()
  }

  const saveToSession = () => {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(mindmap.value))
  }

  const loadFromSession = () => {
    const data = sessionStorage.getItem(STORAGE_KEY)
    if (data) {
      try {
        mindmap.value = JSON.parse(data)
        history.value = [data]
        historyIndex.value = 0
        selectedNodeIds.value = [mindmap.value.id]
        return true
      } catch (e) {
        console.error('Failed to parse mindmap from session storage', e)
      }
    }
    loadTemplate('software_design')
    return false
  }

  const loadTemplate = (templateType) => {
    const template = TEMPLATES[templateType] || TEMPLATES.blank
    mindmap.value = JSON.parse(JSON.stringify(template))
    selectedNodeIds.value = [mindmap.value.id]
    history.value = [JSON.stringify(mindmap.value)]
    historyIndex.value = 0
    saveToSession()
  }

  const exportToJson = () => {
    return JSON.stringify(mindmap.value, null, 2)
  }

  const importFromJson = (jsonString) => {
    try {
      const parsed = JSON.parse(jsonString)
      if (parsed && typeof parsed === 'object' && parsed.id && parsed.text) {
        mindmap.value = parsed
        selectedNodeIds.value = [parsed.id]
        saveToSession()
        recordHistory()
        return true
      }
    } catch (e) {
      console.error(e)
    }
    return false
  }

  return {
    mindmap,
    selectedNodeIds,
    findNode,
    findNodeByText,
    findParent,
    addNode,
    addSiblingNode,
    deleteNode,
    updateNodeText,
    updateNodeDetails,
    updateNodeProperties,
    toggleNodeExpand,
    nestNode,
    unnestNode,
    selectNode,
    loadFromSession,
    loadTemplate,
    exportToJson,
    importFromJson,
    undo,
    redo,
    canUndo,
    canRedo,
    COLORS
  }
}
