<script setup>
import { ref, onMounted, computed, watch, nextTick, defineComponent, h } from 'vue'
import { useMindmap } from './composables/useMindmap'
import { parseMermaidToTree, parseRawJson } from './utils/codeParser'
import DocumentOutline from './components/DocumentOutline.vue'
import MindmapCanvas from './components/MindmapCanvas.vue'
import AiPanel from './components/AiPanel.vue'

import { 
  Menu as MenuIcon, 
  Sparkles as SparklesIcon,
  Download as DownloadIcon,
  Upload as UploadIcon,
  RotateCcw as UndoIcon,
  RotateCw as RedoIcon,
  Trash2 as TrashIcon,
  FileSpreadsheet as TemplateIcon,
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
  Loader2 as SpinnerIcon
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

// UI state
const showOutline = ref(true)
const showAiPanel = ref(true)
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

// AI Node Details Loading
const aiDetailsLoading = ref(false)

// 12-Stage Multi-stage analysis state
const showMultiStageModal = ref(false)
const multiStageActions = ref([])
const isMultiStageRunning = ref(false)
const hasStartedMultiStage = ref(false)
const showProgressSidebar = ref(false)
const stageIsThinking = ref([false, false, false, false, false, false, false, false, false, false, false, false])
const stageThoughts = ref(['', '', '', '', '', '', '', '', '', '', '', ''])
const showThoughtsCollapse = ref([false, false, false, false, false, false, false, false, false, false, false, false])

// Pre-flight preferences
const isUserEngineer = ref(true)
const mbtiStyle = ref('INTJ')

// Separate output for each stage (12 stages)
const stageOutputs = ref(['', '', '', '', '', '', '', '', '', '', '', ''])

const stagesProgress = ref([
  { id: 1, name: '第一層：看圖說故事 (系統架構意圖與概覽)', status: 'idle' },
  { id: 2, name: '第二層：商業分析與業務價值規劃', status: 'idle' },
  { id: 3, name: '第三層：開發技術棧與語言適配分析 (Laravel, Vue, Tailwind, Python)', status: 'idle' },
  { id: 4, name: '第四層：定位與架構規劃評估 (子功能架構 vs. 整體網站方案對比)', status: 'idle' },
  { id: 5, name: '第五層：技術難點與潛在風險評估', status: 'idle' },
  { id: 6, name: '第六層：技術可行性雷達圖分析 (Chart.js)', status: 'idle' },
  { id: 7, name: '第七層：技術實作做法設計 (Blade + Vue 掛載與載入機制)', status: 'idle' },
  { id: 8, name: '第八層：模組與核心程式結構 (Model, Migration, Controller, Service & Web.php)', status: 'idle' },
  { id: 9, name: '第九層：資料庫 ER 關聯圖設計 (Mermaid ERD)', status: 'idle' },
  { id: 10, name: '第十層：與其他節點關係與資料流互動分析', status: 'idle' },
  { id: 11, name: '第十一層：自動生成結構設計 Mermaid 流程圖 & 新增建議', status: 'idle' },
  { id: 12, name: '第十二層：產出供 AI Agent 執行的完整開發指令 Prompt (可複製)', status: 'idle' }
])

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
  loadFromSession()
  
  // Load dynamic scripts for Math, Diagrams, ChartJS, and Bootstrap CSS
  loadKatex().then(k => { katexInstance.value = k })
  loadMermaid().then(m => { mermaidInstance.value = m })
  loadChartJs().then(c => { chartJsInstance.value = c })
  loadBootstrapCss()

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
      if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        if (selectedNodeIds.value.length > 1) {
          e.preventDefault()
          if (confirm(`確定要刪除選取的 ${selectedNodeIds.value.length} 個節點嗎？`)) {
            deleteSelectedNodes()
          }
        }
      }
    }
  })
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

// Inline component definitions
const MermaidRender = defineComponent({
  props: {
    code: { type: String, required: true },
    id: { type: [String, Number], required: true }
  },
  setup(props) {
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

    return () => h('div', {
      class: 'mt-4 border border-purple-100 rounded-xl bg-purple-50/10 p-4'
    }, [
      h('div', { class: 'text-[10px] font-bold text-purple-600 uppercase tracking-wide mb-2' }, 'Mermaid 流程圖即時渲染：'),
      h('div', {
        class: 'flex justify-center bg-white p-4 border border-neutral-100 rounded-lg overflow-x-auto',
        innerHTML: svgHtml.value
      })
    ])
  }
})

