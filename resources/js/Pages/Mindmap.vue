<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick, defineComponent, h } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useMindmap } from '@/composables/useMindmap'
import { parseMermaidToTree, parseRawJson } from '@/utils/codeParser'
import DocumentOutline from '@/Components/DocumentOutline.vue'
import MindmapCanvas from '@/Components/MindmapCanvas.vue'
import AiPanel from '@/Components/AiPanel.vue'
import ImportCodeModal from '@/Components/ImportCodeModal.vue'
import AiProposalsModal from '@/Components/AiProposalsModal.vue'
import NodeDetailsDrawer from '@/Components/NodeDetailsDrawer.vue'
import MultiStageModal from '@/Components/MultiStageModal.vue'

const props = defineProps({
  mindmap: {
    type: Object,
    required: true
  }
})

const isSaving = ref(false)

// Auto-save inactivity timer state
const showAutoSaveModal = ref(false)
let idleTimer = null
let hasAutoSaved = false

const resetIdleTimer = () => {
  if (idleTimer) clearTimeout(idleTimer)
  hasAutoSaved = false
  
  idleTimer = setTimeout(() => {
    if (!hasAutoSaved) {
      hasAutoSaved = true
      triggerAutoSave()
    }
  }, 60000) // 1 minute
}

const triggerAutoSave = async () => {
  if (!isDirty.value) return
  isSaving.value = true
  try {
    const res = await window.axios.post('/mindmaps', {
      id: props.mindmap.id,
      title: mindmap.value?.text || '未命名心智圖',
      folder: props.mindmap.folder || '網站',
      data: mindmap.value,
      ai_history: {
        stageOutputs: stageOutputs.value,
        stagesProgress: stagesProgress.value
      }
    })
    if (res.data.success) {
      showAutoSaveModal.value = true
      isDirty.value = false
      router.reload({ only: ['mindmap'] })
    }
  } catch (e) {
    console.error('自動儲存失敗：', e)
  } finally {
    isSaving.value = false
  }
}

// Dirty state tracking to prevent saving without edits
const isDirty = ref(false)
const isLoaded = ref(false)

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

const addCustomProperty = () => {
  if (!selectedNode.value) return
  if (!selectedNode.value.properties) {
    selectedNode.value.properties = {}
  }
  let newKey = '新欄位'
  let counter = 1
  while (selectedNode.value.properties.hasOwnProperty(newKey)) {
    newKey = `新欄位_${counter++}`
  }
  selectedNode.value.properties[newKey] = ''
  updateNodeProperties(selectedNode.value.id, { ...selectedNode.value.properties })
}

const updatePropertyValue = (key, value) => {
  if (!selectedNode.value || !selectedNode.value.properties) return
  selectedNode.value.properties[key] = value
  updateNodeProperties(selectedNode.value.id, { ...selectedNode.value.properties })
}

const renamePropertyKey = (oldKey, newKey) => {
  if (!selectedNode.value || !selectedNode.value.properties) return
  if (!newKey || oldKey === newKey) return
  
  if (selectedNode.value.properties.hasOwnProperty(newKey)) {
    alert('此欄位名稱已存在！')
    return
  }
  
  const val = selectedNode.value.properties[oldKey]
  delete selectedNode.value.properties[oldKey]
  selectedNode.value.properties[newKey] = val
  updateNodeProperties(selectedNode.value.id, { ...selectedNode.value.properties })
}

const deleteProperty = (key) => {
  if (!selectedNode.value || !selectedNode.value.properties) return
  delete selectedNode.value.properties[key]
  updateNodeProperties(selectedNode.value.id, { ...selectedNode.value.properties })
}

const saveToDatabase = async () => {
  isSaving.value = true
  try {
    const res = await window.axios.post('/mindmaps', {
      id: props.mindmap.id,
      title: mindmap.value?.text || '未命名心智圖',
      folder: props.mindmap.folder || '網站',
      data: mindmap.value,
      ai_history: {
        stageOutputs: stageOutputs.value,
        stagesProgress: stagesProgress.value
      }
    })
    if (res.data.success) {
      alert('儲存成功！')
      isDirty.value = false
      router.reload({ only: ['mindmap'] })
    }
  } catch (e) {
    alert('儲存失敗：' + (e.response?.data?.message || e.message))
  } finally {
    isSaving.value = false
  }
}

// Project Reader state & actions
const showProjectReaderModal = ref(false)
const showRevisionDrawer = ref(false)
const projectUsers = ref([])
const selectedProjectUser = ref('edan898')
const projects = ref([])
const selectedProject = ref('')
const projectFiles = ref([])
const fileFilter = ref('')
const selectedFile = ref(null)
const selectedFileContent = ref('')
const isFileLoading = ref(false)
const isTreeLoading = ref(false)
const isProjectsLoading = ref(false)

const openProjectReader = async () => {
  showProjectReaderModal.value = true
  isProjectsLoading.value = true
  try {
    const usersRes = await window.axios.get('/api/projects/users')
    if (usersRes.data.success) {
      projectUsers.value = usersRes.data.users
    }
    await fetchProjectsForUser()
  } catch (e) {
    console.error('開啟檔案閱讀器失敗：', e)
  } finally {
    isProjectsLoading.value = false
  }
}

const fetchProjectsForUser = async () => {
  isProjectsLoading.value = true
  projects.value = []
  projectFiles.value = []
  selectedFile.value = null
  selectedFileContent.value = ''
  selectedProject.value = ''
  
  try {
    const res = await window.axios.get('/api/projects', {
      params: { username: selectedProjectUser.value }
    })
    if (res.data.success && res.data.projects.length > 0) {
      projects.value = res.data.projects
      
      const hasBeartor = res.data.projects.find(p => p.name === 'beartor')
      const targetProj = hasBeartor ? 'beartor' : res.data.projects[0].name
      selectedProject.value = targetProj
      await selectProject(targetProj)
    }
  } catch (e) {
    console.error('取得專案清單失敗：', e)
  } finally {
    isProjectsLoading.value = false
  }
}

const selectProject = async (projectName) => {
  selectedProject.value = projectName
  isTreeLoading.value = true
  selectedFile.value = null
  selectedFileContent.value = ''
  try {
    const res = await window.axios.post('/api/projects/tree', {
      project: projectName,
      username: selectedProjectUser.value
    })
    if (res.data.success) {
      projectFiles.value = res.data.files
    }
  } catch (e) {
    console.error('取得檔案樹失敗：', e)
  } finally {
    isTreeLoading.value = false
  }
}

const selectFile = async (file) => {
  selectedFile.value = file
  isFileLoading.value = true
  selectedFileContent.value = ''
  try {
    const res = await window.axios.post('/api/projects/read', {
      project: selectedProject.value,
      file_path: file.relative_path,
      username: selectedProjectUser.value
    })
    if (res.data.success) {
      selectedFileContent.value = res.data.content
    }
  } catch (e) {
    selectedFileContent.value = '讀取檔案失敗：' + (e.response?.data?.message || e.message)
  } finally {
    isFileLoading.value = false
  }
}

const filteredFiles = computed(() => {
  if (!fileFilter.value.trim()) return projectFiles.value
  const filter = fileFilter.value.toLowerCase()
  return projectFiles.value.filter(f => 
    f.relative_path.toLowerCase().includes(filter) || f.name.toLowerCase().includes(filter)
  )
})

const expandedFolders = ref({})

const toggleFolder = (folderName) => {
  expandedFolders.value[folderName] = expandedFolders.value[folderName] === false ? true : false
}

const groupedFiles = computed(() => {
  const groups = {}
  filteredFiles.value.forEach(file => {
    const parts = file.relative_path.split('/')
    const dir = parts.slice(0, -1).join('/') || '/'
    if (!groups[dir]) {
      groups[dir] = []
    }
    groups[dir].push(file)
  })
  return groups
})

import { 
  Menu as MenuIcon, 
  Sparkles as SparklesIcon,
  Download as DownloadIcon,
  Upload as UploadIcon,
  RotateCcw as UndoIcon,
  RotateCw as RedoIcon,
  Trash2 as TrashIcon,
  FileSpreadsheet as TemplateIcon,
  Folder as FolderIcon,
  FileCode as FileIcon,
  History as HistoryIcon,
  Eye as EyeIcon,
  EyeOff as EyeOffIcon,
  X as CloseIcon,
  Palette as PaletteIcon,
  Check as CheckIcon,
  AlertCircle as AlertIcon,
  Terminal as TerminalIcon,
  BookOpen as BookOpenIcon,
  ChevronDown as ChevronDownIcon,
  ChevronUp as ChevronUpIcon,
  Loader2 as SpinnerIcon,
  Send as SendIcon
} from '@lucide/vue'

const {
  mindmap,
  selectedNodeIds,
  findNode,
  findNodeByText,
  addNode,
  addSiblingNode,
  deleteNode,
  deleteSelectedNodes,
  changeSelectedNodesColor,
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
} = useMindmap()

watch(() => mindmap.value, (newVal) => {
  if (isLoaded.value && newVal) {
    isDirty.value = true
  }
}, { deep: true })

// UI state
const showOutline = ref(true)
const showAiPanel = ref(true)
const attachedCodeContext = ref('')
let activeAbortController = null
const showDetailsPanel = ref(false)
const selectedTemplate = ref('software_design')
const fileInputRef = ref(null)

// Single AI Modal state
const showAiModal = ref(false)
const aiProposalReport = ref('')
const aiProposalActions = ref([])

// Import Code Modal state
const showImportCodeModal = ref(false)
const importCodeText = ref('')
const importFormat = ref('auto')

// Node Properties Modal state
const showNodePropertiesModal = ref(false)
const nodeToEdit = ref(null)

// AI Node Details Loading
const aiDetailsLoading = ref(false)

// 13-Stage Multi-stage analysis state
const showMultiStageModal = ref(false)
const multiStageActions = ref([])
const isMultiStageRunning = ref(false)
const isMultiStagePaused = ref(false)
const currentAnalyzingStageIndex = ref(0)
const currentViewedStageIndex = ref(0)
const hasStartedMultiStage = ref(false)
const showProgressSidebar = ref(false)
const stageIsThinking = ref([false, false, false, false, false, false, false, false, false, false, false, false, false])
const stageThoughts = ref(['', '', '', '', '', '', '', '', '', '', '', '', ''])
const stageLogs = ref(['', '', '', '', '', '', '', '', '', '', '', '', ''])
const loadedFilesCache = ref([])
const scannedFilesList = ref([])
const showThoughtsCollapse = ref([false, false, false, false, false, false, false, false, false, false, false, false, false])
const showStagesAccordion = ref([true, true, true, true, true, true, true, true, true, true, true, true, true])
const stageRefinePrompts = ref(['', '', '', '', '', '', '', '', '', '', '', '', ''])
const stageIsRefining = ref([false, false, false, false, false, false, false, false, false, false, false, false, false])

// Pre-flight preferences
const isUserEngineer = ref(true)
const mbtiStyle = ref('INTJ')

const getStoredEndpoint = () => {
  const val = localStorage.getItem('mindmap_ai_endpoint')
  if (!val || val === 'null' || val === 'undefined' || val.trim() === '') {
    return 'http://100.108.52.6:8888'
  }
  return val
}

const getStoredModel = () => {
  const val = localStorage.getItem('mindmap_ai_model')
  if (!val || val === 'null' || val === 'undefined' || val.trim() === '' || val === 'gpt-3.5-turbo') {
    return 'gemma-4-12B-it-Q6_K.gguf'
  }
  return val
}

const apiEndpoint = ref(getStoredEndpoint())
const apiModel = ref(getStoredModel())
const allowAiReadCode = ref(true)

watch(apiEndpoint, (newVal) => {
  localStorage.setItem('mindmap_ai_endpoint', newVal)
})
watch(apiModel, (newVal) => {
  localStorage.setItem('mindmap_ai_model', newVal)
})

watch(selectedProjectUser, () => {
  fetchProjectsForUser()
})

watch(selectedProject, (newVal) => {
  if (newVal) {
    selectProject(newVal)
  }
})

const historyVersions = ref([])
const multiStageStatusMessage = ref('')

// Separate output for each stage (13 stages)
const stageOutputs = ref(['', '', '', '', '', '', '', '', '', '', '', '', ''])

const stagesProgress = ref([
  { id: 0, name: '第 0 層：解讀現有代碼結構與分析意圖', status: 'idle' },
  { id: 1, name: '第一層：看圖說故事 (系統架構意圖與概覽)', status: 'idle' },
  { id: 2, name: '第二層：商業分析與業務價值規劃', status: 'idle' },
  { id: 3, name: '第三層：開發技術棧與語言適配分析 (Laravel, Vue, Tailwind, Python)', status: 'idle' },
  { id: 4, name: '第四層：定位與架構規劃評估 (子功能架構 vs. 整體網站方案對比)', status: 'idle' },
  { id: 5, name: '第五層：技術難點與潛在風險評估', status: 'idle' },
  { id: 6, name: '第六層：技術可行性雷達圖 analysis (Chart.js)', status: 'idle' },
  { id: 7, name: '第七層：技術實作做法設計 (Blade + Vue 掛載與載入機制)', status: 'idle' },
  { id: 8, name: '第八層：模組與核心程式結構 (Model, Migration, Controller, Service & Web.php)', status: 'idle' },
  { id: 9, name: '第九層：資料庫 ER 關聯圖設計 (Mermaid ERD)', status: 'idle' },
  { id: 10, name: '第十層：與其他節點關係與資料流互動分析', status: 'idle' },
  { id: 11, name: '第十一層：自動生成結構設計 Mermaid 流程圖 & 新增建議', status: 'idle' },
  { id: 12, name: '第十二層：產出供 AI Agent 執行的完整開發指令 Prompt (可複製)', status: 'idle' }
])

// Auto expand accordion for stages currently running/analyzing
watch(() => stagesProgress.value, (newVal) => {
  newVal.forEach((stage, idx) => {
    if (stage.status === 'running') {
      showStagesAccordion.value[idx] = true
    }
  })
}, { deep: true })

