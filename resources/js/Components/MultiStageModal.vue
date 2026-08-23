<script setup>
import { ref, computed } from 'vue'
import { 
  Sparkles as SparklesIcon, 
  X as CloseIcon, 
  ChevronUp as ChevronUpIcon, 
  ChevronDown as ChevronDownIcon, 
  Check as CheckIcon,
  HelpCircle as HelpIcon,
  Loader2 as SpinnerIcon,
  Send as SendIcon,
  AlertCircle as AlertIcon
} from '@lucide/vue'
import FeasibilityRadarChart from './FeasibilityRadarChart.vue'
import MermaidRender from './MermaidRender.vue'

const props = defineProps({
  show: { type: Boolean, required: true },
  hasStartedMultiStage: { type: Boolean, required: true },
  isMultiStageRunning: { type: Boolean, required: true },
  isMultiStagePaused: { type: Boolean, required: true },
  stagesProgress: { type: Array, required: true },
  stageOutputs: { type: Array, required: true },
  stageLogs: { type: Array, required: true },
  stageThoughts: { type: Array, required: true },
  currentAnalyzingStageIndex: { type: Number, required: true },
  currentViewedStageIndex: { type: Number, required: true },
  multiStageStatusMessage: { type: String, required: true },
  multiStageActions: { type: Array, required: true },
  grandTotalScore: { type: Number, required: true },
  selectedMultiProposalsCount: { type: Number, required: true },
  mbtiStyle: { type: String, required: true },
  isUserEngineer: { type: Boolean, required: true },
  allowAiReadCode: { type: Boolean, required: true },
  apiEndpoint: { type: String, required: true },
  apiModel: { type: String, required: true },
  selectedProject: { type: String, required: true },
  selectedProjectUser: { type: String, default: '' },
  projectUsers: { type: Array, required: true },
  projectFiles: { type: Array, required: true },
  projects: { type: Array, required: true },
  parseAndRenderContent: { type: Function, required: true },
  copyToClipboard: { type: Function, required: true }
})

const emit = defineEmits([
  'update:mbtiStyle',
  'update:isUserEngineer',
  'update:allowAiReadCode',
  'update:apiEndpoint',
  'update:apiModel',
  'update:selectedProject',
  'update:selectedProjectUser',
  'update:currentViewedStageIndex',
  'close',
  'start',
  'pause',
  'continue',
  'refine',
  'applyProposals',
  'print'
])

// Local UI States
const showProgressSidebar = ref(true)
const showStagesAccordion = ref([true, true, true, true, true, true, true, true, true, true, true, true, true])
const showThoughtsCollapse = ref([false, false, false, false, false, false, false, false, false, false, false, false, false])
const refinementInput = ref('')

const mbtiOptions = [
  { value: 'INTJ', label: 'INTJ 系統分析師：極度邏輯、精確架構、著眼未來' },
  { value: 'ENTP', label: 'ENTP 創意規劃師：點子多、多重架構對比、反向思維' },
  { value: 'INFJ', label: 'INFJ 人本引導者：文字溫暖、重視團隊溝通、循序漸進' },
  { value: 'ESTP', label: 'ESTP 實幹執行者：精簡程式碼、直奔難點、注重效能與部署' }
]

