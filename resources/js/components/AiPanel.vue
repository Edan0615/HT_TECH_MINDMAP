<template>
  <div class="h-full flex flex-col bg-white border-l border-neutral-100 w-80 text-sm no-select">
    <!-- Header -->
    <div class="p-4 border-b border-neutral-100 flex items-center justify-between">
      <div class="flex items-center gap-2 font-medium text-neutral-800">
        <SparklesIcon class="w-4 h-4 text-purple-600 animate-pulse" />
        <span>AI 協同設計師</span>
      </div>
      <button 
        @click="showSettings = !showSettings" 
        class="text-neutral-400 hover:text-neutral-600 transition-colors p-1 rounded hover:bg-neutral-50"
        title="設定"
      >
        <SettingsIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Settings (Collapsible) -->
    <div v-if="showSettings" class="p-4 border-b border-neutral-100 bg-neutral-50/50 space-y-3">
      <div>
        <label class="block text-xs text-neutral-500 mb-1">API 端點</label>
        <input 
          v-model="apiEndpoint" 
          type="text" 
          class="w-full px-2 py-1 bg-white border border-neutral-200 rounded text-xs focus:outline-none focus:border-purple-400"
          placeholder="http://127.0.0.1:8888"
        />
      </div>
      <div>
        <label class="block text-xs text-neutral-500 mb-1">模型名稱</label>
        <input 
          v-model="apiModel" 
          type="text" 
          class="w-full px-2 py-1 bg-white border border-neutral-200 rounded text-xs focus:outline-none focus:border-purple-400"
          placeholder="model-name"
        />
      </div>
      <div>
        <label class="block text-xs text-neutral-500 mb-1">系統提示詞 (System Prompt)</label>
        <textarea 
          v-model="systemPrompt" 
          rows="3"
          class="w-full px-2 py-1 bg-white border border-neutral-200 rounded text-xs focus:outline-none focus:border-purple-400 resize-none"
        ></textarea>
      </div>
    </div>

    <!-- Active Node Info -->
    <div class="p-4 border-b border-neutral-100 bg-purple-50/30">
      <div class="text-xs text-neutral-400 mb-1">已選取的節點</div>
      <div class="font-medium text-neutral-800 line-clamp-2">
        {{ selectedNode ? selectedNode.text : '未選取節點' }}
      </div>
    </div>

    <!-- Toggle for allowAiReadCode -->
    <div class="px-4 py-2.5 bg-neutral-50/50 border-b border-neutral-100 flex items-center justify-between select-none">
      <span class="text-xs text-neutral-500 font-semibold">附加專案代碼 Context</span>
      <label class="relative inline-flex items-center cursor-pointer">
        <input 
          type="checkbox" 
          v-model="allowAiReadCode" 
          class="sr-only peer"
        />
        <div class="w-9 h-5 bg-neutral-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-purple-600"></div>
      </label>
    </div>

    <!-- Project and File Info display -->
    <div v-if="allowAiReadCode" class="px-4 py-2 bg-purple-50/30 border-b border-neutral-100 space-y-1 text-[10px] text-neutral-500 font-mono">
      <div class="flex items-center justify-between">
        <span>🎯 專案名稱:</span>
        <span class="font-bold text-purple-700">{{ selectedProject || 'beartor' }}</span>
      </div>
      <div class="flex items-center justify-between">
        <span>📄 檢視檔案:</span>
        <span class="truncate max-w-[150px] font-bold text-neutral-700" :title="selectedFile?.relative_path || '未選擇'">{{ selectedFile ? selectedFile.name : '未選擇' }}</span>
      </div>
    </div>

    <!-- Actions / Prompts -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
      <div class="space-y-2">
        <div class="text-xs font-medium text-neutral-400 uppercase tracking-wider">預設分析任務</div>
        
        <!-- Laravel + Vue Hybrid Architecture Analysis Preset (emits to App.vue for 5-stage modal pipeline) -->
        <button 
          @click="$emit('trigger-multistage')" 
          :disabled="!selectedNode || loading"
          class="w-full text-left px-3 py-2 border border-neutral-200 hover:border-purple-400 hover:bg-purple-50/20 rounded-lg transition-all flex items-center justify-between group disabled:opacity-50 disabled:pointer-events-none bg-purple-50/10 border-purple-100"
        >
          <div>
            <div class="font-semibold text-neutral-800 group-hover:text-purple-700 flex items-center gap-1">
              <SparklesIcon class="w-3.5 h-3.5 text-purple-500" />
              <span>Laravel + Vue 混合架構規劃</span>
            </div>
            <div class="text-xs text-neutral-400 font-medium text-purple-600">啟動五層深度分析 (Blade + MySQL)</div>
          </div>
          <ChevronRightIcon class="w-4 h-4 text-neutral-400 group-hover:text-purple-500" />
        </button>

        <button 
          @click="callAi('expand')" 
          :disabled="!selectedNode || loading"
          class="w-full text-left px-3 py-2 border border-neutral-200 hover:border-purple-400 hover:bg-purple-50/20 rounded-lg transition-all flex items-center justify-between group disabled:opacity-50 disabled:pointer-events-none"
        >
          <div>
            <div class="font-medium text-neutral-700 group-hover:text-purple-700">自動生成子結構建議</div>
            <div class="text-xs text-neutral-400">分析當前節點並提議新增多個相關子節點</div>
          </div>
          <ChevronRightIcon class="w-4 h-4 text-neutral-400 group-hover:text-purple-500" />
        </button>

        <button 
          @click="callAi('refine')" 
          :disabled="!selectedNode || loading"
          class="w-full text-left px-3 py-2 border border-neutral-200 hover:border-purple-400 hover:bg-purple-50/20 rounded-lg transition-all flex items-center justify-between group disabled:opacity-50 disabled:pointer-events-none"
        >
          <div>
            <div class="font-medium text-neutral-700 group-hover:text-purple-700">優化與精簡架構</div>
            <div class="text-xs text-neutral-400">識別贅餘節點，建議刪除或合併修改</div>
          </div>
          <ChevronRightIcon class="w-4 h-4 text-neutral-400 group-hover:text-purple-500" />
        </button>
      </div>

      <!-- Custom Prompt -->
      <div class="space-y-2 pt-2 border-t border-neutral-100">
        <div class="text-xs font-medium text-neutral-400 uppercase tracking-wider">自訂 AI 指令</div>
        <textarea 
          v-model="customPrompt" 
          rows="4" 
          placeholder="請輸入 AI 分析指令（例如：「請幫我看看這個系統架構是否缺少了監控系統，並建議新增對應的 node」）..."
          class="w-full p-2 border border-neutral-200 rounded-lg focus:outline-none focus:border-purple-400 resize-none text-neutral-700 placeholder:text-neutral-300"
        ></textarea>
        <button 
          @click="callAi('custom')" 
          :disabled="!selectedNode || !customPrompt.trim() || loading"
          class="w-full bg-neutral-900 text-white py-2 rounded-lg hover:bg-neutral-800 transition-colors font-medium flex items-center justify-center gap-2 disabled:opacity-50 disabled:pointer-events-none"
        >
          <SendIcon class="w-3.5 h-3.5" />
          <span>傳送指令</span>
        </button>
      </div>

      <!-- AI Response Container -->
      <div v-if="loading || aiResponse" class="mt-4 p-3 bg-neutral-50 rounded-lg border border-neutral-100 space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-neutral-400">AI 回應</span>
          <span class="text-xs text-purple-600 animate-pulse font-medium">{{ currentStage }}</span>
        </div>
        
        <div v-if="aiResponse" class="text-neutral-700 text-xs leading-relaxed max-h-48 overflow-y-auto whitespace-pre-wrap select-text">
          {{ aiResponse }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { 
  Sparkles as SparklesIcon, 
  Settings as SettingsIcon, 
  ChevronRight as ChevronRightIcon,
  Send as SendIcon
} from '@lucide/vue'

const props = defineProps({
  selectedNode: {
    type: Object,
    default: null
  },
  mindmap: {
    type: Object,
    default: null
  },
  apiEndpoint: {
    type: String,
    required: true
  },
  selectedProject: {
    type: String,
    default: 'beartor'
  },
  apiModel: {
    type: String,
    required: true
  },
  allowAiReadCode: {
    type: Boolean,
    default: false
  },
  selectedFile: {
    type: Object,
    default: null
  },
  selectedFileContent: {
    type: String,
    default: ''
  }
})

const emit = defineEmits([
  'ai-proposals', 
  'trigger-multistage', 
  'update:apiEndpoint', 
  'update:apiModel', 
  'update:allowAiReadCode'
])

const showSettings = ref(false)

const apiEndpoint = computed({
  get: () => props.apiEndpoint,
  set: (val) => {
    emit('update:apiEndpoint', val)
    localStorage.setItem('mindmap_ai_endpoint', val)
  }
})

const apiModel = computed({
  get: () => props.apiModel,
  set: (val) => {
    emit('update:apiModel', val)
    localStorage.setItem('mindmap_ai_model', val)
  }
})

const allowAiReadCode = computed({
  get: () => props.allowAiReadCode,
  set: (val) => {
    emit('update:allowAiReadCode', val)
  }
})

const systemPrompt = ref(
  '你是一位優秀的系統分析師與軟體架構師。請以繁體中文回答，先提供簡要分析報告，並必須在回答的最後附帶一個 JSON 格式的調整動作清單。該清單以標記 ```json 和 ``` 包裹。JSON 格式必須為一個陣列，包含 add (新增)、delete (刪除)、update (修改) 三種指令物件：\n[\n  { "type": "add", "target": "父節點名稱", "text": "新節點名稱" },\n  { "type": "delete", "target": "要刪除的節點名稱" },\n  { "type": "update", "target": "要修改的節點名稱", "text": "修改後的新名稱" }\n]'
)

const customPrompt = ref('')
const loading = ref(false)
const aiResponse = ref('')
const currentStage = ref('準備分析中...')

const callAi = async (mode) => {
  if (!props.selectedNode) return

  loading.value = true
  aiResponse.value = ''
  currentStage.value = '正在打包心智圖 JSON 結構...'

  localStorage.setItem('mindmap_ai_endpoint', apiEndpoint.value)
  localStorage.setItem('mindmap_ai_model', apiModel.value)

  let baseInstruction = ''
  if (mode === 'expand') {
    baseInstruction = `請針對目前選取的主題：「${props.selectedNode.text}」進行腦力激盪，建議多個適合的子元件、子主題。請規劃哪些應該「新增 (add)」，並在 JSON 列表中列出對應 of add 動作。`
  } else if (mode === 'refine') {
    baseInstruction = `請審視當前的心智圖結構（見下方的完整 JSON）。請指出哪裡可能有不合理、需要優化、需要「修改 (update)」或需要「刪除 (delete)」的節點。請給予簡短分析，並在 JSON 列表中列出對應的 delete 與 update 動作。`
  } else {
    baseInstruction = `當前選取的節點為：「${props.selectedNode.text}」。指令為：${customPrompt.value}。請根據此指令進行分析，並在回答末尾 the JSON 列表中給出對應的 add/delete/update 動作建議。`
  }

  const fullMindmapJson = props.mindmap ? JSON.stringify(props.mindmap, null, 2) : ''
  
  let finalPrompt = ''
  
  // Dynamic agentic file context selector
  if (props.allowAiReadCode) {
    currentStage.value = '正在讀取專案目錄結構樹...'
    try {
      const treeRes = await window.axios.post('/api/projects/tree', { project: props.selectedProject })
      if (treeRes.data.success && treeRes.data.files.length > 0) {
        const filesList = treeRes.data.files.map(f => f.relative_path)
        
        currentStage.value = 'AI 正在判斷需要精讀哪些檔案...'
        const preFlightRes = await fetch(`${apiEndpoint.value}/v1/chat/completions`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            model: apiModel.value,
            messages: [
              { 
                role: 'system', 
                content: '你是一個專案目錄結構預檢大師。請閱讀提供的檔案列表，依據使用者的任務指示，回傳一個包含最多 3 個必須精讀的檔案相對路徑之 JSON 陣列。請只回傳陣列 JSON本身，例如：["app/Http/Controllers/HomeController.php"]，絕不要包含任何其它解釋或 Markdown 語法外框。' 
              },
              { 
                role: 'user', 
                content: `專案檔案列表：\n${JSON.stringify(filesList.slice(0, 300))}\n\n任務指示：${baseInstruction}\n請回傳 JSON 陣列：` 
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
            // fallback parser
            filesList.forEach(path => {
              if (aiDecision.includes(path) && targetPaths.length < 3) {
                targetPaths.push(path)
              }
            })
          }
          
          if (targetPaths && targetPaths.length > 0) {
            targetPaths = targetPaths.slice(0, 3)
            currentStage.value = `正在讀取 AI 指定的檔案 (${targetPaths.length} 個)...`
            
            finalPrompt += `[CRITICAL SECURITY BOUNDARY] You are running in a strictly READ-ONLY sandbox. You cannot write/modify files. You can only analyze the following attached files context to answer:\n\n`
            
            for (const path of targetPaths) {
              try {
                const fileRes = await window.axios.post('/api/projects/read', {
                  project: props.selectedProject,
                  file_path: path
                })
                if (fileRes.data.success) {
                  finalPrompt += `==== 檔案: ${path} ====\n\`\`\`\n${fileRes.data.content}\n\`\`\`\n\n`
                }
              } catch (err) {
                console.error(`讀取專案檔案 ${path} 失敗:`, err)
              }
            }
          }
        }
      }
    } catch (err) {
      console.error('預檢專案檔案失敗:', err)
    }
  } else if (props.selectedFile && props.selectedFileContent) {
    // Fallback: If toggle is off but user manually selected a file, still inject that single file
    finalPrompt += `[CRITICAL SECURITY BOUNDARY] You are running in a strictly READ-ONLY sandbox tool. You can only read. Analyze this file context:\n`
    finalPrompt += `==== 檔案: ${props.selectedFile.relative_path} ====\n\`\`\`\n${props.selectedFileContent}\n\`\`\`\n\n`
  }

  finalPrompt += `當前整個心智圖設計文件的完整 JSON 結構如下：
\`\`\`json
${fullMindmapJson}
\`\`\`

任務指示：
${baseInstruction}

重要規定：
請務必且只能使用「繁體中文」(Traditional Chinese) 進行所有分析報告與調整節點文字的輸出，絕不可使用英文或簡體中文。`

  currentStage.value = '正在連線至 AI 伺服器並發送分析...'

  try {
    const res = await fetch(`${apiEndpoint.value}/v1/chat/completions`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        model: apiModel.value,
        messages: [
          { role: 'system', content: systemPrompt.value },
          { role: 'user', content: finalPrompt }
        ],
        temperature: 0.7,
        stream: true
      })
    })

    if (!res.ok) {
      throw new Error(`API 錯誤: ${res.status} ${res.statusText}`)
    }

    currentStage.value = 'AI 正在進行架構規劃 (分析中)...'

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
            const content = parsed.choices[0]?.delta?.content || ''
            aiResponse.value += content
          } catch (e) {}
        }
      }
    }

    if (buffer && buffer.startsWith('data: ')) {
      try {
        const parsed = JSON.parse(buffer.slice(6))
        const content = parsed.choices[0]?.delta?.content || ''
        aiResponse.value += content
      } catch (e) {}
    }

    currentStage.value = '正在解析結構化調整建議...'

    const rawText = aiResponse.value.trim()
    let report = rawText
    let actions = []
    
    const jsonMatch = rawText.match(/```json\s*([\s\S]*?)\s*```/)
    if (jsonMatch) {
      try {
        actions = JSON.parse(jsonMatch[1])
        report = rawText.replace(/```json[\s\S]*?```/, '').trim()
      } catch (e) {
        console.error('Failed to parse actions JSON', e)
      }
    } else {
      const bracketMatch = rawText.match(/\[\s*\{[\s\S]*?\}\s*\]/)
      if (bracketMatch) {
        try {
          actions = JSON.parse(bracketMatch[0])
          report = rawText.replace(/\[\s*\{[\s\S]*?\}\s*\]/, '').trim()
        } catch (e) {
          console.error(e)
        }
      }
    }

    emit('ai-proposals', { report, actions })
    currentStage.value = '分析完成！'
    customPrompt.value = ''
  } catch (error) {
    aiResponse.value = `與 AI 連線時發生錯誤: ${error.message}\n\n請確認位於 ${apiEndpoint.value} 的 AI API 伺服器正在運行，且允許來自瀏覽器的跨網域 (CORS) 請求。`
    currentStage.value = '連線失敗'
  } finally {
    loading.value = false
  }
}
</script>