const mbtiOptions = [
  { value: 'INTJ', label: 'INTJ — 極度邏輯、條理清晰、直指核心' },
  { value: 'INTP', label: 'INTP — 邏輯嚴密、深入推導、理論分析' },
  { value: 'ENTJ', label: 'ENTJ — 領導風範、目標導向、商業可行性強' },
  { value: 'ENTP', label: 'ENTP — 辯才無礙、多角度思考、方案對比' },
  { value: 'INFJ', label: 'INFJ — 理想主義、宏觀洞察、條理分明' },
  { value: 'INFP', label: 'INFP — 熱情溫暖、概念思考、層次描述' },
  { value: 'ENFJ', label: 'ENFJ — 激勵人心、注重團隊協調、多用比喻' },
  { value: 'ENFP', label: 'ENFP — 創意無限、聯想豐富、活潑描述' },
  { value: 'ISTJ', label: 'ISTJ — 腳踏實地、注重實作與精確細節' },
  { value: 'ISFJ', label: 'ISFJ — 溫柔守護、詳盡步驟、結構完整' },
  { value: 'ESTJ', label: 'ESTJ — 秩序井然、流程化、系統效率導向' },
  { value: 'ESFJ', label: 'ESFJ — 熱心合作、多角度規劃、易於理解' },
  { value: 'ISTP', label: 'ISTP — 實驗精神、實作導向、簡明扼要' },
  { value: 'ISFP', label: 'ISFP — 藝術美感、隨和直觀、結構靈活' },
  { value: 'ESTP', label: 'ESTP — 行動敏捷、實戰經驗、效益優先' },
  { value: 'ESFP', label: 'ESFP — 熱情享樂、活潑生動、結合實務' }
]

// Dynamic CDN libraries state
const katexInstance = ref(null)
const mermaidInstance = ref(null)
const chartJsInstance = ref(null)

// Computed node references
const selectedNode = computed(() => {
  if (!selectedNodeIds.value || selectedNodeIds.value.length === 0) return null
  return findNode(selectedNodeIds.value[0])
})

onMounted(() => {
  if (props.mindmap && props.mindmap.data) {
    mindmap.value = props.mindmap.data
    selectedNodeIds.value = [mindmap.value.id]
    if (props.mindmap.ai_history) {
      if (props.mindmap.ai_history.historyVersions) {
        historyVersions.value = props.mindmap.ai_history.historyVersions
      }
      if (props.mindmap.ai_history.stageOutputs) {
        stageOutputs.value = props.mindmap.ai_history.stageOutputs
      }
      if (props.mindmap.ai_history.stageLogs) {
        stageLogs.value = props.mindmap.ai_history.stageLogs
      }
      if (props.mindmap.ai_history.stagesProgress) {
        let loaded = props.mindmap.ai_history.stagesProgress
        if (loaded.length === 12 && loaded[0] && loaded[0].name.includes('第一層')) {
          loaded.unshift({ id: 0, name: '第 0 層：解讀現有代碼結構與分析意圖', status: 'success' })
          loaded.forEach((s, idx) => s.id = idx)
          if (stageOutputs.value.length === 12) {
            stageOutputs.value.unshift('已於系統升級前跳過該專案掃描檢索。')
          }
          if (stageLogs.value.length === 12) {
            stageLogs.value.unshift('已於系統升級前跳過該專案預檢日誌。')
          }
        }
        const names = [
          '第 0 層：解讀現有代碼結構與分析意圖',
          '第一層：看圖說故事 (系統架構意圖與概覽)',
          '第二層：商業分析與業務價值規劃',
          '第三層：開發技術棧與語言適配分析 (Laravel, Vue, Tailwind, Python)',
          '第四層：定位與架構規劃評估 (子功能架構 vs. 整體網站方案對比)',
          '第五層：技術難點與潛在風險評估',
          '第六層：技術可行性雷達圖 analysis (Chart.js)',
          '第七層：技術實作做法設計 (Blade + Vue 掛載與載入機制)',
          '第八層：模組與核心程式結構 (Model, Migration, Controller, Service & Web.php)',
          '第九層：資料庫 ER 關聯圖設計 (Mermaid ERD)',
          '第十層：與其他節點關係與資料流互動分析',
          '第十一層：自動生成結構設計 Mermaid 流程圖 & 新增建議',
          '第十二層：產出供 AI Agent 執行的完整開發指令 Prompt (可複製)'
        ]
        loaded.forEach((s, idx) => {
          if (names[idx]) {
            s.name = names[idx]
          }
        })
        stagesProgress.value = loaded
        const hasActiveProgress = loaded.some(s => s.status === 'success' || s.status === 'running')
        if (hasActiveProgress) {
          hasStartedMultiStage.value = true
        }
      }
    }
  } else {
    loadFromSession()
  }
  
  // Load dynamic scripts for Math, Diagrams, ChartJS, and Bootstrap CSS
  loadKatex().then(k => { katexInstance.value = k })
  loadMermaid().then(m => { mermaidInstance.value = m })
  loadChartJs().then(c => { chartJsInstance.value = c })
  loadBootstrapCss()

  // Load project users list and default project in the background for instant AI Panel access
  window.axios.get('/api/projects/users')
    .then(res => {
      if (res.data.success) {
        projectUsers.value = res.data.users
      }
    })
    .catch(err => console.error('取得使用者清單失敗：', err))
  fetchProjectsForUser()

  window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
      e.preventDefault()
      undo()
    }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') {
      e.preventDefault()
      redo()
    }
    if (e.key === 'Delete' || e.key === 'Backspace') {
      if (
        document.activeElement.tagName !== 'INPUT' && 
        document.activeElement.tagName !== 'TEXTAREA' &&
        !document.activeElement.isContentEditable
      ) {
        if (selectedNodeIds.value.length > 1) {
          e.preventDefault()
          if (confirm(`確定要刪除選取的 ${selectedNodeIds.value.length} 個節點嗎？`)) {
            deleteSelectedNodes()
          }
        } else if (selectedNodeIds.value.length === 1 && selectedNodeIds.value[0] !== 'root') {
          e.preventDefault()
          deleteNode(selectedNodeIds.value[0])
        }
      }
    }
  })

  // Set up auto-save inactivity listeners
  const activityEvents = ['mousemove', 'mousedown', 'keypress', 'wheel', 'touchstart']
  activityEvents.forEach(evt => {
    window.addEventListener(evt, resetIdleTimer)
  })
  resetIdleTimer()

  nextTick(() => {
    isLoaded.value = true
    isDirty.value = false
  })
})

onUnmounted(() => {
  const activityEvents = ['mousemove', 'mousedown', 'keypress', 'wheel', 'touchstart']
  activityEvents.forEach(evt => {
    window.removeEventListener(evt, resetIdleTimer)
  })
  if (idleTimer) clearTimeout(idleTimer)
})

// Dynamic loaders
const loadKatex = () => {
  if (window.katex) return Promise.resolve(window.katex)
  return new Promise((resolve) => {
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = 'https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css'
    document.head.appendChild(link)

    const script = document.createElement('script')
    script.src = 'https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.js'
    script.onload = () => resolve(window.katex)
    document.head.appendChild(script)
  })
}

const loadMermaid = () => {
  if (window.mermaid) return Promise.resolve(window.mermaid)
  return new Promise((resolve) => {
    const script = document.createElement('script')
    script.src = 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js'
    script.onload = () => {
      window.mermaid.initialize({ startOnLoad: false, theme: 'neutral', securityLevel: 'loose' })
      resolve(window.mermaid)
    }
    document.head.appendChild(script)
  })
}

const loadChartJs = () => {
  if (window.Chart) return Promise.resolve(window.Chart)
  return new Promise((resolve) => {
    const script = document.createElement('script')
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js'
    script.onload = () => resolve(window.Chart)
    document.head.appendChild(script)
  })
}

const loadBootstrapCss = () => {
  const link = document.createElement('link')
  link.rel = 'stylesheet'
  link.href = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'
  document.head.appendChild(link)
}