const FeasibilityRadarChart = defineComponent({
  props: {
    scores: { type: Object, required: true }
  },
  setup(props) {
    const canvasRef = ref(null)
    let chartInstance = null

    const renderChart = async () => {
      if (!canvasRef.value) return
      
      const loadC = () => {
        if (window.Chart) return Promise.resolve(window.Chart)
        return new Promise(r => setTimeout(() => r(loadC()), 200))
      }
      
      const Chart = await loadC()
      
      if (chartInstance) {
        chartInstance.destroy()
      }

      const ctx = canvasRef.value.getContext('2d')
      chartInstance = new Chart(ctx, {
        type: 'radar',
        data: {
          labels: ['Laravel 整合度', 'Vue 匹配密合度', '資料庫適配性', '技術團隊維護度', '開發迭代效能'],
          datasets: [{
            label: '技術可行性分析評分 (越接近 Laravel Vue 越高分)',
            data: [
              props.scores.laravel_integration || 80,
              props.scores.vue_compatibility || 80,
              props.scores.database_performance || 80,
              props.scores.maintenance_ease || 80,
              props.scores.development_speed || 80
            ],
            backgroundColor: 'rgba(139, 92, 246, 0.2)',
            borderColor: 'rgb(139, 92, 246)',
            pointBackgroundColor: 'rgb(139, 92, 246)',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: 'rgb(139, 92, 246)',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            r: {
              angleLines: { display: true },
              suggestedMin: 0,
              suggestedMax: 100,
              ticks: { stepSize: 20 }
            }
          }
        }
      })
    }

    onMounted(renderChart)
    watch(() => props.scores, renderChart, { deep: true })

    return () => h('div', {
      class: 'mt-4 border border-blue-100 rounded-xl bg-blue-50/10 p-4'
    }, [
      h('div', { class: 'text-[10px] font-bold text-blue-600 uppercase tracking-wide mb-2' }, '技術可行性評分雷達圖 (Chart.js Radar)：'),
      h('div', { class: 'w-full h-72 bg-white p-4 border border-neutral-100 rounded-lg' }, [
        h('canvas', { ref: canvasRef })
      ])
    ])
  }
})

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

const handleUpdateText = (nodeId, newText) => {
  updateNodeText(nodeId, newText)
}

const handleClear = () => {
  if (confirm('您確定要清除所有子項目嗎？')) {
    deleteNode('root')
  }
}

const handleSelectNode = ({ id, isMulti }) => {
  selectNode(id, isMulti)
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
  stageOutputs.value = ['', '', '', '', '', '', '', '', '', '', '', '']
  multiStageActions.value = []
  stagesProgress.value.forEach(s => s.status = 'idle')
  stageIsThinking.value = [false, false, false, false, false, false, false, false, false, false, false, false]
  stageThoughts.value = ['', '', '', '', '', '', '', '', '', '', '', '']
  showThoughtsCollapse.value = [false, false, false, false, false, false, false, false, false, false, false, false]
}

// 12-Stage Sequential progressive AI reasoning pipeline
const runMultiStageAnalysis = async () => {
  hasStartedMultiStage.value = true
  isMultiStageRunning.value = true
  
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

  // 12 Stage Prompts Config
  const stagePrompts = [
    `【第 1 層：看圖說故事 (系統架構意圖與概覽)】
請依據當前設計文件的完整心智圖結構（JSON）以及當前選取的節點「${nodeText}」，分析本節點在整個系統架構中的核心定位、意圖與系統整體概覽。`,
    
    `【第 2 層：商業分析與業務價值規劃】
請為此節點「${nodeText}」進行「商業分析（Business Analysis）」。請從商業變現路徑、目標客群、潛在業務價值、以及為使用者帶來的關鍵商業價值等維度，撰寫一份精簡的商業分析報告。`,

    `【第 3 層：開發技術棧與語言適配分析】
請針對當前節點「${nodeText}」評估開發所需要的「語言與技術棧（Language & Stack）」。分析現有的 Laravel、Vue.js、Tailwind CSS 是否能滿足所有功能需求，並判斷是否必須引入 Python（例如做機器學習、大數據處理、爬蟲）或其他後端語言。
其中，在資料庫選型部分，請評估 2-3 種主流資料庫（如 MySQL, PostgreSQL, MongoDB, Redis 等）之優缺點，給出明確的比分對照，並基於評分推薦最適合的資料庫選項。
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
特別規定：在線條上必須清晰使用關聯基數符號標示其關聯性，例如：一對多 (||--o{)、一對一 (||--||)、多對多 (}|--|{) 等關聯，並撰寫簡短的欄位與關聯規劃解說。`,
    
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
    for (let i = 0; i < 12; i++) {
      stagesProgress.value[i].status = 'running'
      stageIsThinking.value[i] = true
      
      const accumulatedContext = stageOutputs.value
        .map((out, idx) => out ? `### ${stagesProgress.value[idx].name}\n${out}` : '')
        .filter(Boolean)
        .join('\n\n')

      const currentPrompt = `當前整個心智圖設計文件的完整 JSON 結構如下：
\`\`\`json
${fullMindmapJson}
\`\`\`

我們目前已經累積的分析報告如下：
\`\`\`markdown
${accumulatedContext}
\`\`\`

請接續上面的分析報告，依據以下指示與風格設定撰寫本次部分的報告內容：
${stagePrompts[i]}

重要規定與表達風格：
${customStylePrompt}
- 請務必且只能使用「繁體中文」(Traditional Chinese) 進行文字回答。直接開始回答，不要有任何客套話。`

      try {
        const res = await fetch(`${apiEndpointVal}/v1/chat/completions`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
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
      } catch (innerError) {
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
        <div class="w-7 h-7 rounded bg-neutral-900 flex items-center justify-center text-white font-black text-sm">
          M
        </div>
        <div>
          <h1 class="text-sm font-semibold tracking-tight text-neutral-800">極簡心智圖設計文件</h1>
          <p class="text-[10px] text-neutral-400">設計文件藍圖編輯器</p>
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
            class="flex items-center gap-1.5 bg-neutral-900 hover:bg-neutral-800 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
          >
            <DownloadIcon class="w-3.5 h-3.5" />
            <span>匯出 JSON</span>
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
                @click="batchColorChange(color)"
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
          @update-text="data => handleUpdateText(data.id, data.text)"
          @toggle-expand="toggleNodeExpand"
        />

        <!-- Collapsible Bottom Node Details & Execution Plan Drawer -->
        <transition
          enter-active-class="transition-all duration-300 ease-out"
          leave-active-class="transition-all duration-200 ease-in"
          enter-from-class="translate-y-full opacity-0"
          leave-to-class="translate-y-full opacity-0"
        >
          <div 
            v-if="showDetailsPanel && selectedNode"
            class="h-72 bg-white border-t border-neutral-100 flex flex-col shrink-0 relative z-20 shadow-lg no-select"
          >
            <!-- Drawer Header -->
            <div class="h-10 px-6 border-b border-neutral-100 bg-neutral-50/50 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: selectedNode.color }"></div>
                <span class="text-xs font-semibold text-neutral-700">節點設計明細與實作計劃：{{ selectedNode.text }}</span>
              </div>
              <div class="flex items-center gap-3">
                <button 
                  @click="generateNodeDetails"
                  :disabled="aiDetailsLoading"
                  class="flex items-center gap-1.5 px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-[10px] font-semibold transition-colors disabled:opacity-40 disabled:pointer-events-none"
                >
                  <SparklesIcon class="w-3 h-3 animate-pulse" />
                  <span>{{ aiDetailsLoading ? 'AI 正在撰寫計畫...' : '🤖 AI 產生詳細實作計劃與 Mermaid 流程圖' }}</span>
                </button>
                <button 
                  @click="showDetailsPanel = false"
                  class="p-0.5 hover:bg-neutral-200/50 rounded-md text-neutral-400 hover:text-neutral-700"
                >
                  <ChevronDownIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Drawer Textarea -->
            <div class="flex-1 p-4">
              <textarea 
                v-model="selectedNode.details"
                @input="e => updateNodeDetails(selectedNode.id, e.target.value)"
                placeholder="貼入或在這裡撰寫該節點的詳細功能描述、規格說明、執行計劃、或 Mermaid 流程代碼..."
                class="w-full h-full p-3 bg-neutral-50 border border-neutral-100 rounded-xl focus:outline-none focus:border-neutral-200 font-mono text-xs text-neutral-700 placeholder:text-neutral-300 resize-none select-text"
              ></textarea>
            </div>
          </div>
        </transition>
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
          @ai-proposals="onAiProposals"
          @trigger-multistage="triggerMultiStageSetup"
          @add-children="handleAddChildren"
          @update-text="handleUpdateText"
        />
      </transition>
    </div>

    <!-- AI Suggestion Modal -->
    <transition
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-200 ease-in"
      enter-from-class="opacity-0 scale-95"
      leave-to-class="opacity-0 scale-95"
    >
      <div 
        v-if="showAiModal"
        class="fixed inset-0 bg-neutral-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-6 no-select"
      >
        <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[85vh] shadow-2xl flex flex-col overflow-hidden border border-neutral-100">
          <div class="p-5 border-b border-neutral-100 flex items-center justify-between shrink-0 bg-neutral-50/50">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-purple-600 flex items-center justify-center text-white">
                <SparklesIcon class="w-4 h-4 animate-pulse" />
              </div>
              <div class="flex items-center justify-between">
                <button @click="toggleAllProposals(true)" class="text-[10px] text-purple-600 hover:underline font-semibold">全選</button>
                <span class="text-neutral-300 text-[10px]">|</span>
                <button @click="toggleAllProposals(false)" class="text-[10px] text-neutral-500 hover:underline font-semibold">全不選</button>
              </div>
            </div>

            <div class="flex-1 overflow-y-auto space-y-2.5 pr-1">
              <div 
                v-for="proposal in aiProposalActions" 
                :key="proposal.id"
                class="flex items-start gap-3 p-3 rounded-xl border border-neutral-100 hover:border-neutral-200 transition-all"
                :class="proposal.selected ? 'bg-white shadow-sm' : 'bg-neutral-50/50 opacity-60'"
              >
                <input v-model="proposal.selected" type="checkbox" class="mt-1 rounded text-purple-600 border-neutral-300 focus:ring-purple-400 w-4 h-4 cursor-pointer" />
                <div class="flex-1 text-xs">
                  <div class="flex items-center gap-1.5 mb-1.5">
                    <span v-if="proposal.type === 'add'" class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">新增</span>
                    <span v-else-if="proposal.type === 'delete'" class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-red-50 text-red-700 border border-red-100">刪除</span>
                    <span v-else-if="proposal.type === 'update'" class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-amber-50 text-amber-700 border border-amber-100">修改</span>
                    <span class="text-[10px] text-neutral-400">目標位置: 「{{ proposal.target }}」</span>
                  </div>
                  <div class="text-neutral-700 font-medium">
                    <template v-if="proposal.type === 'add'">在「{{ proposal.target }}」底下新增子項目：「{{ proposal.text }}」</template>
                    <template v-else-if="proposal.type === 'delete'">將節點「{{ proposal.target }}」及其所有子項目刪除</template>
                    <template v-else-if="proposal.type === 'update'">將節點「{{ proposal.target }}」文字修改為：「{{ proposal.text }}」</template>
                  </div>
                </div>
              </div>
              <div v-if="aiProposalActions.length === 0" class="h-48 flex flex-col items-center justify-center border border-dashed border-neutral-200 rounded-xl text-neutral-400 gap-1.5">
                <AlertIcon class="w-5 h-5 text-neutral-300" />
                <span class="text-xs">AI 報告無提出結構性的調整清單</span>
              </div>
            </div>
          </div>
        </div>

        <div class="p-5 border-t border-neutral-100 flex items-center justify-between bg-neutral-50/50 shrink-0">
          <span class="text-xs text-neutral-500">已選取套用 <strong class="text-purple-600 font-semibold">{{ selectedProposalsCount }}</strong> / {{ aiProposalActions.length }} 個變更動作</span>
          <div class="flex items-center gap-3">
            <button @click="showAiModal = false" class="px-4 py-2 border border-neutral-200 hover:bg-neutral-100 text-neutral-700 rounded-xl text-xs font-semibold transition-colors">取消</button>
            <button @click="applySelectedProposals" :disabled="selectedProposalsCount === 0" class="px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition-colors disabled:opacity-40 disabled:pointer-events-none flex items-center gap-1.5"><CheckIcon class="w-3.5 h-3.5" /><span>套用所選變更</span></button>
          </div>
        </div>
      </div>
    </transition>

    <!-- 12-Stage Progressive Multi-stage reasoning Modal -->
    <transition
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-200 ease-in"
      enter-from-class="opacity-0 scale-95"
      leave-to-class="opacity-0 scale-95"
    >
      <div 
        v-if="showMultiStageModal"
        class="fixed inset-0 bg-neutral-900/40 backdrop-blur-sm z-50 flex items-center justify-center md:p-6 no-select"
      >
        <div class="bg-white md:rounded-2xl w-full h-full md:h-[85vh] md:w-4/5 shadow-2xl flex flex-col overflow-hidden border border-neutral-100">
          <!-- Header -->
          <div class="p-4 md:p-5 border-b border-neutral-100 flex items-center justify-between shrink-0 bg-neutral-50/50">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-purple-600 flex items-center justify-center text-white">
                <SparklesIcon class="w-4 h-4 animate-pulse" />
              </div>
              <div class="flex flex-col md:flex-row md:items-center md:gap-3">
                <h2 class="text-sm font-semibold text-neutral-800">Laravel + Vue 混合架構十二層深度分析區</h2>
                <div class="flex items-center gap-2 mt-1 md:mt-0">
                  <p class="text-[10px] md:text-[11px] text-neutral-400 m-0">分層級與客製化偏好，簡化評估、做法、細節與圖表</p>
                  <button 
                    @click="showProgressSidebar = !showProgressSidebar"
                    class="px-2 py-0.5 border border-purple-200 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded text-[10px] font-semibold transition-colors"
                  >
                    {{ showProgressSidebar ? '◀ 收起進度' : '▶ 展開進度' }}
                  </button>
                </div>
              </div>
            </div>
            <button 
              @click="showMultiStageModal = false"
              :disabled="isMultiStageRunning"
              class="p-1 hover:bg-neutral-200/50 rounded-lg text-neutral-400 hover:text-neutral-700 transition-colors disabled:opacity-30 disabled:pointer-events-none"
            >
              <CloseIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Pre-flight settings screen (before analysis starts) -->
          <div v-if="!hasStartedMultiStage" class="flex-1 p-4 md:p-8 flex flex-col items-center justify-center bg-neutral-50/30 overflow-y-auto">
            <div class="bg-white border border-neutral-100 p-5 md:p-6 rounded-2xl shadow-sm w-full max-w-xl space-y-6">
              <div class="text-center space-y-1.5">
                <h3 class="text-sm font-bold text-neutral-800">第一步：配置分析設定與偏好</h3>
                <p class="text-xs text-neutral-400">客製化 AI 在這十二層架構中的解說與表達口吻</p>
              </div>

              <div class="space-y-4">
                <!-- User role choice -->
                <div class="space-y-2">
                  <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wider">讀者身份與專業度</label>
                  <div class="grid grid-cols-2 gap-3">
                    <button 
                      @click="isUserEngineer = true"
                      class="px-4 py-2.5 border rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition-all"
                      :class="isUserEngineer ? 'bg-neutral-900 border-neutral-900 text-white shadow-sm' : 'border-neutral-200 hover:bg-neutral-50 text-neutral-700'"
                    >
                      <span>專業工程師</span>
                    </button>
                    <button 
                      @click="isUserEngineer = false"
                      class="px-4 py-2.5 border rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition-all"
                      :class="!isUserEngineer ? 'bg-neutral-900 border-neutral-900 text-white shadow-sm' : 'border-neutral-200 hover:bg-neutral-50 text-neutral-700'"
                    >
                      <span>非工程師 (通俗語言)</span>
                    </button>
                  </div>
                </div>

                <!-- MBTI Style Dropdown -->
                <div class="space-y-2">
                  <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wider">MBTI 性格口吻表達風格</label>
                  <select 
                    v-model="mbtiStyle"
                    class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-2.5 text-xs text-neutral-700 font-medium focus:outline-none focus:border-neutral-300 transition-colors"
                  >
                    <option v-for="opt in mbtiOptions" :key="opt.value" :value="opt.value">
                      {{ opt.label }}
                    </option>
                  </select>
                </div>

                <!-- AI Server Configuration Settings -->
                <div class="grid grid-cols-2 gap-3.5">
                  <div class="space-y-2">
                    <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wider">AI API 端點 URL</label>
                    <input 
                      v-model="apiEndpoint"
                      type="text"
                      class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-2.5 text-xs text-neutral-700 font-medium focus:outline-none focus:border-neutral-300 transition-colors"
                      placeholder="http://127.0.0.1:8888"
                    />
                  </div>
                  <div class="space-y-2">
                    <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wider">AI API 模型名稱</label>
                    <input 
                      v-model="apiModel"
                      type="text"
                      class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-2.5 text-xs text-neutral-700 font-medium focus:outline-none focus:border-neutral-300 transition-colors"
                      placeholder="model-name"
                    />
                  </div>
                </div>

                <div class="p-3.5 bg-neutral-50 rounded-xl border border-neutral-100 text-xs text-neutral-500 space-y-1 leading-relaxed">
                  <div class="font-semibold text-neutral-600">技術框架規範：</div>
                  <div>- 分析中提供之範例代碼將優先包含 <strong>HTML, Bootstrap, jQuery</strong> 及 Vue 混合架構。</div>
                  <div>- 系統將自動理性評估適用之資料庫優缺點並比分，以產出最契合的資料表 Migration 與 ER 圖。</div>
                </div>
              </div>

              <!-- Start trigger -->
              <button 
                @click="runMultiStageAnalysis"
                class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-xl text-xs flex items-center justify-center gap-2 shadow-md hover:shadow-lg transition-all"
              >
                <SparklesIcon class="w-4 h-4 animate-pulse" />
                <span>🚀 開始進行十二層架構可行性分析</span>
              </button>
            </div>
          </div>

          <!-- Body (Main progressive report) -->
          <div v-else class="flex-1 flex flex-col md:flex-row overflow-hidden min-h-0">
            <!-- Left Side: Progressive checklist -->
            <div v-if="showProgressSidebar" class="w-full md:w-1/3 p-4 md:p-6 border-b md:border-b-0 md:border-r border-neutral-100 flex flex-col gap-3 bg-neutral-50/20 shrink-0 font-sans max-h-[35vh] md:max-h-full">
              <h3 class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-1">分析進度追蹤</h3>
              
              <div class="space-y-3 overflow-y-auto pr-1 flex-1">
                <div 
                  v-for="stage in stagesProgress" 
                  :key="stage.id"
                  class="flex items-start gap-3 p-3 rounded-xl border transition-all"
                  :class="[
                    stage.status === 'running' ? 'bg-purple-50/50 border-purple-200 ring-1 ring-purple-100' : '',
                    stage.status === 'success' ? 'bg-emerald-50/30 border-emerald-100 opacity-90' : '',
                    stage.status === 'idle' ? 'bg-white border-neutral-100 opacity-60' : ''
                  ]"
                >
                  <!-- Status Indicator -->
                  <div class="mt-0.5 shrink-0">
                    <SpinnerIcon v-if="stage.status === 'running'" class="w-4 h-4 text-purple-600 animate-spin" />
                    <div 
                      v-else-if="stage.status === 'success'"
                      class="w-4 h-4 rounded-full bg-emerald-500 flex items-center justify-center text-white"
                    >
                      <CheckIcon class="w-3 h-3 font-bold" />
                    </div>
                    <div 
                      v-else
                      class="w-4 h-4 rounded-full border border-neutral-300 bg-white"
                    ></div>
                  </div>

                  <!-- Text -->
                  <div>
                    <div 
                      class="font-medium text-xs text-neutral-700"
                      :class="[
                        stage.status === 'running' ? 'text-purple-700 font-semibold' : '',
                        stage.status === 'success' ? 'text-emerald-800' : ''
                      ]"
                    >
                      {{ stage.name }}
                    </div>
                    <div class="text-[10px] text-neutral-400 mt-1">
                      <span v-if="stage.status === 'running'" class="text-purple-500 font-medium">分析中...</span>
                      <span v-else-if="stage.status === 'success'" class="text-emerald-600">已完成</span>
                      <span v-else>等待中</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Side: VISUALLY SEPARATED Cards with HTML Previews, Math, and Mermaid Renderers -->
            <div class="w-full md:flex-1 flex flex-col min-h-0 bg-neutral-50/30 font-sans">
              <!-- Report Area using clean Card Layout -->
              <div class="flex-1 p-4 md:p-6 overflow-y-auto space-y-6 min-h-0">
                <h3 class="text-xs font-semibold text-neutral-400 uppercase tracking-wider mb-2">分層架構分析報告明細</h3>
                
                <div class="space-y-6">
                  <div 
                    v-for="(stage, idx) in stagesProgress" 
                    :key="stage.id"
                    class="border border-neutral-200/60 rounded-xl overflow-hidden shadow-sm bg-white"
                  >
                    <!-- Stage Title Header -->
                    <div class="bg-neutral-50/80 px-4 py-3 border-b border-neutral-100 flex items-center justify-between text-xs font-semibold text-neutral-700">
                      <span class="flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded-full bg-neutral-200 text-neutral-700 flex items-center justify-center text-[9px]">{{ idx + 1 }}</span>
                        <span>{{ stage.name }}</span>
                      </span>
                      <span :class="stage.status === 'success' ? 'text-emerald-600' : (stage.status === 'running' ? 'text-purple-600 animate-pulse' : 'text-neutral-400')">
                        {{ stage.status === 'running' ? '正在生成...' : (stage.status === 'success' ? '已完成' : '等待上游階段...') }}
                      </span>
                    </div>
                    
                    <!-- Stage Content -->
                    <div class="p-4 text-xs leading-relaxed text-neutral-700 bg-white relative">
                      
                      <!-- 1. Active Thinking Panel (Streaming text visible, with a cool frosted/foggy glass overlay) -->
                      <div v-if="stageIsThinking[idx] && stageThoughts[idx]" class="relative border border-purple-100/70 rounded-xl bg-purple-50/5 p-4 mb-4 overflow-hidden">
                        <!-- Frosted/Foggy background overlay -->
                        <div class="absolute inset-0 bg-white/40 backdrop-blur-[1px] flex items-center justify-center pointer-events-none select-none">
                          <div class="bg-white/95 border border-purple-200/50 px-3 py-1 rounded-full shadow-sm flex items-center gap-1.5 animate-pulse">
                            <SpinnerIcon class="w-3.5 h-3.5 text-purple-600 animate-spin" />
                            <span class="text-[10px] font-bold text-purple-700">AI 思考中...</span>
                          </div>
                        </div>
                        <!-- Legible streaming text -->
                        <div class="text-[11px] font-mono text-neutral-500 italic whitespace-pre-wrap leading-relaxed select-text">
                          {{ stageThoughts[idx] }}
                        </div>
                      </div>

                      <!-- 2. Collapsed Thinking Trace (Finished thinking, show elegant collapse button) -->
                      <div v-if="stageThoughts[idx] && !stageIsThinking[idx]" class="mb-4">
                        <button 
                          @click="showThoughtsCollapse[idx] = !showThoughtsCollapse[idx]"
                          class="inline-flex items-center gap-1.5 px-3 py-1 bg-neutral-50 hover:bg-neutral-100 border border-neutral-200 text-neutral-600 hover:text-neutral-800 rounded-lg text-[10px] font-medium transition-colors select-none"
                        >
                          <span>🧠</span>
                          <span>{{ showThoughtsCollapse[idx] ? '收起思考軌跡' : '展開思考軌跡' }}</span>
                          <ChevronUpIcon v-if="showThoughtsCollapse[idx]" class="w-3 h-3 text-neutral-400" />
                          <ChevronDownIcon v-else class="w-3 h-3 text-neutral-400" />
                        </button>
                        
                        <div 
                          v-if="showThoughtsCollapse[idx]"
                          class="mt-2 p-3 bg-neutral-50 border border-neutral-200/70 rounded-lg text-[11px] font-mono text-neutral-500 italic whitespace-pre-wrap leading-relaxed select-text"
                        >
                          {{ stageThoughts[idx] }}
                        </div>
                      </div>

                      <!-- Render text with KaTeX formulas and Code Blocks processed -->
                      <div 
                        v-if="stageOutputs[idx]"
                        class="whitespace-pre-wrap select-text text-neutral-700 font-sans"
                        v-html="parseAndRenderContent(stageOutputs[idx]).parsedText"
                      ></div>
                      
                      <div v-else-if="stage.status === 'running' && !stageIsThinking[idx]" class="text-purple-400 font-medium animate-pulse flex items-center gap-2">
                        <SpinnerIcon class="w-3.5 h-3.5 animate-spin" />
                        <span>AI 正在撰寫本層技術分析，請稍候...</span>
                      </div>
                      <span v-else-if="stage.status === 'idle'" class="text-neutral-300 italic">等待上游階段完成後解鎖...</span>

                      <!-- Stage 12 Copy Button (Prompt Clipboard Integration) -->
                      <div v-if="idx === 11 && stageOutputs[idx] && !stageIsThinking[idx]" class="mt-4">
                        <button 
                          @click="copyToClipboard(stageOutputs[idx])"
                          class="flex items-center gap-1.5 px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold shadow-md hover:shadow-lg transition-all"
                        >
                          <span>📋 複製 AI Agent 開發指令 Prompt</span>
                        </button>
                      </div>

                      <!-- Render Rubric Score UI for this stage if parsed -->
                      <div 
                        v-if="stageOutputs[idx] && parsedStageScores[idx].score > 0" 
                        class="mt-4 p-3 border border-purple-200 bg-purple-50/20 rounded-xl flex items-center justify-between"
                      >
                        <div class="flex-1 pr-4">
                          <div class="text-[10px] font-bold text-purple-600 uppercase tracking-wide">本層指標可行性評分 (Rubric)</div>
                          <div class="text-xs text-neutral-600 mt-1 font-medium">{{ parsedStageScores[idx].reason }}</div>
                        </div>
                        <div class="shrink-0 flex items-baseline gap-0.5 bg-purple-600 text-white px-3 py-1.5 rounded-lg shadow-sm font-sans">
                          <span class="text-base font-black leading-none">{{ parsedStageScores[idx].score }}</span>
                          <span class="text-[9px] opacity-75">/ 10 分</span>
                        </div>
                      </div>

                      <!-- Render Feasibility Radar Chart if scores are detected -->
                      <FeasibilityRadarChart 
                        v-if="stageOutputs[idx] && parseAndRenderContent(stageOutputs[idx]).radarScores" 
                        :scores="parseAndRenderContent(stageOutputs[idx]).radarScores" 
                      />

                      <!-- Render Dynamic Mermaid Diagram if Mermaid flowchart is detected in output -->
                      <MermaidRender 
                        v-if="stageOutputs[idx] && parseAndRenderContent(stageOutputs[idx]).mermaidCode" 
                        :code="parseAndRenderContent(stageOutputs[idx]).mermaidCode" 
                        :id="stage.id" 
                      />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Proposals Area -->
              <div v-if="multiStageActions.length > 0 && !isMultiStageRunning" class="p-4 md:p-6 shrink-0 bg-white border-t border-neutral-100 shadow-inner">
                <div class="flex items-center justify-between mb-3">
                  <h3 class="text-xs font-semibold text-neutral-800 flex items-center gap-1">
                    <SparklesIcon class="w-3.5 h-3.5 text-purple-600" />
                    <span>AI 建議新增之心智圖子節點：</span>
                  </h3>
                  <div class="flex items-center gap-2">
                    <button @click="toggleAllMultiProposals(true)" class="text-[10px] text-purple-600 hover:underline font-semibold">全選</button>
                    <span class="text-neutral-300 text-[10px]">|</span>
                    <button @click="toggleAllMultiProposals(false)" class="text-[10px] text-neutral-500 hover:underline font-semibold">全不選</button>
                  </div>
                </div>
                
                <div class="max-h-24 md:max-h-32 overflow-y-auto space-y-2">
                  <div 
                    v-for="prop in multiStageActions" 
                    :key="prop.id"
                    class="flex items-center gap-2.5 p-2 bg-neutral-50 rounded-lg border border-neutral-100 text-xs"
                  >
                    <input v-model="prop.selected" type="checkbox" class="rounded text-purple-600 border-neutral-300 w-3.5 h-3.5 cursor-pointer" />
                    <span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">新增</span>
                    <span class="text-neutral-700 font-medium">在「{{ prop.target }}」下新增子節點「{{ prop.text }}」</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="p-5 border-t border-neutral-100 flex items-center justify-between bg-neutral-50/50 shrink-0">
            <div class="flex items-center gap-4 flex-1">
              <span class="text-xs text-neutral-500">
                <template v-if="!hasStartedMultiStage">
                  配置偏好後即可啟動深度技術分析
                </template>
                <template v-else-if="isMultiStageRunning">
                  正在為您進行第 <strong class="text-purple-600 font-semibold">{{ stagesProgress.filter(s => s.status === 'success').length + 1 }}</strong> / 12 層深度技術分析...
                </template>
                <template v-else>
                  十二層深度分析已順利完成！已選取套用 <strong class="text-purple-600 font-semibold">{{ selectedMultiProposalsCount }}</strong> / {{ multiStageActions.length }} 個結構調整
                </template>
              </span>
              
              <!-- Grand Total Feasibility Score display -->
              <div v-if="hasStartedMultiStage && !isMultiStageRunning" class="flex items-center gap-3 bg-neutral-100 px-3.5 py-1.5 rounded-xl border border-neutral-200/50">
                <div class="text-xs font-bold text-neutral-700">綜合評估總分數：</div>
                <div class="flex items-baseline gap-0.5">
                  <span class="text-base font-black text-purple-700 leading-none">{{ grandTotalScore }}</span>
                  <span class="text-[10px] text-neutral-400">/ 110 滿分</span>
                </div>
                <!-- Mini Progress Bar scaled to 110 -->
                <div class="w-16 h-2 bg-neutral-200 rounded-full overflow-hidden">
                  <div class="h-full bg-purple-600 transition-all duration-500" :style="{ width: (grandTotalScore / 1.1) + '%' }"></div>
                </div>
              </div>
            </div>
            
            <div class="flex items-center gap-3">
              <button 
                @click="showMultiStageModal = false"
                :disabled="isMultiStageRunning"
                class="px-4 py-2 border border-neutral-200 hover:bg-neutral-100 text-neutral-700 rounded-xl text-xs font-semibold transition-colors disabled:opacity-30 disabled:pointer-events-none"
              >
                關閉
              </button>
              <button 
                @click="applyMultiStageProposals"
                :disabled="!hasStartedMultiStage || isMultiStageRunning || selectedMultiProposalsCount === 0"
                class="px-5 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition-colors disabled:opacity-40 disabled:pointer-events-none flex items-center gap-1.5"
              >
                <CheckIcon class="w-3.5 h-3.5" />
                <span>套用選取建議至心智圖</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
    
    <!-- Import Raw Code Modal -->
    <transition
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-200 ease-in"
      enter-from-class="opacity-0 scale-95"
      leave-to-class="opacity-0 scale-95"
    >
      <div 
        v-if="showImportCodeModal"
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
            <button @click="showImportCodeModal = false" class="p-1 hover:bg-neutral-200/50 rounded-lg text-neutral-400 hover:text-neutral-700 transition-colors">
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
            <button @click="showImportCodeModal = false" class="px-4 py-2 border border-neutral-200 hover:bg-neutral-100 text-neutral-700 rounded-xl text-xs font-semibold transition-colors">取消</button>
            <button @click="handleImportCode" class="px-5 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5"><CheckIcon class="w-3.5 h-3.5" /><span>載入至畫布</span></button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>
