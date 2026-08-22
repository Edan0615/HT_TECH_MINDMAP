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
  apiModel: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['ai-proposals', 'trigger-multistage', 'update:apiEndpoint', 'update:apiModel'])

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
    baseInstruction = `當前選取的節點為：「${props.selectedNode.text}」。指令為：${customPrompt.value}。請根據此指令進行分析，並在回答末尾的 JSON 列表中給出對應的 add/delete/update 動作建議。`
  }

  const fullMindmapJson = props.mindmap ? JSON.stringify(props.mindmap, null, 2) : ''
  const finalPrompt = `當前整個心智圖設計文件的完整 JSON 結構如下：
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