// Helper to parse Markdown, render KaTeX, and build gorgeous terminal code boxes
const parseAndRenderContent = (text) => {
  if (!text) return { mermaidCode: '', parsedText: '', radarScores: null }

  let mermaidCode = ''
  const mermaidMatch = text.match(/```mermaid\s*([\s\S]*?)\s*```/i)
  if (mermaidMatch) {
    mermaidCode = mermaidMatch[1]
  }

  // Parse Radar Chart JSON parameters out of the text
  let radarScores = null
  const scoreMatch = text.match(/feasibility_scores\s*:\s*(\{[\s\S]*?\})/i) || text.match(/"feasibility_scores"\s*:\s*(\{[\s\S]*?\})/i)
  if (scoreMatch) {
    try {
      const cleanJson = scoreMatch[1].replace(/'/g, '"').replace(/,\s*}/g, '}')
      radarScores = JSON.parse(cleanJson)
    } catch (e) {
      try {
        radarScores = (new Function(`return ${scoreMatch[1]}`))()
      } catch (err) {}
    }
  } else {
    const jsonMatch = text.match(/```json\s*([\s\S]*?)\s*```/i)
    if (jsonMatch) {
      try {
        const parsed = JSON.parse(jsonMatch[1])
        if (parsed.feasibility_scores) {
          radarScores = parsed.feasibility_scores
        }
      } catch(e) {}
    }
  }

  let parsedText = text

  // Strip structural JSON blocks and Mermaid source code blocks from visible text rendering
  parsedText = parsedText.replace(/```json\s*[\s\S]*?\s*```/gi, '')
  parsedText = parsedText.replace(/```mermaid\s*[\s\S]*?\s*```/gi, '')

  // 1. Convert Markdown code blocks into beautifully styled code components
  parsedText = parsedText.replace(/```(php|javascript|js|json|css|sql|bash|html|xml)\s*([\s\S]*?)\s*```/gi, (match, lang, code) => {
    const escapedCode = code.trim().replace(/</g, '&lt;').replace(/>/g, '&gt;')
    return `
      <div class="my-3 border border-neutral-700/30 rounded-lg overflow-hidden shadow-sm">
        <div class="bg-neutral-800 text-neutral-400 text-[10px] px-3 py-1 font-mono flex items-center justify-between border-b border-neutral-700 select-none">
          <span>${lang.toUpperCase()} CODE</span>
        </div>
        <pre class="bg-neutral-900 text-emerald-400 p-3 overflow-x-auto font-mono text-[11px] leading-relaxed select-text m-0"><code>${escapedCode}</code></pre>
      </div>
    `
  })

  // 2. Parse inline math ($...$) and block math ($$...$$) using KaTeX
  if (katexInstance.value) {
    // Block math
    parsedText = parsedText.replace(/\$\$\s*([\s\S]*?)\s*\$\$/g, (match, formula) => {
      try {
        return `<div class="my-3 flex justify-center">${katexInstance.value.renderToString(formula, { displayMode: true, throwOnError: false })}</div>`
      } catch (e) {
        return match
      }
    })
    // Inline math
    parsedText = parsedText.replace(/\$\s*([^\n$]+?)\s*\$/g, (match, formula) => {
      try {
        return katexInstance.value.renderToString(formula, { displayMode: false, throwOnError: false })
      } catch (e) {
        return match
      }
    })
  }

  return { mermaidCode, parsedText, radarScores }
}

// Extract specific Rubric score and reason out of stage outputs dynamically
const parsedStageScores = computed(() => {
  return stageOutputs.value.map((text) => {
    if (!text) return { score: 0, reason: '' }
    
    let score = 0
    let reason = ''
    
    const blocks = [...text.matchAll(/```json\s*([\s\S]*?)\s*```/g)]
    if (blocks.length > 0) {
      try {
        const parsed = JSON.parse(blocks[blocks.length - 1][1])
        if (parsed.stage_score !== undefined) score = parseInt(parsed.stage_score, 10)
        if (parsed.stage_rubric_reason) reason = parsed.stage_rubric_reason
      } catch (e) {
        try {
          const looseParsed = (new Function(`return ${blocks[blocks.length - 1][1]}`))()
          if (looseParsed.stage_score !== undefined) score = parseInt(looseParsed.stage_score, 10)
          if (looseParsed.stage_rubric_reason) reason = looseParsed.stage_rubric_reason
        } catch(err) {}
      }
    }
    
    if (!score) {
      const scoreMatch = text.match(/stage_score\s*:\s*(\d+)/i) || text.match(/"stage_score"\s*:\s*(\d+)/i)
      if (scoreMatch) {
        score = parseInt(scoreMatch[1], 10)
      }
    }
    
    if (!reason) {
      const reasonMatch = text.match(/stage_rubric_reason\s*:\s*"([\s\S]*?)"/i) || text.match(/"stage_rubric_reason"\s*:\s*"([\s\S]*?)"/i)
      if (reasonMatch) {
        reason = reasonMatch[1]
      }
    }
    
    return { score: score || 0, reason: reason || '' }
  })
})

const grandTotalScore = computed(() => {
  // Sum up scores of first 11 stages (grand total out of 110 points)
  return parsedStageScores.value.slice(0, 11).reduce((sum, item) => sum + item.score, 0)
})

const copyToClipboard = (text) => {
  navigator.clipboard.writeText(text).then(() => {
    alert('📋 開發指令 Prompt 已成功複製到您的剪貼簿！您可以直接貼給其他 AI Agent / 程式編碼助手使用。')
  }).catch(err => {
    alert('複製失敗，請手動選取文字進行複製。')
  })
}

const scrollToStage = (id) => {
  const idx = id - 1
  // Auto-expand this specific stage card's accordion
  showStagesAccordion.value[idx] = true
  
  // Smooth scroll
  nextTick(() => {
    const el = document.getElementById(`analysis-stage-card-${id}`)
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  })
}

// Inline component definitions
// Extracted local component definitions

const onTemplateChange = (e) => {
  loadTemplate(e.target.value)
}

const triggerImport = () => {
  if (fileInputRef.value) {
    fileInputRef.value.click()
  }
}

const handleFileImport = (e) => {
  const file = e.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = (event) => {
    const success = importFromJson(event.target.result)
    if (!success) {
      alert('無效的 JSON 格式。請檢查您所匯入的檔案。')
    }
  }
  reader.readAsText(file)
}

const handleExport = () => {
  const jsonStr = exportToJson()
  const blob = new Blob([jsonStr], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  
  const link = document.createElement('a')
  link.href = url
  link.download = `${mindmap.value.text.replace(/\s+/g, '_')}_設計文件.json`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

const handleImportCode = () => {
  try {
    let parsedTree = null
    const text = importCodeText.value.trim()
    if (!text) {
      alert('請先貼入原始碼。')
      return
    }

    let format = importFormat.value
    if (format === 'auto') {
      if (text.startsWith('{') || text.startsWith('[') || text.startsWith('`')) {
        format = 'json'
      } else {
        format = 'mermaid'
      }
    }

    if (format === 'json') {
      parsedTree = parseRawJson(text)
    } else {
      parsedTree = parseMermaidToTree(text)
    }

    if (parsedTree && parsedTree.id && parsedTree.text) {
      mindmap.value = parsedTree
      selectedNodeIds.value = [parsedTree.id]
      sessionStorage.setItem('minimalist_mindmap_data', JSON.stringify(parsedTree))
      showImportCodeModal.value = false
      importCodeText.value = ''
      alert('原始碼匯入成功！')
    } else {
      alert('匯入失敗，無法解析為合法的節點大綱。')
    }
  } catch (e) {
    alert('解析錯誤: ' + e.message)
  }
}

const handleAddChildren = (parentId, childTexts) => {
  const parent = findNode(parentId)
  if (!parent) return
  
  childTexts.forEach(text => {
    const newNode = addNode(parentId)
    if (newNode) {
      newNode.text = text
    }
  })
}

const openNodePropertiesModal = (nodeId) => {
  const node = findNode(nodeId)
  if (node) {
    nodeToEdit.value = node
    showNodePropertiesModal.value = true
  }
}

const deleteNodeAndCloseModal = (nodeId) => {
  if (confirm('確定要刪除此節點與其底下的所有子節點嗎？')) {
    deleteNode(nodeId)
    showNodePropertiesModal.value = false
  }
}

const generateNodeDetailsForSelected = async (node) => {
  if (!node) return
  aiDetailsLoading.value = true
  
  const apiEndpoint = localStorage.getItem('mindmap_ai_endpoint') || 'http://100.108.52.6:8888'
  const apiModel = localStorage.getItem('mindmap_ai_model') || 'gpt-3.5-turbo'
  
  const prompt = `請針對當前設計文件的節點主題「${node.text}」，撰寫一份極為詳細的工程實作計劃與需求分析。
請包括：
1. 實作步驟與執行計畫。
2. 開發需求與規格細節。
3. 額外附上一個 Mermaid 流程圖（使用 \`\`\`mermaid 和 \`\`\` 包裹）以視覺化此步驟的執行流程。

重要規定：
請務必只使用「繁體中文」回答，保持專業性、結構化。直接開始輸出，不要有任何前言或客套話。`

  try {
    const res = await fetch(`${apiEndpoint}/v1/chat/completions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: apiModel,
        messages: [
          { role: 'system', content: '你是一位優秀的軟體架構師。請以繁體中文撰寫詳細實作計劃與 Mermaid 流程圖。' },
          { role: 'user', content: prompt }
        ],
        temperature: 0.7,
        stream: true
      })
    })

    if (!res.ok) throw new Error(`API 錯誤: ${res.statusText}`)

    const reader = res.body.getReader()
    const decoder = new TextDecoder("utf-8")
    let finished = false
    let buffer = ''
    
    node.details = ''

    while (!finished) {
      const { value, done } = await reader.read()
      if (done) {
        finished = true
        break
      }
      
      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split('\n')
      buffer = lines.pop()

      for (const line of lines) {
        const trimmed = line.trim()
        if (!trimmed || trimmed === 'data: [DONE]') continue
        if (trimmed.startsWith('data: ')) {
          try {
            const parsed = JSON.parse(trimmed.slice(6))
            const delta = parsed.choices[0]?.delta
            const content = delta?.content || delta?.reasoning_content || delta?.reasoning || ''
            node.details += content
          } catch (e) {}
        }
      }
    }
    
    if (buffer && buffer.startsWith('data: ')) {
      try {
        const parsed = JSON.parse(buffer.slice(6))
        const delta = parsed.choices[0]?.delta
        const content = delta?.content || delta?.reasoning_content || delta?.reasoning || ''
        node.details += content
      } catch (e) {}
    }

  } catch (error) {
    alert('AI 生成設計細節時發生錯誤: ' + error.message)
  } finally {
    aiDetailsLoading.value = false
  }
}

const handleUpdateText = (nodeId, newText) => {
  updateNodeText(nodeId, newText)
}

const refineStageOutput = async (idx) => {
  const userFeedback = stageRefinePrompts.value[idx].trim()
  if (!userFeedback) return

  stageIsRefining.value[idx] = true
  const originalOutput = stageOutputs.value[idx]
  stageOutputs.value[idx] = '' // clear to stream new content
  
  const apiEndpointVal = apiEndpoint.value
  const apiModelVal = apiModel.value
  const fullMindmapJson = JSON.stringify(mindmap.value, null, 2)
  const stageName = stagesProgress.value[idx].name

  const selectedMbtiObj = mbtiOptions.find(o => o.value === mbtiStyle.value)
  const mbtiStyleInstructions = `請採用「${selectedMbtiObj ? selectedMbtiObj.label : mbtiStyle.value}」性格的表達口吻進行全繁體中文輸出。`
  const roleInstruction = isUserEngineer.value
    ? '讀者是「專業軟體工程師」，請務必使用精確的技術專有名詞、程式架構、設計模式進行專業解說。'
    : '讀者是「非技術人員」，請以通俗易懂的平實白話文，搭配生活中的譬喻進行 analysis。'

  let refinePrompt = ''
  if (allowAiReadCode.value && selectedFile.value && selectedFileContent.value) {
    refinePrompt += `[CRITICAL SECURITY BOUNDARY] You are in a strictly READ-ONLY sandbox. You have absolutely no permissions or capability to modify, write, or delete any files in the workspace. Do not attempt to output write commands, payload exploits, or modify the repository. You can only analyze the provided code context and guide the user.\n\n`
    refinePrompt += `==== 當前唯讀專案代碼資料 ====\n`
    refinePrompt += `檔案名稱: ${selectedFile.value.name}\n`
    refinePrompt += `相對路徑: ${selectedFile.value.relative_path}\n`
    refinePrompt += `程式碼內容:\n\`\`\`\n${selectedFileContent.value}\n\`\`\`\n==================================\n\n`
  }

  refinePrompt += `當前整個心智圖設計文件的完整 JSON 結構如下：
\`\`\`json
${fullMindmapJson}
\`\`\`

目前我們正在對【${stageName}】這一層的技術報告進行局部的針對性修改。

該層原本的報告內容如下：
\`\`\`html
${originalOutput}
\`\`\`

使用者提出的修改意見與指示如下：
「${userFeedback}」

任務指示：
請依據使用者的修改意見，重新撰寫或優化這部分報告。
重要規定與表達風格：
- ${mbtiStyleInstructions}
- ${roleInstruction}
- 請「務必保留原有的 HTML 與 Bootstrap 5 樣式類別（例如 <div class="card mb-3">、<span class="badge"> 等）進行格式化排版輸出」，不要輸出純 Markdown 格式段落。
- 若本層有涉及評分（Rubric JSON），請在回答的結尾同樣輸出一個獨立的 JSON 區塊（使用 \`\`\`json 和 \`\`\` 包裹）以更新分數：
\`\`\`json
{
  "stage_score": 新分數,
  "stage_rubric_reason": "說明修改後的分數理由"
}
\`\`\`
- 若本層包含雷達圖數據或 Mermaid 流程圖代碼，請務必在輸出的內容中保留或相應修改對應的 \`\`\`json (feasibility_scores) 或 \`\`\`mermaid 代碼塊，以便前端正常渲染。
- 直接開始輸出修改後的完整報告內容，不要有任何客套話與廢話。`

  try {
    const res = await fetch(`${apiEndpointVal}/v1/chat/completions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: apiModelVal,
        messages: [
          { role: 'system', content: '你是一位優秀的資深軟體架構師。請根據使用者的意見修改指定分析層的 HTML 報告。' },
          { role: 'user', content: refinePrompt }
        ],
        temperature: 0.7,
        stream: true
      })
    })

    if (!res.ok) {
      throw new Error(`HTTP 錯誤 ${res.status}: ${res.statusText || '連線失敗'}`)
    }

    const reader = res.body.getReader()
    const decoder = new TextDecoder("utf-8")
    let finished = false
    let buffer = ''

    while (!finished) {
      const { value, done } = await reader.read()
      if (done) {
        finished = true
        break
      }
      
      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split('\n')
      buffer = lines.pop()

      for (const line of lines) {
        const trimmed = line.trim()
        if (!trimmed || trimmed === 'data: [DONE]') continue
        if (trimmed.startsWith('data: ')) {
          try {
            const parsed = JSON.parse(trimmed.slice(6))
            const delta = parsed.choices[0]?.delta
            if (delta?.content) {
              stageOutputs.value[idx] += delta.content
            }
          } catch (e) {}
        }
      }
    }

    if (buffer && buffer.startsWith('data: ')) {
      try {
        const parsed = JSON.parse(buffer.slice(6))
        const delta = parsed.choices[0]?.delta
        if (delta?.content) {
          stageOutputs.value[idx] += delta.content
        }
      } catch (e) {}
    }

    stageRefinePrompts.value[idx] = '' // clear input
  } catch (error) {
    alert(`修改失敗: ${error.message}`)
    stageOutputs.value[idx] = originalOutput // restore
  } finally {
    stageIsRefining.value[idx] = false
  }
}

const handleClear = () => {
  if (confirm('您確定要清除所有子項目嗎？')) {
    deleteNode('root')
  }
}

const handleSelectNode = ({ id, isMulti }) => {
  selectNode(id, isMulti)
  showDetailsPanel.value = true
}

const batchDelete = () => {
  if (confirm(`確定要刪除這 ${selectedNodeIds.value.length} 個節點嗎？`)) {
    deleteSelectedNodes()
  }
}

const batchColorChange = (color) => {
  changeSelectedNodesColor(color)
}

const clearSelection = () => {
  selectedNodeIds.value = ['root']
}

const batchColorSelection = (color) => {
  batchColorChange(color)
}

const isUserEngineerVal = isUserEngineer.value
const mbtiStyleVal = mbtiStyle.value

const archiveCurrentRunToHistory = async () => {
  const hasOutputs = stageOutputs.value.some(out => out && out.trim())
  if (!hasOutputs) return

  const timestamp = new Date().toLocaleString('zh-TW', { hour12: false })
  const newArchive = {
    timestamp,
    mbtiStyle: mbtiStyle.value,
    isUserEngineer: isUserEngineer.value,
    stagesProgress: JSON.parse(JSON.stringify(stagesProgress.value)),
    stageOutputs: JSON.parse(JSON.stringify(stageOutputs.value)),
    stageLogs: JSON.parse(JSON.stringify(stageLogs.value)),
    stageThoughts: JSON.parse(JSON.stringify(stageThoughts.value))
  }
  
  historyVersions.value.unshift(newArchive)
  await saveAiHistoryProgress()
}

const loadHistoryVersion = (version) => {
  stagesProgress.value = JSON.parse(JSON.stringify(version.stagesProgress))
  stageOutputs.value = JSON.parse(JSON.stringify(version.stageOutputs))
  stageLogs.value = JSON.parse(JSON.stringify(version.stageLogs))
  stageThoughts.value = JSON.parse(JSON.stringify(version.stageThoughts))
  mbtiStyle.value = version.mbtiStyle || 'INTJ'
  isUserEngineer.value = typeof version.isUserEngineer === 'boolean' ? version.isUserEngineer : true
  hasStartedMultiStage.value = true
  isMultiStageRunning.value = false
  isMultiStagePaused.value = false
  currentViewedStageIndex.value = 0
}

const deleteHistoryVersion = (index) => {
  historyVersions.value.splice(index, 1)
  saveAiHistoryProgress()
}

const triggerMultiStageAnalysisStart = async () => {
  if (isMultiStagePaused.value) {
    isMultiStagePaused.value = false
    runMultiStageAnalysis(currentAnalyzingStageIndex.value)
  } else {
    await archiveCurrentRunToHistory()
    stageOutputs.value = ['', '', '', '', '', '', '', '', '', '', '', '', '']
    stagesProgress.value.forEach(s => s.status = 'idle')
    multiStageActions.value = []
    currentAnalyzingStageIndex.value = 0
    isMultiStagePaused.value = false
    runMultiStageAnalysis(0)
  }
}

const pauseMultiStageAnalysis = () => {
  isMultiStagePaused.value = true
  isMultiStageRunning.value = false
  multiStageStatusMessage.value = '⏸️ 分析已暫停，您可以隨時點擊「繼續」重啟。'
  if (activeAbortController) {
    activeAbortController.abort()
    activeAbortController = null
  }
  if (stagesProgress.value[currentAnalyzingStageIndex.value]) {
    stagesProgress.value[currentAnalyzingStageIndex.value].status = 'idle'
    stageIsThinking.value[currentAnalyzingStageIndex.value] = false
  }
  saveAiHistoryProgress()
}

const generatePrintableReport = () => {
  const printWindow = window.open('', '_blank')
  if (!printWindow) {
    alert('無法開啟列印視窗，請檢查您的瀏覽器是否阻擋了彈出視窗。')
    return
  }

  const mindmapTitle = mindmap.value?.text || '系統架構設計藍圖'
  const activeNodeText = selectedNode.value?.text || '選定模組'
  
  let reportsHtml = ''
  stagesProgress.value.forEach((stage, idx) => {
    const outputText = stageOutputs.value[idx] || ''
    if (outputText) {
      const rendered = parseAndRenderContent(outputText).parsedText
      reportsHtml += `
        <div class="report-card ${idx > 0 ? 'page-break' : ''}">
          <h2 class="section-title">
            <span class="badge bg-purple-600 me-2 text-white px-2 py-1 rounded" style="background-color: #6f42c1 !important;">${idx}</span>
            ${stage.name}
          </h2>
          <div class="report-content">
            ${rendered}
          </div>
        </div>
      `
    }
  })

  const docContent = `
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8">
      <title>【列印報告】${mindmapTitle} - ${activeNodeText} 技術架構分析</title>
      <!-- Load Bootstrap for rich layouts -->
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <!-- Load KaTeX CSS for equations -->
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.8/dist/katex.min.css">
      <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
      <style>
        body {
          font-family: 'Inter', 'Noto Sans TC', sans-serif;
          background-color: #f8f9fa;
          color: #2d3748;
          padding: 40px 20px;
          line-height: 1.6;
        }
        .container {
          max-width: 900px;
        }
        .header-box {
          border-bottom: 2px solid #6f42c1;
          padding-bottom: 20px;
          margin-bottom: 40px;
        }
        .report-card {
          background: #fff;
          border: 1px solid #e2e8f0;
          border-radius: 12px;
          padding: 30px;
          margin-bottom: 30px;
          box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .section-title {
          font-size: 1.25rem;
          font-weight: 700;
          color: #4a5568;
          border-bottom: 1px solid #edf2f7;
          padding-bottom: 12px;
          margin-bottom: 20px;
          display: flex;
          align-items: center;
        }
        .report-content {
          font-size: 14px;
        }
        .report-content pre {
          background: #1a202c !important;
          color: #48bb78 !important;
          padding: 15px !important;
          border-radius: 8px !important;
          font-family: Menlo, Monaco, Consolas, "Courier New", monospace !important;
          font-size: 12px !important;
          margin: 15px 0 !important;
        }
        .no-print-bar {
          background: #ffffff;
          border: 1px solid #e2e8f0;
          padding: 15px 24px;
          border-radius: 12px;
          margin-bottom: 30px;
          display: flex;
          justify-content: space-between;
          align-items: center;
          box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .btn-purple {
          background-color: #6f42c1 !important;
          border-color: #6f42c1 !important;
        }
        .btn-purple:hover {
          background-color: #5a32a3 !important;
          border-color: #5a32a3 !important;
        }
        @media print {
          body {
            background-color: #fff;
            padding: 0;
          }
          .no-print-bar, .no-print {
            display: none !important;
          }
          .report-card {
            border: none;
            box-shadow: none;
            padding: 0;
            margin-bottom: 40px;
          }
          .page-break {
            page-break-before: always;
            break-before: page;
          }
        }
      </style>
    </head>
    <body>
      <div class="container">
        <!-- Floating no-print control bar -->
        <div class="no-print-bar">
          <div>
            <h5 class="m-0 font-weight-bold text-dark">🖨️ 系統架構報告列印預覽</h5>
            <small class="text-muted">建議選擇「另存為 PDF」或直接列印</small>
          </div>
          <button onclick="window.print()" class="btn btn-purple text-white px-4 py-2 font-weight-bold">
            ⚡ 立即啟動列印 / 匯出 PDF
          </button>
        </div>

        <div class="header-box">
          <h1 class="h3 font-weight-bold text-dark">${mindmapTitle}</h1>
          <div class="text-muted text-xs mt-2 d-flex gap-4">
            <span><strong>🎯 目標節點：</strong> ${activeNodeText}</span>
            <span><strong>📅 產生時間：</strong> ${new Date().toLocaleString()}</span>
          </div>
        </div>

        <div class="reports-container">
          ${reportsHtml || '<div class="alert alert-warning text-center">目前尚無已完成的架構分析報告。</div>'}
        </div>
      </div>
    </body>
    </html>
  `
  printWindow.document.write(docContent)
  printWindow.document.close()
}

const applyProposals = () => {
  applySelectedProposals()
}

const applyMultiProposals = () => {
  applyMultiStageProposals()
}

const toggleAllMultiProposalsVal = (val) => {
  toggleAllMultiProposals(val)
}

// AI Proposals handlers
const onAiProposals = ({ report, actions }) => {
  aiProposalReport.value = report
  aiProposalActions.value = actions.map(act => ({
    id: 'act-' + Math.random().toString(36).substr(2, 9),
    type: act.type,
    target: act.target,
    text: act.text || '',
    selected: true
  }))
  showAiModal.value = true
}

const applySelectedProposals = () => {
  const selectedProposals = aiProposalActions.value.filter(p => p.selected)
  if (selectedProposals.length === 0) {
    showAiModal.value = false
    return
  }

  selectedProposals.forEach(proposal => {
    let targetNode = findNodeByText(proposal.target)
    if (!targetNode && selectedNode.value) {
      targetNode = selectedNode.value
    }
    
    if (proposal.type === 'add' && targetNode) {
      const newNode = addNode(targetNode.id)
      if (newNode) {
        newNode.text = proposal.text
      }
    } else if (proposal.type === 'delete') {
      if (targetNode) {
        deleteNode(targetNode.id)
      }
    } else if (proposal.type === 'update') {
      if (targetNode) {
        updateNodeText(targetNode.id, proposal.text)
      }
    }
  })

  showAiModal.value = false
  selectNode('root')
}

// Deep AI node details & Mermaid generation
const generateNodeDetails = async () => {
  if (!selectedNode.value) return
  
  aiDetailsLoading.value = true
  const nodeText = selectedNode.value.text
  
  const apiEndpoint = localStorage.getItem('mindmap_ai_endpoint') || 'http://100.108.52.6:8888'
  const apiModel = localStorage.getItem('mindmap_ai_model') || 'gpt-3.5-turbo'
  
  const prompt = `請針對當前設計文件的節點主題「${nodeText}」，撰寫一份極為詳細的工程實作計劃與需求分析。
請包括：
1. 實作步驟與執行計畫。
2. 開發需求與規格細節。
3. 額外附上一個 Mermaid 流程圖（使用 \`\`\`mermaid 和 \`\`\` 包裹）以視覺化此步驟的執行流程。

重要規定：
請務必只使用「繁體中文」回答，保持專業性、結構化。直接開始輸出，不要有任何前言或客套話。`

  try {
    const res = await fetch(`${apiEndpoint}/v1/chat/completions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: apiModel,
        messages: [
          { role: 'system', content: '你是一位優秀的軟體架構師。請以繁體中文撰寫詳細實作計劃與 Mermaid 流程圖。' },
          { role: 'user', content: prompt }
        ],
        temperature: 0.7,
        stream: true
      })
    })

    if (!res.ok) throw new Error(`API 錯誤: ${res.statusText}`)

    const reader = res.body.getReader()
    const decoder = new TextDecoder("utf-8")
    let finished = false
    let buffer = ''
    
    updateNodeDetails(selectedNode.value.id, '')

    while (!finished) {
      const { value, done } = await reader.read()
      if (done) {
        finished = true
        break
      }
      
      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split('\n')
      buffer = lines.pop()

      for (const line of lines) {
        const trimmed = line.trim()
        if (!trimmed || trimmed === 'data: [DONE]') continue
        if (trimmed.startsWith('data: ')) {
          try {
            const parsed = JSON.parse(trimmed.slice(6))
            const delta = parsed.choices[0]?.delta
            const content = delta?.content || delta?.reasoning_content || delta?.reasoning || ''
            const currentDetails = selectedNode.value.details || ''
            updateNodeDetails(selectedNode.value.id, currentDetails + content)
          } catch (e) {}
        }
      }
    }
    
    if (buffer && buffer.startsWith('data: ')) {
      try {
        const parsed = JSON.parse(buffer.slice(6))
        const delta = parsed.choices[0]?.delta
        const content = delta?.content || delta?.reasoning_content || delta?.reasoning || ''
        const currentDetails = selectedNode.value.details || ''
        updateNodeDetails(selectedNode.value.id, currentDetails + content)
      } catch (e) {}
    }

  } catch (error) {
    alert('AI 生成設計細節時發生錯誤: ' + error.message)
  } finally {
    aiDetailsLoading.value = false
  }
}

// Trigger multi-stage modal setup
const triggerMultiStageSetup = () => {
  if (!selectedNode.value) return
  showMultiStageModal.value = true
  hasStartedMultiStage.value = false
  isMultiStageRunning.value = false
  isMultiStagePaused.value = false
  currentAnalyzingStageIndex.value = 0
  stageOutputs.value = ['', '', '', '', '', '', '', '', '', '', '', '', '']
  stageLogs.value = ['', '', '', '', '', '', '', '', '', '', '', '', '']
  loadedFilesCache.value = []
  scannedFilesList.value = []
  multiStageActions.value = []
  stagesProgress.value.forEach(s => s.status = 'idle')
  stageIsThinking.value = [false, false, false, false, false, false, false, false, false, false, false, false, false]
  stageThoughts.value = ['', '', '', '', '', '', '', '', '', '', '', '', '']
  showThoughtsCollapse.value = [false, false, false, false, false, false, false, false, false, false, false, false, false]
  showStagesAccordion.value = [true, true, true, true, true, true, true, true, true, true, true, true, true]
}

const saveAiHistoryProgress = async () => {
  try {
    await window.axios.post('/mindmaps', {
      id: props.mindmap.id,
      title: mindmap.value?.text || '未命名心智圖',
      folder: props.mindmap.folder || '網站',
      data: mindmap.value,
      ai_history: {
        stageOutputs: stageOutputs.value,
        stagesProgress: stagesProgress.value,
        stageLogs: stageLogs.value,
        historyVersions: historyVersions.value
      }
    })
  } catch (e) {
    console.error('自動備份 AI 歷史進度失敗：', e)
  }
}

// 13-Stage Sequential progressive AI reasoning pipeline
const runMultiStageAnalysis = async (startFromIdx = 0) => {
  hasStartedMultiStage.value = true
  isMultiStageRunning.value = true
  isMultiStagePaused.value = false
  
  if (startFromIdx === 0) {
    attachedCodeContext.value = ''
  }
  
  const apiEndpointVal = apiEndpoint.value
  const apiModelVal = apiModel.value
  const nodeText = selectedNode.value.text
  const fullMindmapJson = JSON.stringify(mindmap.value, null, 2)

  const selectedMbtiObj = mbtiOptions.find(o => o.value === mbtiStyle.value)
  const mbtiStyleInstructions = `請採用「${selectedMbtiObj ? selectedMbtiObj.label : mbtiStyle.value}」性格的表達口吻進行全繁體中文輸出。`

  const roleInstruction = isUserEngineer.value
    ? '讀者是「專業軟體工程師」，請務必使用精確的技術專有名詞、程式架構、設計模式進行專業解說。'
    : '讀者是「非技術人員」，請以通俗易懂的平實白話文，搭配生活中的譬喻進行 analysis。'

  const customStylePrompt = `
- ${mbtiStyleInstructions}
- ${roleInstruction}
- 在分析架構時，請推薦 2-3 個可以直接安裝使用的開源/成熟套件（如 Composer 套件或 npm 套件）。
- 所有輸出的 Bootstrap HTML 元件排版必須符合響應式設計 (RWD)，例如使用網格系統時必須結合行動端與電腦端類別 (如 col-12 col-md-6) 以及響應式間距 (如 p-3 p-md-4)，確保在各種螢幕尺寸下皆能完美呈現。
- 重要評估分數規定：在本次回答的結尾，請「務必額外輸出一個獨立的 JSON 區塊（使用 \`\`\`json 和 \`\`\` 包裹）」，給予本層分析一個 0-10 分的可行性評估分數（本層總分為 10 分），並給予對應的文字評分理由。格式如下（請確保 JSON 格式完全正確，stage_score 必須為整數）：
\`\`\`json
{
  "stage_score": 8,
  "stage_rubric_reason": "寫下對應此分數的文字分析與理由..."
}
\`\`\`
- 重要輸出規定：請「直接使用 HTML 與 Bootstrap 5 樣式類別（例如 <div class="card mb-3"><div class="card-body">...</div></div>、<span class="badge bg-purple">...</span>、<div class="alert alert-info">...</div>、以及 <ul class="list-group">...</ul> 等）」來結構化、排版您的報告內容，不要輸出純 Markdown 格式段落。這能讓前端的 v-html 直接渲染出排版美觀的卡片、標籤、清單與警示。請保持程式碼結構合法、標籤正確閉合。
`

  // 13 Stage Prompts Config
  const stagePrompts = [
    `【第 0 層：解讀現有代碼結構與分析意圖】
請檢視分析目前已選定或上傳的專案目錄結構樹及代碼。
請簡單向使用者說明：
1. 該專案對應的代碼目錄結構與核心檔案分佈（如果是新功能，指出可以插入適配的檔案路徑）。
2. 目前檢索到現有的設計概念與商業邏輯架構。
請以此作為整份深度分析報告的起點與奠基導言。`,

    `【第 1 層：看圖說故事 (系統架構意圖與概覽)】
請依據當前設計文件的完整心智圖結構（JSON）以及當前選取的節點「${nodeText}」，分析本節點在整個系統架構中的核心定位、意圖與系統整體概覽。`,
    
    `【第 2 層：商業分析與業務價值規劃】
請為此節點「${nodeText}」進行「商業分析（Business Analysis）」。請從商業變現路徑、目標客群、潛在業務價值、以及為使用者帶來的關鍵商業價值等維度，撰寫一份精簡的商業分析報告。`,

    `【第 3 層：開發技術棧與語言適配分析】
請針對當前節點「${nodeText}」評估開發所需要的「語言與技術棧（Language & Stack）」。分析現有的 Laravel、Vue.js、Tailwind CSS 是否能滿足所有功能需求，並判斷是否必須引入 Python（例如做機器學習、大數據處理、爬蟲）或其他後端語言。
其中，在資料庫選型部分，請評估 2-3 種主流資料庫（如 MySQL, PostgreSQL, MongoDB, Redis 等）之優缺點，給出明確的比分對照，並基於評分推薦最適合 the 資料庫選項。
重要視覺规定：請在報告中使用漂亮的 Bootstrap 卡片與網格展示這些技術棧的 Logo 圖標（寬度設定在 40px-50px，水平並排或卡片標題旁邊）。請使用以下高品質公用 SVG URL 作為圖片 src 渲染：
- Laravel: https://raw.githubusercontent.com/devicons/devicon/master/icons/laravel/laravel-original.svg
- Vue.js: https://raw.githubusercontent.com/devicons/devicon/master/icons/vuejs/vuejs-original.svg
- Tailwind CSS: https://raw.githubusercontent.com/devicons/devicon/master/icons/tailwindcss/tailwindcss-original.svg
- Python: https://raw.githubusercontent.com/devicons/devicon/master/icons/python/python-original.svg`,

    `【第 4 層：定位與架構規劃評估 (子功能架構 vs. 整體網站方案對比)】
請判斷當前選取節點「${nodeText}」是屬於「子功能/子模組 (小局觀)」還是「整體網站/大系統 (大局觀)」：
重要判斷提示：請注意！即使當前選取的節點在心智圖結構中層級很高，它也可能只是使用者剛剛臨時加入的一個「局部微觀功能 (小局觀)」。請依據該節點文字的實際「語意」進行智慧分析（例如：「登入功能」、「購物車結帳」代表小功能；「企業內部 ERP 系統」、「電商平台」代表大系統），切勿只看樹狀層級深淺。
1. 如果是「子功能/子模組 (小局觀)」：請注重其與主系統之完整性與呼應性，詳細分析此功能所需要的 Model、Relationship (關聯模型設計)、Migration、Controller、Web.php (路由與路徑規劃)、Service Layer 服務層架構問題。
2. 如果是「整體網站/大系統 (大局觀)」：請詳細評估並對比以下三種主流開發方案之利弊：
   A. Blade 為核心，Vue 局部嵌入掛載 (SEO 友善)
   B. Laravel Breeze 並用 Inertia.js 嵌入 (單頁一頁式網站 SPA 友善)
   C. 完全前後端分離 (Backend API + Frontend SPA)`,
    
    `【第 5 層：技術難點與潛在風險評估】
請為選取的主題節點「${nodeText}」進行「技術難點評估」。分析在採用 Laravel + Vue 架構時，可能會碰到的技術瓶頸、效能挑戰（例如資料庫查詢開銷、前端元件渲染過載）、並給出對應的預防與解決方案。`,

    `【第 6 層：技術可行性雷達圖分析 (Chart.js)】
請對此技術方案的「可行性」進行 0-100 的量化評分。
評分規則：方案越接近使用 Laravel + Vue 混合架構則分數越高；若需要引進 Python 或其他異質大架構，則降低分數。
請詳細評估以下 5 個面向，並在報告末尾必須以如下 JSON 格式輸出評分結果（請確保 JSON 格式完全正確），以便前端 Chart.js 解析並繪製雷達圖：
\`\`\`json
{
  "feasibility_scores": {
    "laravel_integration": 95,
    "vue_compatibility": 90,
    "database_performance": 85,
    "maintenance_ease": 90,
    "development_speed": 95
  }
}
\`\`\``,

    `【第 7 層：技術實作做法設計 (Blade + Vue 掛載與載入機制)】
接著，請規劃實作細節。說明如何在 Laravel Controller 中處理請求、如何利用 Blade 模板將 initial data 以 JSON 安全傳遞給 Vue 元件屬性，以及 Vue 元件如何與 Blade 共存掛載。`,
    
    `【第 8 層：模組與核心程式結構 (Model, Migration, Controller, Service & Web.php)】
接著，請為此混合模組設計具體的功能程式結構、模型規劃（Model 與 Relationships）、Migration 資料庫遷移規劃、Controller 與 Service Layer 業務分離邏輯，以及 Web.php 具體路由寫法。
注意：資料庫部分請使用先前理性分析比分推薦的資料庫，並提供具體的 Migration 與表欄位結構。`,

    `【第 9 層：資料庫 ER 關聯圖設計 (Mermaid ERD)】
請針對先前比分推薦的資料庫類型，設計對應的資料庫綱要與關聯表結構。
請產出一個可直接渲染的 Mermaid ER 圖（Entity Relationship Diagram，使用 \`\`\`mermaid 和 \`\`\` 包裹），必須在圖中清楚標記主鍵 PK、外鍵 FK、以及欄位名稱與屬性類型。
特別規定：在線條上必須清晰使用關聯基數符號標示其關聯性，例如：一對多 (||--o{), 一對一 (||--||), 多對多 (}|--|{) 等關聯，並撰寫簡短的欄位與關聯規劃解說。`,
    
    `【第 10 層：與其他節點關係與資料流互動分析】
接著，請詳細分析本模組與心智圖中其他節點在資料流向、前端事件或後端 API 上的交互整合關係與依賴程度。`,
    
    `【第 11 層：自動生成結構設計 Mermaid 流程圖 & 新增建議】
最後，請為本規劃提供一個精美且可直接繪製的 Mermaid Flowchart 流程圖（使用 \`\`\`mermaid 和 \`\`\` 包裹）。
並且必須在最後附帶一個包含 add 調整指令的 JSON 陣列（使用 \`\`\`json 和 \`\`\` 包裹，必須包含多個 add 指令，在當前選取節點「${nodeText}」下，建議新增多個子節點，以便使用者點選套用至心智圖中）。`,

    `【第 12 層：產出供 AI Agent 執行的完整開發指令 Prompt (可複製)】
請結合前面所有 11 個階段的技術架構分析成果，生成一個專門寫給 AI Coding Agent（如 Cursor、GitHub Copilot、Code Agent）的完整、清晰、結構化的「AI Agent 開發實作指令 Prompt」。
這個 Prompt 必須設計成可以複製，並且在 Prompt 中明確命令該 Coding Agent 先執行並生成以下幾項設計文件：
1. **獨立的 Markdown (.md) 設計書**：
   - 【前端規格設計書 (Frontend.md)】：包含 Vue 元件掛載、Props 傳遞與前端 UI 邏輯。
   - 【後端規格設計書 (Backend.md)】：包含 Controller、Service 層、路由 API、防禦性驗證機制。
   - 【資料庫設計書 (Database.md)】：使用先前評估比分推薦的資料庫，並包含對應表欄位欄位設定與結構。
2. **資料庫 ER 關聯圖 (ER Diagram)**：
   - 必須輸出一個 Mermaid ER 圖（Entity Relationship Diagram），在圖中尤其必須清楚標記一對多 (One-to-Many)、一對一 (One-to-One)、或多對多 (Many-to-Many) 等關聯基數符號。
3. **開發順序時間軸 (Development Timeline)**：
   - 明確劃分階段性的實作順序（例如：資料庫遷移 -> 後端 Service/Model -> 後端 Controller/API 路由 -> 前端元件掛載 -> 聯調與測試）。
讓使用者複製後貼給 AI 程式助手即可依序精準建立專案架構。`
  ]

  try {
    for (let i = startFromIdx; i < 13; i++) {
      if (isMultiStagePaused.value) {
        break
      }
      if (activeAbortController) {
        activeAbortController.abort()
      }
      activeAbortController = new AbortController()

      currentAnalyzingStageIndex.value = i
      currentViewedStageIndex.value = i
      stagesProgress.value[i].status = 'running'
      stageIsThinking.value[i] = true
      multiStageStatusMessage.value = `🧠 AI 正在分析：${stagesProgress.value[i].name}...`

      if (i === 0 && startFromIdx === 0) {
        const logToStage0 = (html) => {
          stageLogs.value[0] = html
        }

        let logHtml = `
          <div class="flex items-center gap-2 text-purple-400">
            <span class="animate-spin text-xs">⏳</span>
            <span>[SCAN] 正在讀取並掃描專案目錄結構樹...</span>
          </div>
        `
        logToStage0(logHtml)
        
        try {
          if (allowAiReadCode.value) {
            const targetProj = selectedProject.value || 'beartor'
            const treeRes = await window.axios.post('/api/projects/tree', { 
              project: targetProj,
              username: selectedProjectUser.value
            })
            
            if (treeRes.data.success && treeRes.data.files.length > 0) {
              const filesList = treeRes.data.files.map(f => f.relative_path)
              scannedFilesList.value = filesList
              logHtml += `
                <div class="text-emerald-400">
                  ✓ [SCAN SUCCESS] 已成功掃描專案目錄，共找到 ${filesList.length} 個檔案
                </div>
                <div class="flex items-center gap-2 text-purple-400">
                  <span class="animate-spin text-xs">⏳</span>
                  <span>[PRE-FLIGHT] AI 正在評估專案目錄，篩選核心代碼檔案...</span>
                </div>
              `
              logToStage0(logHtml)
              
              const preFlightRes = await fetch(`${apiEndpointVal}/v1/chat/completions`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                signal: activeAbortController.signal,
                body: JSON.stringify({
                  model: apiModelVal,
                  messages: [
                    { 
                      role: 'system', 
                      content: '你是一個專案目錄結構預檢大師。請閱讀提供的檔案列表，依據使用者的任務指示，回傳一個包含最多 3 個必須精讀的檔案相對路徑之 JSON 陣列。請只回傳陣列 JSON本身，例如：["app/Http/Controllers/HomeController.php"]，絕不要包含 any other explanation or Markdown markup.' 
                    },
                    { 
                      role: 'user', 
                      content: `專案檔案列表：\n${JSON.stringify(filesList.slice(0, 300))}\n\n任務指示：請針對目前選取的心智圖架構節點「${nodeText}」進行 12 層深度系統分析規劃。\n請回傳 JSON 陣列：` 
                    }
                  ],
                  temperature: 0.1
                })
              })
              
              if (preFlightRes.ok) {
                const preFlightData = await preFlightRes.json()
                const aiDecision = preFlightData.choices[0]?.message?.content || ''
                
                let targetPaths = []
                try {
                  const arrMatch = aiDecision.match(/\[\s*([\s\S]*?)\s*\]/)
                  if (arrMatch) {
                    targetPaths = JSON.parse(arrMatch[0])
                  } else {
                    targetPaths = JSON.parse(aiDecision.trim())
                  }
                } catch (e) {
                  filesList.forEach(path => {
                    if (aiDecision.includes(path) && targetPaths.length < 3) {
                      targetPaths.push(path)
                    }
                  })
                }
                
                if (targetPaths && targetPaths.length > 0) {
                  targetPaths = targetPaths.slice(0, 3)
                  logHtml += `
                    <div class="text-amber-400 font-bold">
                      ✓ [AI SELECTED] AI 智慧選定精讀以下 3 個核心檔案：
                    </div>
                    <div class="pl-4 text-neutral-300">
                      ${targetPaths.map(p => `• ${p}`).join('<br/>')}
                    </div>
                  `
                  logToStage0(logHtml)
                  
                  let filesContext = '[CRITICAL SECURITY BOUNDARY] You are running in a strictly READ-ONLY sandbox. Analyze this project files context:\n\n'
                  
                  for (const path of targetPaths) {
                    logHtml += `
                      <div class="flex items-center gap-2 text-purple-400 animate-pulse">
                        <span>⏳</span>
                        <span>[READING] 正在精讀代碼檔案: ${path}...</span>
                      </div>
                    `
                    logToStage0(logHtml)
                    
                    const fileRes = await window.axios.post('/api/projects/read', {
                      project: targetProj,
                      file_path: path,
                      username: selectedProjectUser.value
                    })
                    
                    if (fileRes.data.success) {
                      filesContext += `==== 檔案: ${path} ====\n\`\`\`\n${fileRes.data.content}\n\`\`\`\n\n`
                      loadedFilesCache.value.push(path)
                      logHtml += `
                        <div class="text-neutral-400 text-[10px] pl-4">
                          - 已成功載入 ${path} (${fileRes.data.content.length} 字元)
                        </div>
                      `
                      logToStage0(logHtml)
                    }
                  }
                  attachedCodeContext.value = filesContext
                }
              }
            } else {
              logHtml += `<div class="text-red-400">[ERROR] 找不到檔案或讀取目錄權限被拒絕。將採用空專案模式繼續。</div>`
              logToStage0(logHtml)
            }
          } else if (selectedFile.value && selectedFileContent.value) {
            logHtml += `
              <div class="text-emerald-400">
                ✓ [LOAD SELECTED] 正在載入手動選取檔案：${selectedFile.value.name}
              </div>
            `
            logToStage0(logHtml)
            let filesContext = `[CRITICAL SECURITY BOUNDARY] You are running in a strictly READ-ONLY sandbox. Analyze this project file context:\n`
            filesContext += `==== 檔案: ${selectedFile.value.relative_path} ====\n\`\`\`\n${selectedFileContent.value}\n\`\`\`\n\n`
            attachedCodeContext.value = filesContext
            loadedFilesCache.value.push(selectedFile.value.relative_path)
            logHtml += `
              <div class="text-neutral-400 text-[10px] pl-4">
                - 已成功載入 ${selectedFile.value.name} (${selectedFileContent.value.length} 字元)
              </div>
            `
            logToStage0(logHtml)
          } else {
            logHtml += `
              <div class="text-neutral-400">
                ℹ [NO CONTEXT] 未開啟代碼閱讀權限且無手動載入檔案，僅基於心智圖內容進行 analysis。
              </div>
            `
            logToStage0(logHtml)
          }
        } catch (err) {
          console.error(err)
          logHtml += `<div class="text-red-400">[EXCEPTION] 檔案預檢中斷: ${err.message}</div>`
          logToStage0(logHtml)
        }
        
        logHtml += `<div class="text-purple-400 font-bold mt-2 animate-pulse">[STREAM] AI 正在產出第 0 層分析報告中...</div>`
        logToStage0(logHtml)
      } else if (i > 0 && allowAiReadCode.value) {
        // Pass 1: Dynamic Pre-flight check for Stage 1 to 12
        const targetProj = selectedProject.value || 'beartor'
        if (scannedFilesList.value.length === 0) {
          stageLogs.value[i] = `
            <div class="flex items-center gap-2 text-purple-400">
              <span class="animate-spin text-xs">⏳</span>
              <span>[SCAN] 正在讀取並掃描專案目錄結構樹...</span>
            </div>
          `
          try {
            const treeRes = await window.axios.post('/api/projects/tree', { 
              project: targetProj,
              username: selectedProjectUser.value
            })
            if (treeRes.data.success && treeRes.data.files.length > 0) {
              scannedFilesList.value = treeRes.data.files.map(f => f.relative_path)
            }
          } catch (treeErr) {
            console.error(treeErr)
          }
        }

        if (scannedFilesList.value.length > 0) {
          let stageLogHtml = `
            <div class="flex items-center gap-2 text-purple-400">
              <span class="animate-spin text-xs">⏳</span>
              <span>[PRE-FLIGHT] 正在分析本層級是否需要讀取額外代碼檔案...</span>
            </div>
          `
          stageLogs.value[i] = stageLogHtml

          try {
            const cacheHint = loadedFilesCache.value.length > 0
              ? `目前快取中已精讀載入的檔案：\n${JSON.stringify(loadedFilesCache.value)}`
              : '目前尚未讀取任何代碼檔案。'

            const checkRes = await fetch(`${apiEndpointVal}/v1/chat/completions`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              signal: activeAbortController.signal,
              body: JSON.stringify({
                model: apiModelVal,
                messages: [
                  { 
                    role: 'system', 
                    content: '你是一位代碼檢索精讀助手。請審查當前分析主題、指示，對比專案檔案清單與已加載的快取。判斷為了進行本層任務，是否需要讀取額外的檔案內容。如果需要，請只回傳一個包含所需檔案相對路徑的 JSON 陣列，例如：["app/Models/User.php"]（一次不超過 3 個，且不要包含任何已在快取中的路徑）。如果不需要加載任何新檔案，請務必且只回傳 ["NONE"]。請絕不要包含 markdown 格式、註解或說明。' 
                  },
                  { 
                    role: 'user', 
                    content: `當前分析階段：${stagesProgress.value[i].name}\n分析任務指示：${stagePrompts[i]}\n\n專案檔案清單：\n${JSON.stringify(scannedFilesList.value.slice(0, 300))}\n\n${cacheHint}\n\n請回傳所需 JSON 相對路徑陣列或 ["NONE"]：` 
                  }
                ],
                temperature: 0.1
              })
            })

            if (checkRes.ok) {
              const checkData = await checkRes.json()
              const checkDecision = checkData.choices[0]?.message?.content || ''
              
              let requestedPaths = []
              try {
                const arrMatch = checkDecision.match(/\[\s*([\s\S]*?)\s*\]/)
                if (arrMatch) {
                  requestedPaths = JSON.parse(arrMatch[0])
                } else {
                  requestedPaths = JSON.parse(checkDecision.trim())
                }
              } catch (e) {
                scannedFilesList.value.forEach(path => {
                  if (checkDecision.includes(path) && !loadedFilesCache.value.includes(path) && requestedPaths.length < 3) {
                    requestedPaths.push(path)
                  }
                })
              }

              requestedPaths = requestedPaths.filter(p => p !== 'NONE' && p !== 'none' && !loadedFilesCache.value.includes(p))

              if (requestedPaths && requestedPaths.length > 0) {
                requestedPaths = requestedPaths.slice(0, 3)
                stageLogHtml = `
                  <div class="text-amber-400 font-bold">
                    ✓ [AI REQUESTED] 本層分析需要加載並精讀以下檔案：
                  </div>
                  <div class="pl-4 text-neutral-300">
                    ${requestedPaths.map(p => `• ${p}`).join('<br/>')}
                  </div>
                `
                stageLogs.value[i] = stageLogHtml

                let newContext = attachedCodeContext.value || '[CRITICAL SECURITY BOUNDARY] Sandbox Context:\n\n'
                
                for (const path of requestedPaths) {
                  stageLogHtml += `
                    <div class="flex items-center gap-2 text-purple-400 animate-pulse">
                      <span>⏳</span>
                      <span>[READING] 正在加載檔案: ${path}...</span>
                    </div>
                  `
                  stageLogs.value[i] = stageLogHtml

                  const fileRes = await window.axios.post('/api/projects/read', {
                    project: targetProj,
                    file_path: path,
                    username: selectedProjectUser.value
                  })

                  if (fileRes.data.success) {
                    newContext += `==== 檔案: ${path} ====\n\`\`\`\n${fileRes.data.content}\n\`\`\`\n\n`
                    loadedFilesCache.value.push(path)
                    stageLogHtml += `
                      <div class="text-neutral-400 text-[10px] pl-4">
                        - 已成功載入 ${path} (${fileRes.data.content.length} 字元)
                      </div>
                    `
                    stageLogs.value[i] = stageLogHtml
                  }
                }
                
                attachedCodeContext.value = newContext
                stageLogHtml += `<div class="text-emerald-400 font-bold mt-1">✓ [READY] 代碼合流成功，正式啟動本層架構分析。</div>`
                stageLogs.value[i] = stageLogHtml
              } else {
                stageLogHtml = `<div class="text-emerald-400">✓ [CACHE READY] 快取脈絡已足夠，無需讀取新檔案。開始分析本層架構！</div>`
                stageLogs.value[i] = stageLogHtml
              }
            }
          } catch (checkErr) {
            console.error('Pre-flight dynamic file retrieval error:', checkErr)
            stageLogHtml += `<div class="text-amber-500">[WARN] 預檢分析發生異常 (${checkErr.message})，將使用現有快取繼續。</div>`
            stageLogs.value[i] = stageLogHtml
          }
        }
      }
      
      const accumulatedContext = stageOutputs.value
        .map((out, idx) => out ? `### ${stagesProgress.value[idx].name}\n${out}` : '')
        .filter(Boolean)
        .join('\n\n')

      const currentPrompt = `${attachedCodeContext.value}
當前整個心智圖設計文件的完整 JSON 結構如下：
\`\`\`json
${fullMindmapJson}
\`\`\`

我們目前已經累積的分析報告如下：
\`\`\`markdown
${accumulatedContext}
\`\`\`

[CRITICAL INSTRUCTION] 請「僅限」針對本階段（${stagesProgress.value[i].name}）的特定主題進行深入分析與撰寫，絕對不可以提前撰寫後續其他階段的內容（例如：如果在第 1 或第 2 層，絕對不要自行輸出資料庫規劃、Migration 或 Mermaid 圖，這些會在後續的特定圖表層由程式安排生成）。請將注意力 100% 集中在本層規定的任務。

請接續上面的分析報告，依據以下指示與風格設定撰寫本次部分的報告內容：
${stagePrompts[i]}

重要規定與表達風格：
${customStylePrompt}
- 請務必且只能使用「繁體中文」(Traditional Chinese) 進行文字回答。直接開始回答，不要有防廢話。`

      try {
        const res = await fetch(`${apiEndpointVal}/v1/chat/completions`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          signal: activeAbortController.signal,
          body: JSON.stringify({
            model: apiModelVal,
            messages: [
              { role: 'system', content: '你是一位優秀的資深軟體架構師。請以繁體中文撰寫非常詳細、結構化且直接採用 HTML Bootstrap 排版包裹的架構分析報告。' },
              { role: 'user', content: currentPrompt }
            ],
            temperature: 0.7,
            stream: true
          })
        })

        if (!res.ok) {
          throw new Error(`HTTP 錯誤 ${res.status}: ${res.statusText || '連線失敗'}`)
        }

        const reader = res.body.getReader()
        const decoder = new TextDecoder("utf-8")
        let finished = false
        let buffer = ''

        while (!finished) {
          const { value, done } = await reader.read()
          if (done) {
            finished = true
            break
          }
          
          buffer += decoder.decode(value, { stream: true })
          const lines = buffer.split('\n')
          buffer = lines.pop()

          for (const line of lines) {
            const trimmed = line.trim()
            if (!trimmed || trimmed === 'data: [DONE]') continue
            if (trimmed.startsWith('data: ')) {
              try {
                const parsed = JSON.parse(trimmed.slice(6))
                const delta = parsed.choices[0]?.delta
                if (delta?.content) {
                  stageIsThinking.value[i] = false
                  stageOutputs.value[i] += delta.content
                } else if (delta?.reasoning_content) {
                  stageIsThinking.value[i] = true
                  stageThoughts.value[i] += delta.reasoning_content
                } else if (delta?.reasoning) {
                  stageIsThinking.value[i] = true
                  stageThoughts.value[i] += delta.reasoning
                }
              } catch (e) {}
            }
          }
        }
        
        if (buffer && buffer.startsWith('data: ')) {
          try {
            const parsed = JSON.parse(buffer.slice(6))
            const delta = parsed.choices[0]?.delta
            if (delta?.content) {
              stageIsThinking.value[i] = false
              stageOutputs.value[i] += delta.content
            } else if (delta?.reasoning_content) {
              stageThoughts.value[i] += delta.reasoning_content
            } else if (delta?.reasoning) {
              stageThoughts.value[i] += delta.reasoning
            }
          } catch (e) {}
        }

        stagesProgress.value[i].status = 'success'
        stageIsThinking.value[i] = false
        await saveAiHistoryProgress()
      } catch (innerError) {
        if (innerError.name === 'AbortError') {
          console.log('AI analysis request aborted successfully.')
          return
        }
        stagesProgress.value[i].status = 'error'
        stageIsThinking.value[i] = false
        stageOutputs.value[i] = `<div class="alert alert-danger m-0 p-3"><strong>⚠️ 呼叫 AI 發生錯誤：</strong><span class="font-mono text-xs d-block mt-1">${innerError.message}</span><span class="d-block mt-2 text-muted">請確認您的 AI 端點設定與網路狀態是否正常。</span></div>`
        throw innerError
      }
    }

    // Parse JSON actions for node insertions from stage outputs
    const fullText = stageOutputs.value.join('\n\n')
    let actions = []
    const jsonBlocks = [...fullText.matchAll(/```json\s*([\s\S]*?)\s*```/g)]
    if (jsonBlocks.length > 0) {
      const lastBlock = jsonBlocks[jsonBlocks.length - 1][1]
      try {
        actions = JSON.parse(lastBlock)
      } catch (e) {
        console.error(e)
      }
    }
    
    multiStageActions.value = actions.map(act => ({
      id: 'act-' + Math.random().toString(36).substr(2, 9),
      type: act.type,
      target: act.target,
      text: act.text || '',
      selected: true
    }))

  } catch (error) {
    console.error('多層分析出錯並終止:', error.message)
  } finally {
    isMultiStageRunning.value = false
    multiStageStatusMessage.value = ''
  }
}

const applyMultiStageProposals = () => {
  const selectedProposals = multiStageActions.value.filter(p => p.selected)
  if (selectedProposals.length > 0) {
    selectedProposals.forEach(proposal => {
      let targetNode = findNodeByText(proposal.target)
      if (!targetNode && selectedNode.value) {
        targetNode = selectedNode.value
      }
      
      if (proposal.type === 'add' && targetNode) {
        const newNode = addNode(targetNode.id)
        if (newNode) {
          newNode.text = proposal.text
        }
      }
    })
  }
  showMultiStageModal.value = false
  selectNode('root')
}

const toggleAllMultiProposals = (val) => {
  multiStageActions.value.forEach(p => p.selected = val)
}

const selectedMultiProposalsCount = computed(() => {
  return multiStageActions.value.filter(p => p.selected).length
})
</script>

<template>
  <div class="h-screen w-screen flex flex-col bg-white overflow-hidden text-neutral-800">
    <!-- Topbar Navigation -->
    <header class="h-14 border-b border-neutral-100 px-6 flex items-center justify-between shrink-0 no-select">
      <!-- Title -->
      <div class="flex items-center gap-3">
        <Link href="/dashboard" class="w-7 h-7 rounded bg-neutral-900 flex items-center justify-center text-white font-black text-xs hover:bg-neutral-800 transition-all select-none">
          M
        </Link>
        <div>
          <h1 class="text-sm font-bold tracking-tight text-neutral-800 flex items-center gap-1.5 h-6">
            <input 
              v-if="mindmap"
              v-model="mindmap.text"
              type="text"
              class="bg-transparent border-b border-transparent hover:border-neutral-200 focus:border-neutral-400 focus:outline-none text-sm font-bold tracking-tight text-neutral-800 p-0 h-6 leading-6 max-w-[240px] rounded align-middle"
              @change="saveToSession"
            />
            <span v-else class="h-6 leading-6">極簡心智圖設計文件</span>
            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-purple-50 text-purple-600 border border-purple-100/50 shrink-0 select-none h-4.5 flex items-center justify-center">雲端同步</span>
          </h1>
          <p class="text-[10px] text-neutral-400 mt-0.5 leading-none">設計文件藍圖編輯器</p>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-4">
        <!-- Template Selection -->
        <div class="flex items-center gap-1.5">
          <TemplateIcon class="w-3.5 h-3.5 text-neutral-400" />
          <select 
            v-model="selectedTemplate" 
            @change="onTemplateChange"
            class="bg-neutral-50 hover:bg-neutral-100 border-none rounded px-2.5 py-1 text-xs text-neutral-700 font-medium focus:outline-none transition-colors"
          >
            <option value="software_design">軟體設計文件</option>
            <option value="ai_workflow">AI 流程規範</option>
            <option value="blank">空白範本</option>
          </select>
        </div>

        <div class="w-px h-4 bg-neutral-200"></div>

        <!-- History Undo / Redo -->
        <div class="flex items-center gap-1">
          <button 
            @click="undo" 
            :disabled="!canUndo()"
            class="p-1.5 hover:bg-neutral-50 text-neutral-600 rounded disabled:opacity-30 disabled:pointer-events-none transition-colors"
            title="復原 (Ctrl+Z)"
          >
            <UndoIcon class="w-3.5 h-3.5" />
          </button>
          <button 
            @click="redo" 
            :disabled="!canRedo()"
            class="p-1.5 hover:bg-neutral-50 text-neutral-600 rounded disabled:opacity-30 disabled:pointer-events-none transition-colors"
            title="重做 (Ctrl+Y)"
          >
            <RedoIcon class="w-3.5 h-3.5" />
          </button>
        </div>

        <div class="w-px h-4 bg-neutral-200"></div>

        <!-- Import / Export Actions -->
        <div class="flex items-center gap-2">
          <input 
            ref="fileInputRef" 
            type="file" 
            accept=".json" 
            class="hidden" 
            @change="handleFileImport"
          />
          <button 
            @click="triggerImport"
            class="flex items-center gap-1.5 bg-neutral-50 hover:bg-neutral-100 text-neutral-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
          >
            <UploadIcon class="w-3.5 h-3.5" />
            <span>匯入 JSON</span>
          </button>
          <button 
            @click="showImportCodeModal = true"
            class="flex items-center gap-1.5 bg-neutral-50 hover:bg-neutral-100 text-neutral-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
          >
            <TerminalIcon class="w-3.5 h-3.5 text-neutral-500" />
            <span>匯入原始碼</span>
          </button>
          <button 
            @click="handleExport"
            class="flex items-center gap-1.5 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
          >
            <DownloadIcon class="w-3.5 h-3.5" />
            <span>匯出 JSON</span>
          </button>
          <button 
            @click="openProjectReader"
            class="flex items-center gap-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm transition-colors cursor-pointer"
          >
            <BookOpenIcon class="w-3.5 h-3.5" />
            <span>讀取專案代碼</span>
          </button>
          <button 
            @click="showRevisionDrawer = true"
            class="flex items-center gap-1.5 bg-neutral-100 hover:bg-neutral-200 text-neutral-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer"
          >
            <HistoryIcon class="w-3.5 h-3.5 text-neutral-500" />
            <span>變更歷史</span>
          </button>
          <button 
            @click="saveToDatabase"
            :disabled="isSaving"
            class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm transition-all disabled:opacity-40 cursor-pointer"
          >
            <span v-if="isSaving" class="w-3 h-3 border-2 border-t-transparent border-white rounded-full animate-spin"></span>
            <CheckIcon v-else class="w-3.5 h-3.5" />
            <span>儲存到雲端</span>
          </button>
        </div>

        <div class="w-px h-4 bg-neutral-200"></div>

        <!-- Clear / Destructive -->
        <button 
          @click="handleClear"
          class="p-1.5 hover:bg-red-50 text-neutral-400 hover:text-red-500 rounded transition-colors"
          title="清除畫布"
        >
          <TrashIcon class="w-3.5 h-3.5" />
        </button>

        <div class="w-px h-4 bg-neutral-200"></div>

        <!-- Sidebar Toggles -->
        <div class="flex items-center gap-1">
          <button 
            @click="showOutline = !showOutline"
            class="p-1.5 rounded transition-colors"
            :class="showOutline ? 'bg-neutral-100 text-neutral-800' : 'hover:bg-neutral-50 text-neutral-400'"
            title="切換大綱"
          >
            <MenuIcon class="w-3.5 h-3.5" />
          </button>
          <button 
            @click="showDetailsPanel = !showDetailsPanel"
            class="p-1.5 rounded transition-colors"
            :class="showDetailsPanel ? 'bg-neutral-100 text-neutral-800' : 'hover:bg-neutral-50 text-neutral-400'"
            title="切換細節編輯面板"
          >
            <BookOpenIcon class="w-3.5 h-3.5" />
          </button>
          <button 
            @click="showAiPanel = !showAiPanel"
            class="p-1.5 rounded transition-colors"
            :class="showAiPanel ? 'bg-neutral-100 text-neutral-800' : 'hover:bg-neutral-50 text-neutral-400'"
            title="切換 AI 面板"
          >
            <SparklesIcon class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </header>

    <!-- Main Workspace Split Panel -->
    <div class="flex-1 flex overflow-hidden relative">
      <!-- Floating Batch Actions Toolbar for Multi-select -->
      <transition
        enter-active-class="transition-all duration-300 ease-out"
        leave-active-class="transition-all duration-200 ease-in"
        enter-from-class="-translate-y-12 opacity-0"
        leave-to-class="-translate-y-12 opacity-0"
      >
        <div 
          v-if="selectedNodeIds.length > 1"
          class="absolute top-4 left-1/2 -translate-x-1/2 bg-neutral-900 text-white px-4 py-2.5 rounded-full shadow-lg flex items-center gap-4 z-30 text-xs font-medium no-select"
        >
          <span class="text-neutral-300">已選取 {{ selectedNodeIds.length }} 個節點</span>
          
          <div class="w-px h-4 bg-neutral-700"></div>
          
          <!-- Color batch change -->
          <div class="flex items-center gap-1.5">
            <PaletteIcon class="w-3.5 h-3.5 text-neutral-400" />
            <div class="flex items-center gap-1">
              <button 
                v-for="color in COLORS" 
                :key="color"
                @click="batchColorSelection(color)"
                class="w-3.5 h-3.5 rounded-full border border-neutral-700 hover:scale-125 transition-transform"
                :style="{ backgroundColor: color }"
                :title="'將選取節點變更為此色'"
              ></button>
            </div>
          </div>
          
          <div class="w-px h-4 bg-neutral-700"></div>

          <!-- Batch delete -->
          <button 
            @click="batchDelete"
            class="flex items-center gap-1 text-red-400 hover:text-red-300 transition-colors"
          >
            <TrashIcon class="w-3.5 h-3.5" />
            <span>批次刪除</span>
          </button>

          <div class="w-px h-4 bg-neutral-700"></div>

          <!-- Cancel/Close selection -->
          <button 
            @click="clearSelection"
            class="p-0.5 hover:bg-neutral-800 rounded-full transition-colors text-neutral-400 hover:text-white"
          >
            <CloseIcon class="w-4 h-4" />
          </button>
        </div>
      </transition>

      <!-- Left sidebar: Outline view -->
      <transition 
        enter-active-class="transition-all duration-300 ease-in-out"
        leave-active-class="transition-all duration-300 ease-in-out"
        enter-from-class="-ml-80 opacity-0"
        leave-to-class="-ml-80 opacity-0"
      >
        <DocumentOutline 
          v-if="showOutline && mindmap"
          :mindmap="mindmap"
          :selected-node-ids="selectedNodeIds"
          @select-node="handleSelectNode"
          @add-node="addNode"
          @add-sibling="addSiblingNode"
          @delete-node="deleteNode"
          @update-text="handleUpdateText"
          @toggle-expand="toggleNodeExpand"
          @nest-node="nestNode"
          @unnest-node="unnestNode"
          @open-properties="openNodePropertiesModal"
        />
      </transition>

      <!-- Center: Canvas View & Bottom Details Drawer Split Panel -->
      <div class="flex-1 flex flex-col min-w-0 min-h-0 relative">
        <MindmapCanvas 
          v-if="mindmap"
          :mindmap="mindmap"
          :selected-node-ids="selectedNodeIds"
          @select-node="handleSelectNode"
          @add-node="addNode"
          @add-sibling="addSiblingNode"
          @delete-node="deleteNode"
          @update-text="handleUpdateText"
          @toggle-expand="toggleNodeExpand"
        />

        <!-- Collapsible Bottom Node Details & Execution Plan Drawer -->
        <NodeDetailsDrawer
          :show="showDetailsPanel"
          :selected-node="selectedNode"
          :ai-details-loading="aiDetailsLoading"
          @close="showDetailsPanel = false"
          @generateDetails="generateNodeDetails"
          @updateDetails="val => updateNodeDetails(selectedNode.id, val)"
          @addProperty="addCustomProperty"
          @renameProperty="({ oldKey, newKey }) => renamePropertyKey(oldKey, newKey)"
          @updateProperty="({ key, val }) => updatePropertyValue(key, val)"
          @deleteProperty="key => deleteProperty(key)"
        />
      </div>

      <!-- Right sidebar: AI Integration Panel -->
      <transition 
        enter-active-class="transition-all duration-300 ease-in-out"
        leave-active-class="transition-all duration-300 ease-in-out"
        enter-from-class="-mr-80 opacity-0"
        leave-to-class="-mr-80 opacity-0"
      >
        <AiPanel 
          v-if="showAiPanel"
          :selected-node="selectedNode"
          :mindmap="mindmap"
          v-model:api-endpoint="apiEndpoint"
          v-model:api-model="apiModel"
          v-model:allow-ai-read-code="allowAiReadCode"
          v-model:selected-project="selectedProject"
          v-model:selected-project-user="selectedProjectUser"
          :project-users="projectUsers"
          :projects="projects"
          :selected-file="selectedFile"
          :selected-file-content="selectedFileContent"
          @ai-proposals="onAiProposals"
          @trigger-multistage="triggerMultiStageSetup"
          @add-children="handleAddChildren"
          @update-text="handleUpdateText"
        />
      </transition>
    </div>

    <!-- AI Suggestion Modal -->
    <AiProposalsModal 
      :show="showAiModal" 
      :proposals="aiProposalActions" 
      :selected-count="selectedProposalsCount" 
      @close="showAiModal = false" 
      @toggleAll="toggleAllProposals" 
      @apply="applyProposals" 
    />

    <!-- 12-Stage Progressive Multi-stage reasoning Modal -->
    <MultiStageModal
      :show="showMultiStageModal"
      :has-started-multi-stage="hasStartedMultiStage"
      :is-multi-stage-running="isMultiStageRunning"
      :is-multi-stage-paused="isMultiStagePaused"
      :stages-progress="stagesProgress"
      :stage-outputs="stageOutputs"
      :stage-logs="stageLogs"
      :stage-thoughts="stageThoughts"
      :current-analyzing-stage-index="currentAnalyzingStageIndex"
      :current-viewed-stage-index="currentViewedStageIndex"
      :multi-stage-status-message="multiStageStatusMessage"
      :multi-stage-actions="multiStageActions"
      :grand-total-score="grandTotalScore"
      :selected-multi-proposals-count="selectedMultiProposalsCount"
      :mbti-style="mbtiStyle"
      :is-user-engineer="isUserEngineer"
      :allow-ai-read-code="allowAiReadCode"
      :api-endpoint="apiEndpoint"
      :api-model="apiModel"
      :selected-project="selectedProject"
      :selected-project-user="selectedProjectUser"
      :project-users="projectUsers"
      :project-files="projectFiles"
      :projects="projects"
      :history-versions="historyVersions"
      :parse-and-render-content="parseAndRenderContent"
      :copy-to-clipboard="copyToClipboard"
      @update:mbtiStyle="val => mbtiStyle = val"
      @update:isUserEngineer="val => isUserEngineer = val"
      @update:allowAiReadCode="val => allowAiReadCode = val"
      @update:apiEndpoint="val => apiEndpoint = val"
      @update:apiModel="val => apiModel = val"
      @update:selectedProject="val => selectedProject = val"
      @update:selectedProjectUser="val => selectedProjectUser = val"
      @update:currentViewedStageIndex="val => currentViewedStageIndex = val"
      @close="showMultiStageModal = false"
      @start="triggerMultiStageAnalysisStart"
      @pause="pauseMultiStageAnalysis"
      @continue="triggerMultiStageAnalysisStart"
      @refine="handleRefinementRequest"
      @applyProposals="applyMultiProposals"
      @print="generatePrintableReport"
      @loadVersion="loadHistoryVersion"
      @deleteVersion="deleteHistoryVersion"
    />
    
    <!-- Import Raw Code Modal -->
    <ImportCodeModal 
      :show="showImportCodeModal" 
      @close="showImportCodeModal = false" 
      @import="handleImportCode" 
    />

    <!-- Node Properties & Details Modal -->
    <transition
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-200 ease-in"
      enter-from-class="opacity-0 scale-95"
      leave-to-class="opacity-0 scale-95"
    >
      <div 
        v-if="showNodePropertiesModal && nodeToEdit"
        class="fixed inset-0 bg-neutral-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-6 no-select"
      >
        <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl flex flex-col overflow-hidden border border-neutral-100">
          <!-- Header -->
          <div class="p-5 border-b border-neutral-100 flex items-center justify-between shrink-0 bg-neutral-50/50">
            <div class="flex items-center gap-2">
              <div class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: nodeToEdit.color }"></div>
              <div>
                <h2 class="text-sm font-semibold text-neutral-800">編輯節點設計屬性</h2>
                <p class="text-[11px] text-neutral-400">更換節點文字、標記色彩與詳細實作規格書資料</p>
              </div>
            </div>
            <button @click="showNodePropertiesModal = false" class="p-1 hover:bg-neutral-200/50 rounded-lg text-neutral-400 hover:text-neutral-700 transition-colors">
              <CloseIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 space-y-4">
            <!-- Node text -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wider">節點名稱 (Text)</label>
              <input 
                v-model="nodeToEdit.text"
                type="text"
                class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-2.5 text-xs text-neutral-700 font-semibold focus:outline-none focus:border-neutral-300 transition-colors"
                placeholder="輸入節點名稱..."
              />
            </div>

            <!-- Node color -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wider">標記主題顏色 (Color)</label>
              <div class="flex items-center gap-2">
                <button 
                  v-for="color in COLORS" 
                  :key="color"
                  @click="nodeToEdit.color = color"
                  class="w-6 h-6 rounded-full border hover:scale-110 transition-all flex items-center justify-center cursor-pointer"
                  :style="{ backgroundColor: color, borderColor: nodeToEdit.color === color ? '#000' : 'transparent' }"
                >
                  <CheckIcon v-if="nodeToEdit.color === color" class="w-3.5 h-3.5 text-white stroke-[3px]" />
                </button>
              </div>
            </div>

            <!-- Node details -->
            <div class="space-y-1.5">
              <div class="flex items-center justify-between">
                <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wider">詳細規格資料與實作計畫 (Details)</label>
                <button 
                  @click="generateNodeDetailsForSelected(nodeToEdit)"
                  :disabled="aiDetailsLoading"
                  class="text-[10px] text-purple-600 hover:underline font-semibold flex items-center gap-1 disabled:opacity-40"
                >
                  <SparklesIcon class="w-3 h-3 animate-pulse" />
                  <span>AI 生成計畫</span>
                </button>
              </div>
              <textarea 
                v-model="nodeToEdit.details"
                rows="6"
                placeholder="在此撰寫該功能模組的詳細功能規格、系統要求、甚至代碼說明等身份資料..."
                class="w-full p-4 bg-neutral-50 border border-neutral-200 rounded-xl focus:outline-none focus:border-neutral-300 font-mono text-xs text-neutral-700 placeholder:text-neutral-300 resize-none select-text"
              ></textarea>
            </div>
          </div>

          <!-- Footer -->
          <div class="p-5 border-t border-neutral-100 flex items-center justify-between bg-neutral-50/50">
            <div>
              <button 
                v-if="nodeToEdit.id !== 'root'"
                @click="deleteNodeAndCloseModal(nodeToEdit.id)"
                class="px-4 py-2 border border-red-200 hover:bg-red-50 text-red-600 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer"
              >
                <TrashIcon class="w-3.5 h-3.5 animate-pulse" />
                <span>刪除此節點</span>
              </button>
            </div>
            <div class="flex items-center gap-2">
              <button @click="showNodePropertiesModal = false" class="px-4 py-2 border border-neutral-200 hover:bg-neutral-100 text-neutral-700 rounded-xl text-xs font-semibold transition-colors">取消</button>
              <button @click="showNodePropertiesModal = false" class="px-5 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5"><CheckIcon class="w-3.5 h-3.5" /><span>完成修改</span></button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Auto Save Modal -->
    <transition
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-200 ease-in"
      enter-from-class="opacity-0 scale-95"
      leave-to-class="opacity-0 scale-95"
    >
      <div 
        v-if="showAutoSaveModal"
        class="fixed inset-0 bg-neutral-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-6 no-select"
      >
        <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl flex flex-col overflow-hidden border border-neutral-100 p-6 text-center space-y-4">
          <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto">
            <CheckIcon class="w-6 h-6 stroke-[3px]" />
          </div>
          <div class="space-y-1">
            <h3 class="text-sm font-bold text-neutral-800">雲端自動儲存成功</h3>
            <p class="text-xs text-neutral-400 leading-relaxed">
              系統偵測到您已閒置 1 分鐘，已自動將當前最新設計草稿與 12 層分析進度儲存至雲端資料庫。
            </p>
          </div>
          <button 
            @click="showAutoSaveModal = false"
            class="w-full py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition-colors cursor-pointer"
          >
            我知道了
          </button>
        </div>
      </div>
    </transition>

    <!-- Project Reader Modal -->
    <transition
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-200 ease-in"
      enter-from-class="opacity-0 scale-95"
      leave-to-class="opacity-0 scale-95"
    >
      <div 
        v-if="showProjectReaderModal"
        class="fixed inset-0 bg-neutral-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-6"
      >
        <div class="bg-white rounded-2xl w-full max-w-6xl h-[85vh] shadow-2xl flex flex-col overflow-hidden border border-neutral-100">
          <!-- Header -->
          <div class="p-5 border-b border-neutral-100 flex items-center justify-between bg-neutral-50/50 shrink-0 select-none">
            <div>
              <h2 class="text-sm font-bold text-neutral-800 flex items-center gap-1.5">
                <BookOpenIcon class="w-4 h-4 text-purple-600" />
                <span>皇榳專案代碼唯讀檢視器</span>
              </h2>
              <p class="text-[10px] text-neutral-400 mt-0.5">唯讀模式瀏覽本地主機開發目錄下的專案原始碼</p>
            </div>
            <button @click="showProjectReaderModal = false" class="p-1 hover:bg-neutral-200/50 rounded-lg text-neutral-400 hover:text-neutral-700 transition-colors cursor-pointer">
              <CloseIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Grid Container -->
          <div class="flex-1 flex min-h-0">
            <!-- Sidebar (Project Selection & File Tree) -->
            <div class="w-80 border-r border-neutral-100 flex flex-col p-4 space-y-4 shrink-0 bg-neutral-50/20 select-none">
              <!-- User Selector Dropdown -->
              <div class="space-y-1">
                <label class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">選擇使用者 (Linux User)</label>
                <select 
                  v-model="selectedProjectUser" 
                  @change="fetchProjectsForUser"
                  class="w-full bg-white border border-neutral-200 rounded-lg px-3 py-2 text-xs text-neutral-700 font-semibold focus:outline-none focus:border-purple-300 transition-colors"
                >
                  <option v-for="user in projectUsers" :key="user" :value="user">{{ user }}</option>
                </select>
              </div>

              <!-- Project Selector Dropdown -->
              <div class="space-y-1">
                <label class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">選擇專案 (Project)</label>
                <div v-if="isProjectsLoading" class="text-xs text-neutral-400 p-2">載入專案中...</div>
                <select 
                  v-else
                  v-model="selectedProject" 
                  @change="selectProject($event.target.value)"
                  class="w-full bg-white border border-neutral-200 rounded-lg px-3 py-2 text-xs text-neutral-700 font-semibold focus:outline-none focus:border-neutral-300 transition-colors"
                >
                  <option v-for="p in projects" :key="p.name" :value="p.name">{{ p.name }}</option>
                </select>
              </div>

              <!-- File Search Filter -->
              <div class="space-y-1">
                <label class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">搜尋檔案 (Search)</label>
                <input 
                  v-model="fileFilter"
                  type="text"
                  placeholder="輸入檔名過濾..."
                  class="w-full bg-white border border-neutral-200 rounded-lg px-3 py-2 text-xs text-neutral-700 placeholder:text-neutral-300 focus:outline-none focus:border-neutral-300 transition-colors"
                />
              </div>

              <!-- File List -->
              <div class="flex-1 min-h-0 flex flex-col space-y-1">
                <label class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">檔案列表 (Files)</label>
                
                <div v-if="isTreeLoading" class="flex-1 flex items-center justify-center text-xs text-neutral-400">
                  <span class="w-4 h-4 border-2 border-t-transparent border-neutral-400 rounded-full animate-spin mr-1.5"></span>
                  <span>掃描檔案結構...</span>
                </div>
                <div v-else-if="filteredFiles.length === 0" class="flex-1 flex items-center justify-center text-xs text-neutral-300">
                  無匹配的檔案
                </div>
                <div v-else class="flex-1 overflow-y-auto space-y-2 pr-1 text-xs">
                  <div v-for="(files, folderName) in groupedFiles" :key="folderName" class="space-y-0.5">
                    <!-- Folder Header -->
                    <button 
                      @click="toggleFolder(folderName)"
                      class="w-full flex items-center gap-1.5 px-2 py-1 text-neutral-500 hover:text-neutral-800 text-[10px] font-semibold tracking-wider text-left bg-neutral-100/50 rounded cursor-pointer select-none"
                    >
                      <FolderIcon class="w-3.5 h-3.5 text-yellow-500 shrink-0" />
                      <span class="truncate">{{ folderName }}</span>
                      <span class="text-[8px] opacity-40 ml-auto font-mono">({{ files.length }})</span>
                    </button>
                    
                    <!-- Files inside folder -->
                    <div v-if="expandedFolders[folderName] !== false" class="pl-2 border-l border-neutral-100 ml-3.5 space-y-0.5">
                      <button 
                        v-for="file in files" 
                        :key="file.relative_path"
                        @click="selectFile(file)"
                        class="w-full text-left px-2 py-1 rounded hover:bg-neutral-100 transition-colors flex items-center gap-1.5 cursor-pointer font-mono"
                        :class="[selectedFile?.relative_path === file.relative_path ? 'bg-purple-50 text-purple-700 font-semibold' : 'text-neutral-600']"
                      >
                        <FileIcon class="w-3 h-3 text-neutral-400 shrink-0" />
                        <span class="truncate block text-[11px] select-text">{{ file.name }}</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Content Area (Code Preview) -->
            <div class="flex-1 flex flex-col min-h-0 bg-neutral-950 p-4 relative">
              <!-- Selected File Header -->
              <div v-if="selectedFile" class="mb-3 flex items-center justify-between border-b border-white/5 pb-2 shrink-0 select-none">
                <div class="font-mono text-xs text-neutral-400">
                  <span class="text-neutral-500 font-bold mr-1">{{ selectedProject }}</span> / {{ selectedFile.relative_path }}
                </div>
                <span class="text-[9px] font-mono px-2 py-0.5 rounded bg-purple-500/10 text-purple-400 border border-purple-500/20">唯讀模式</span>
              </div>

              <!-- Loading spinner -->
              <div v-if="isFileLoading" class="absolute inset-0 flex items-center justify-center bg-neutral-950/80 z-10">
                <div class="text-center space-y-2">
                  <span class="w-6 h-6 border-2 border-t-transparent border-purple-500 rounded-full animate-spin block mx-auto"></span>
                  <span class="text-xs text-neutral-400 font-mono">讀取檔案中...</span>
                </div>
              </div>

              <!-- Code Preview Box -->
              <div class="flex-1 min-h-0 overflow-auto">
                <pre v-if="selectedFileContent" class="m-0 p-2 font-mono text-xs text-neutral-200 select-text leading-relaxed tab-size-4"><code class="block whitespace-pre">{{ selectedFileContent }}</code></pre>
                <div v-else-if="!selectedFile" class="h-full flex items-center justify-center text-xs text-neutral-600 font-mono select-none">
                  請在左側選擇一個專案檔案進行檢視
                </div>
                <div v-else class="h-full flex items-center justify-center text-xs text-neutral-600 font-mono select-none">
                  檔案為空
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="p-4 border-t border-neutral-100 flex justify-end bg-neutral-50/50 shrink-0 select-none">
            <button 
              @click="showProjectReaderModal = false"
              class="px-5 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition-colors cursor-pointer"
            >
              關閉檢視器
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Revision History Drawer -->
    <transition
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-200 ease-in"
      enter-from-class="opacity-0 translate-x-8"
      leave-to-class="opacity-0 translate-x-8"
    >
      <div 
        v-if="showRevisionDrawer"
        class="fixed inset-0 bg-neutral-900/40 backdrop-blur-sm z-50 flex justify-end"
        @click.self="showRevisionDrawer = false"
      >
        <div class="bg-white w-96 h-full shadow-2xl flex flex-col border-l border-neutral-100 p-6 space-y-6 select-none">
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-neutral-100 pb-4 shrink-0">
            <div class="flex items-center gap-2">
              <HistoryIcon class="w-5 h-5 text-purple-600 animate-pulse" />
              <div>
                <h3 class="text-sm font-bold text-neutral-800">變更歷史紀錄</h3>
                <p class="text-[10px] text-neutral-400 mt-0.5 font-mono">REVISION LOGS HISTORY</p>
              </div>
            </div>
            <button @click="showRevisionDrawer = false" class="p-1 hover:bg-neutral-100 rounded-lg text-neutral-400 hover:text-neutral-700 transition-colors cursor-pointer">
              <CloseIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Timeline list of logs -->
          <div class="flex-1 overflow-y-auto pr-1 space-y-5">
            <div v-if="!props.mindmap.logs || props.mindmap.logs.length === 0" class="h-full flex items-center justify-center text-xs text-neutral-300 font-mono">
              尚未有任何變更歷史紀錄
            </div>
            <div 
              v-else 
              v-for="(log, idx) in props.mindmap.logs" 
              :key="log.id"
              class="relative pl-5 border-l border-neutral-200 ml-2"
            >
              <!-- Timeline Bullet dot -->
              <div 
                class="absolute -left-1.5 top-1.5 w-3 h-3 rounded-full border bg-white flex items-center justify-center"
                :class="[idx === 0 ? 'border-purple-500 bg-purple-100' : 'border-neutral-300']"
              >
                <div class="w-1.5 h-1.5 rounded-full" :class="[idx === 0 ? 'bg-purple-600' : 'bg-neutral-400']"></div>
              </div>

              <!-- Content Card -->
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] px-2 py-0.5 rounded bg-purple-50 text-purple-600 border border-purple-100/50 font-bold">
                     👤 {{ log.user?.name || '未知' }}
                  </span>
                  <span class="text-[9px] text-neutral-400 font-mono">
                    {{ formatDate(log.created_at) }}
                  </span>
                </div>
                <div class="text-xs font-semibold text-neutral-800">
                  {{ log.action_summary }}
                </div>
                
                <!-- Expandable details as a tree list -->
                <ul class="space-y-1 pl-4 list-disc text-[10px] text-neutral-500 font-mono select-text">
                  <li v-for="(detail, didx) in log.details" :key="didx" class="leading-relaxed">
                    {{ detail }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="border-t border-neutral-100 pt-4 shrink-0">
            <button 
              @click="showRevisionDrawer = false"
              class="w-full py-2.5 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition-colors cursor-pointer"
            >
              關閉歷史面板
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Floating Multi-Stage Progress Restore Button -->
    <div 
      v-if="hasStartedMultiStage && !showMultiStageModal"
      class="fixed bottom-6 right-24 z-40 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2.5 rounded-full shadow-2xl flex items-center gap-2 transition-all hover:scale-105 cursor-pointer border border-purple-500/50"
      @click="showMultiStageModal = true"
    >
      <SparklesIcon class="w-4 h-4 animate-spin-slow text-white" />
      <span class="text-xs font-bold font-sans">
        {{ isMultiStageRunning ? '📊 查看分析進度...' : (isMultiStagePaused ? '⏸️ 分析已暫停' : '✅ 檢視規劃報告') }}
      </span>
      <span v-if="isMultiStageRunning" class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
    </div>
  </div>
</template>