const handleRefine = () => {
  if (!refinementInput.value.trim()) return
  emit('refine', refinementInput.value)
  refinementInput.value = ''
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
              <h2 class="text-sm font-semibold text-neutral-800">Laravel + Vue 混合架構十三層深度分析區</h2>
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
            @click="emit('close')"
            class="p-1 hover:bg-neutral-200/50 rounded-lg text-neutral-400 hover:text-neutral-700 transition-colors cursor-pointer"
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
                    @click="emit('update:isUserEngineer', true)"
                    class="px-4 py-2.5 border rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition-all"
                    :class="isUserEngineer ? 'bg-neutral-900 border-neutral-900 text-white shadow-sm' : 'border-neutral-200 hover:bg-neutral-50 text-neutral-700'"
                  >
                    <span>專業工程師</span>
                  </button>
                  <button 
                    @click="emit('update:isUserEngineer', false)"
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
                  :value="mbtiStyle"
                  @change="e => emit('update:mbtiStyle', e.target.value)"
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
                    :value="apiEndpoint"
                    @input="e => emit('update:apiEndpoint', e.target.value)"
                    type="text"
                    class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-2 text-xs text-neutral-700 focus:outline-none focus:border-neutral-300 transition-colors"
                  />
                </div>
                <div class="space-y-2">
                  <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wider">模型 (Model)</label>
                  <input 
                    :value="apiModel"
                    @input="e => emit('update:apiModel', e.target.value)"
                    type="text"
                    class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-2 text-xs text-neutral-700 focus:outline-none focus:border-neutral-300 transition-colors"
                  />
                </div>
              </div>

              <!-- Active Code Retrieval Permission Toggle -->
              <div class="p-3 bg-purple-50/50 rounded-xl border border-purple-100 flex items-center justify-between">
                <div class="flex-1 pr-4">
                  <label class="block text-xs font-bold text-purple-950 flex items-center gap-1.5">
                    <span>📂 開啟沙盒原始碼動態檢讀權限</span>
                    <HelpIcon class="w-3.5 h-3.5 text-purple-400" title="開啟後，AI 將在各分析層級主動掃描比對您的 Laravel 專案代碼，進行精讀評估！" />
                  </label>
                  <p class="text-[10px] text-purple-600/70 m-0 mt-0.5">授權 AI 深入檢索當前專案與讀取具體 Controller/Vue/Model 代碼</p>
                </div>
                <input 
                  type="checkbox" 
                  :checked="allowAiReadCode"
                  @change="e => emit('update:allowAiReadCode', e.target.checked)"
                  class="rounded text-purple-600 border-purple-300 focus:ring-purple-400 w-5 h-5 cursor-pointer" 
                />
              </div>

              <!-- Project scope configuration -->
              <div class="grid grid-cols-2 gap-3.5" v-if="allowAiReadCode">
                <div class="space-y-2">
                  <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wider">工程師成員專案</label>
                  <select 
                    :value="selectedProjectUser"
                    @change="e => emit('update:selectedProjectUser', e.target.value)"
                    class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-2 text-xs text-neutral-700 font-medium focus:outline-none focus:border-neutral-300 transition-colors"
                  >
                    <option v-for="user in projectUsers" :key="user" :value="user">{{ user }}</option>
                  </select>
                </div>
                <div class="space-y-2">
                  <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wider">要分析的儲存庫 (Repo)</label>
                  <select 
                    :value="selectedProject"
                    @change="e => emit('update:selectedProject', e.target.value)"
                    class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-2 text-xs text-neutral-700 font-medium focus:outline-none focus:border-neutral-300 transition-colors"
                  >
                    <option v-for="p in projects" :key="p.name" :value="p.name">{{ p.name }}</option>
                  </select>
                </div>
              </div>
            </div>

            <button 
              @click="emit('start')"
              class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-semibold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-1.5 cursor-pointer"
            >
              <SparklesIcon class="w-4 h-4 animate-pulse" />
              <span>啟動十三層深度串流分析規劃</span>
            </button>
          </div>
        </div>

        <!-- Working analysis panel -->
        <div v-else class="flex-1 flex overflow-hidden min-h-0 relative select-text">
          <!-- Left checklist sidebar -->
          <div 
            v-show="showProgressSidebar"
            class="w-80 border-r border-neutral-100 flex flex-col shrink-0 bg-neutral-50/30 overflow-y-auto no-select"
          >
            <div class="p-4 border-b border-neutral-100 flex items-center justify-between shrink-0 bg-white">
              <span class="text-xs font-bold text-neutral-500 uppercase tracking-wider">分析進度樹</span>
              <span class="text-[10px] text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full font-bold">13 階段</span>
            </div>

            <div class="p-2 space-y-1">
              <div 
                v-for="(stage, idx) in stagesProgress" 
                :key="stage.id"
                @click="emit('update:currentViewedStageIndex', idx)"
                class="flex items-start gap-3 p-3 rounded-xl cursor-pointer transition-all border"
                :class="[
                  currentViewedStageIndex === idx 
                    ? 'bg-purple-50/50 border-purple-100 shadow-sm' 
                    : 'border-transparent hover:bg-neutral-100/50',
                  stage.status === 'running' ? 'ring-1 ring-purple-400' : ''
                ]"
              >
                <!-- Icon state -->
                <div class="mt-0.5">
                  <span v-if="stage.status === 'running'" class="text-purple-600 font-bold text-xs animate-spin block">⏳</span>
                  <span v-else-if="stage.status === 'success'" class="text-emerald-600 font-bold text-xs block">✓</span>
                  <span v-else-if="stage.status === 'error'" class="text-red-600 font-bold text-xs block">✗</span>
                  <span v-else class="text-neutral-300 text-xs block">•</span>
                </div>
                
                <div class="flex-1 min-w-0">
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

          <!-- Right report content area -->
          <div class="flex-1 flex flex-col min-h-0 bg-white">
            <!-- Pagination header toolbar -->
            <div class="px-6 py-3 border-b border-neutral-100 flex items-center justify-between shrink-0 bg-neutral-50/30 no-select">
              <button 
                @click="emit('update:currentViewedStageIndex', Math.max(0, currentViewedStageIndex - 1))"
                :disabled="currentViewedStageIndex === 0"
                class="px-3.5 py-1.5 border border-neutral-200 hover:bg-neutral-50 text-neutral-600 rounded-lg text-xs font-semibold disabled:opacity-40 disabled:pointer-events-none flex items-center gap-1 cursor-pointer transition-all"
              >
                <span>◀ 上一層</span>
              </button>
              
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-neutral-500">檢視中：</span>
                <span class="text-xs font-black text-purple-700 bg-purple-50 px-3 py-1 rounded-full border border-purple-100">
                  第 {{ currentViewedStageIndex }} 層：{{ stagesProgress[currentViewedStageIndex]?.name.split('：')[1] || stagesProgress[currentViewedStageIndex]?.name }}
                </span>
              </div>
              
              <button 
                @click="emit('update:currentViewedStageIndex', Math.min(12, currentViewedStageIndex + 1))"
                :disabled="currentViewedStageIndex === 12"
                class="px-3.5 py-1.5 border border-neutral-200 hover:bg-neutral-50 text-neutral-600 rounded-lg text-xs font-semibold disabled:opacity-40 disabled:pointer-events-none flex items-center gap-1 cursor-pointer transition-all"
              >
                <span>下一層 ▶</span>
              </button>
            </div>

            <!-- Page card contents -->
            <div class="space-y-6 flex-1 overflow-y-auto p-6 select-text">
              <div 
                v-for="(stage, idx) in stagesProgress" 
                :key="stage.id"
                v-show="idx === currentViewedStageIndex"
                :id="`analysis-stage-card-${stage.id}`"
                class="border border-neutral-200/60 rounded-xl overflow-hidden shadow-sm bg-white"
              >
                <!-- Stage Title Header -->
                <div 
                  @click="showStagesAccordion[idx] = !showStagesAccordion[idx]"
                  class="bg-neutral-50/80 px-4 py-3 border-b border-neutral-100 flex items-center justify-between text-xs font-semibold text-neutral-700 cursor-pointer select-none hover:bg-neutral-100/70 transition-colors"
                >
                  <span class="flex items-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-neutral-200 text-neutral-700 flex items-center justify-center text-[9px]">{{ idx }}</span>
                    <span>{{ stage.name }}</span>
                  </span>
                  <div class="flex items-center gap-3">
                    <span :class="stage.status === 'success' ? 'text-emerald-600' : (stage.status === 'running' ? 'text-purple-600 animate-pulse' : 'text-neutral-400')">
                      {{ stage.status === 'running' ? '正在生成...' : (stage.status === 'success' ? '已完成' : '等待上游階段...') }}
                    </span>
                    <ChevronUpIcon v-if="showStagesAccordion[idx]" class="w-3.5 h-3.5 text-neutral-400" />
                    <ChevronDownIcon v-else class="w-3.5 h-3.5 text-neutral-400" />
                  </div>
                </div>
                
                <!-- Stage Card Body -->
                <div 
                  v-show="showStagesAccordion[idx]"
                  class="p-4 text-sm leading-relaxed text-neutral-700 bg-white relative border-t border-neutral-50 select-text"
                >
                  <!-- Stage Terminal Logs (Show what AI is doing behind the scenes) -->
                  <div v-if="stageLogs[idx]" class="p-3.5 bg-neutral-900 text-neutral-200 rounded-xl font-mono text-[11px] mb-4 space-y-1.5 border border-neutral-800 shadow-inner select-text">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-purple-400 mb-2 border-b border-neutral-800 pb-1 flex items-center justify-between select-none">
                      <span>⚙️ 本層技術分析預檢日誌</span>
                      <span v-if="currentAnalyzingStageIndex === idx && isMultiStageRunning" class="w-1.5 h-1.5 rounded-full bg-purple-500 animate-ping"></span>
                    </div>
                    <div class="space-y-1.5" v-html="stageLogs[idx]"></div>
                  </div>
                  
                  <!-- 1. Active Thinking Panel -->
                  <div v-if="currentAnalyzingStageIndex === idx && isMultiStageRunning && stageThoughts[idx]" class="relative border border-purple-100/70 rounded-xl bg-purple-50/5 p-4 mb-4 overflow-hidden">
                    <div class="absolute inset-0 bg-white/40 backdrop-blur-[1px] flex items-center justify-center pointer-events-none select-none">
                      <div class="bg-white/95 border border-purple-200/50 px-3 py-1 rounded-full shadow-sm flex items-center gap-1.5 animate-pulse">
                        <SpinnerIcon class="w-3.5 h-3.5 text-purple-600 animate-spin" />
                        <span class="text-[10px] font-bold text-purple-700">AI 思考中...</span>
                      </div>
                    </div>
                    <div class="text-[11px] font-mono text-neutral-500 italic whitespace-pre-wrap leading-relaxed select-text">
                      {{ stageThoughts[idx] }}
                    </div>
                  </div>

                  <!-- 2. Collapsed Thinking Trace -->
                  <div v-if="stageThoughts[idx] && !(currentAnalyzingStageIndex === idx && isMultiStageRunning)" class="mb-4">
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
                  
                  <div v-else-if="stage.status === 'running' && !(currentAnalyzingStageIndex === idx && isMultiStageRunning)" class="text-purple-400 font-medium animate-pulse flex items-center gap-2">
                    <SpinnerIcon class="w-3.5 h-3.5 animate-spin" />
                    <span>AI 正在撰寫本層技術分析，請稍候...</span>
                  </div>
                  <span v-else-if="stage.status === 'idle'" class="text-neutral-300 italic">等待上游階段完成後解鎖...</span>

                  <!-- Stage 12 Copy Button -->
                  <div v-if="idx === 12 && stageOutputs[idx] && !(currentAnalyzingStageIndex === idx && isMultiStageRunning)" class="mt-4">
                    <button 
                      @click="copyToClipboard(stageOutputs[idx])"
                      class="flex items-center gap-1.5 px-4 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold shadow-md hover:shadow-lg transition-all"
                    >
                      <span>📋 複製 AI Agent 開發指令 Prompt</span>
                    </button>
                  </div>

                  <!-- Feasibility Radar Chart -->
                  <FeasibilityRadarChart 
                    v-if="stageOutputs[idx] && parseAndRenderContent(stageOutputs[idx]).radarScores" 
                    :scores="parseAndRenderContent(stageOutputs[idx]).radarScores" 
                  />

                  <!-- Mermaid Live Chart rendering -->
                  <MermaidRender 
                    v-if="stageOutputs[idx] && parseAndRenderContent(stageOutputs[idx]).mermaidCode" 
                    :code="parseAndRenderContent(stageOutputs[idx]).mermaidCode" 
                    :id="idx"
                  />
                </div>
              </div>
            </div>
            
            <!-- Analysis Refinement Input Box -->
            <div v-if="hasStartedMultiStage && !isMultiStageRunning && stagesProgress.every(s => s.status === 'success' || s.status === 'error') && stageOutputs[currentViewedStageIndex]" class="p-4 border-t border-neutral-100 bg-neutral-50/50 shrink-0">
              <div class="flex items-center gap-2 max-w-4xl mx-auto">
                <input 
                  v-model="refinementInput"
                  @keydown.enter="handleRefine"
                  type="text" 
                  placeholder="輸入您的修改或補充意見，要求 AI 微調優化此層技術架構報告..." 
                  class="flex-1 bg-white border border-neutral-200 rounded-xl px-4 py-2.5 text-xs text-neutral-700 focus:outline-none focus:border-purple-300 outline-none select-text"
                />
                <button 
                  @click="handleRefine"
                  class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer shadow-sm"
                >
                  <SendIcon class="w-3.5 h-3.5" />
                  <span>優化架構</span>
                </button>
              </div>
            </div>

            <!-- Proposals Application Box -->
            <div v-if="multiStageActions.length > 0 && !isMultiStageRunning" class="p-4 md:p-6 shrink-0 bg-white border-t border-neutral-100 shadow-inner">
              <div class="max-w-4xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="text-xs">
                  <div class="font-bold text-neutral-700">💡 AI 依此深度分析產生了 {{ multiStageActions.length }} 項心智圖變更建議：</div>
                  <div class="text-neutral-400 mt-1">選取套用的動作會自動寫入左側的心智圖畫布，您可在此進行預檢。</div>
                </div>
                <div class="flex items-center gap-3">
                  <button 
                    @click="emit('applyProposals')"
                    class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-semibold shadow-md hover:shadow-lg transition-all flex items-center gap-1.5 cursor-pointer"
                  >
                    <CheckIcon class="w-4 h-4" />
                    <span>查看 / 套用所選的 {{ selectedMultiProposalsCount }} 個心智圖變更</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Footer control bar -->
            <div class="p-4 md:p-5 border-t border-neutral-100 flex items-center justify-between shrink-0 bg-neutral-50/50 no-select">
              <div class="flex items-center gap-3">
                <div v-if="isMultiStageRunning" class="flex items-center gap-2 text-purple-600 font-semibold text-xs animate-pulse">
                  <SpinnerIcon class="w-3.5 h-3.5 animate-spin" />
                  <span>正在生成 {{ stagesProgress[currentAnalyzingStageIndex]?.name }}...</span>
                </div>
                <div v-else class="text-xs text-neutral-400">
                  <span v-if="isMultiStagePaused">{{ multiStageStatusMessage }}</span>
                  <span v-else>完成所有階段分析！</span>
                </div>

                <!-- Grand Total Feasibility Score display -->
                <div v-if="hasStartedMultiStage && !isMultiStageRunning && !isMultiStagePaused" class="flex items-center gap-3 bg-neutral-100 px-3.5 py-1.5 rounded-xl border border-neutral-200/50">
                  <div class="text-xs font-bold text-neutral-700">綜合評估總分數：</div>
                  <div class="flex items-baseline gap-0.5">
                    <span class="text-base font-black text-purple-700 leading-none">{{ grandTotalScore }}</span>
                    <span class="text-[10px] text-neutral-400">/ 120 滿分</span>
                  </div>
                  <!-- Mini Progress Bar scaled to 120 -->
                  <div class="w-16 h-2 bg-neutral-200 rounded-full overflow-hidden">
                    <div class="h-full bg-purple-600 transition-all duration-500" :style="{ width: (grandTotalScore / 1.2) + '%' }"></div>
                  </div>
                </div>
              </div>
              
              <div class="flex items-center gap-3">
                <button 
                  v-if="hasStartedMultiStage"
                  @click="emit('print')"
                  class="px-4 py-2 border border-neutral-300 bg-neutral-900 hover:bg-neutral-800 text-white rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer"
                >
                  <span>🖨️ 列印 / 匯出 PDF</span>
                </button>
                <button 
                  v-if="isMultiStageRunning"
                  @click="emit('pause')"
                  class="px-4 py-2 border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer"
                >
                  <span>⏸️ 暫停分析</span>
                </button>
                <button 
                  v-if="isMultiStagePaused"
                  @click="emit('continue')"
                  class="px-4 py-2 border border-purple-200 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 cursor-pointer"
                >
                  <span>▶️ 繼續分析</span>
                </button>
                <button 
                  @click="emit('close')"
                  class="px-4 py-2 border border-neutral-200 hover:bg-neutral-100 text-neutral-700 rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                >
                  關閉
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>
