<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
  Plus as PlusIcon, 
  Trash2 as TrashIcon, 
  FileText as FileIcon, 
  ArrowRight as ArrowRightIcon,
  Calendar as CalendarIcon,
  Sparkles as SparklesIcon
} from '@lucide/vue';

const props = defineProps({
  mindmaps: {
    type: Array,
    default: () => []
  }
});

const isCreating = ref(false);
const activeFilterFolder = ref('全部');

const uniqueFolders = computed(() => {
  const folders = new Set();
  props.mindmaps.forEach(item => {
    if (item.folder) folders.add(item.folder);
  });
  return ['全部', ...Array.from(folders)];
});

const filteredMindmaps = computed(() => {
  if (activeFilterFolder.value === '全部') return props.mindmaps;
  return props.mindmaps.filter(item => item.folder === activeFilterFolder.value);
});

const createNewMindmap = async () => {
  const folderName = prompt('請輸入存放資料夾名稱 (例如：網站、作業、商業規劃)：', '網站');
  if (folderName === null) return; // user cancelled

  isCreating.value = true;
  try {
    const res = await window.axios.post('/mindmaps', {
      title: '新專案設計藍圖',
      folder: folderName.trim() || '網站',
      data: {
        id: 'root',
        text: '新專案設計藍圖',
        color: '#1b1b1f',
        details: '雙擊或點擊大綱旁的齒輪編輯此節點細節。',
        expanded: true,
        children: [
          {
            id: 'overview',
            text: '1. 專案概述',
            color: '#8b5cf6',
            details: '在這裡填寫專案目標與背景。',
            expanded: true,
            children: []
          },
          {
            id: 'architecture',
            text: '2. 系統架構',
            color: '#3b82f6',
            details: '填寫前端、後端、與整合邏輯。',
            expanded: true,
            children: []
          }
        ]
      }
    });

    if (res.data.success) {
      router.visit(`/mindmaps/${res.data.mindmap.id}`);
    }
  } catch (e) {
    alert('建立失敗：' + (e.response?.data?.message || e.message));
  } finally {
    isCreating.value = false;
  }
};

const deleteMindmap = async (id) => {
  if (confirm('確定要永久刪除此心智圖嗎？此動作無法復原！')) {
    try {
      const res = await window.axios.delete(`/mindmaps/${id}`);
      if (res.data.success) {
        router.reload();
      }
    } catch (e) {
      alert('刪除失敗：' + (e.response?.data?.message || e.message));
    }
  }
};

const formatDate = (dateStr) => {
  const d = new Date(dateStr);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
};
</script>

<template>
  <Head title="控制台 - HT Mindmap" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between no-select">
        <div>
          <h2 class="text-xl font-black tracking-tight text-neutral-800 uppercase">
            Workspace Dashboard
          </h2>
          <p class="text-[10px] text-neutral-400 font-mono tracking-wider uppercase mt-0.5">專案藍圖管理控制台</p>
        </div>
        
        <button
          @click="createNewMindmap"
          :disabled="isCreating"
          class="flex items-center gap-1.5 px-4 py-2 bg-[#1A1A1A] hover:bg-[#2c2c2c] text-white rounded-lg text-xs font-bold uppercase tracking-wider shadow-sm hover:shadow-md transition-all duration-300 disabled:opacity-50 cursor-pointer"
        >
          <span v-if="isCreating" class="w-3.5 h-3.5 border-2 border-t-transparent border-white rounded-full animate-spin"></span>
          <PlusIcon v-else class="w-3.5 h-3.5 text-[#FACC15]" />
          <span>建立新心智圖</span>
        </button>
      </div>
    </template>

    <div class="relative min-h-[calc(100vh-7rem)] bg-white py-10 px-4 sm:px-6 lg:px-8 overflow-hidden">
      <!-- Fluid Background Blurs to match HTIS_Tailwind -->
      <div class="absolute w-[400px] h-[400px] rounded-full bg-[#FACC15] blur-[150px] opacity-[0.06] top-[-10%] left-[-10%] pointer-events-none"></div>
      <div class="absolute w-[450px] h-[450px] rounded-full bg-[#FACC15] blur-[160px] opacity-[0.08] bottom-[-10%] right-[-10%] pointer-events-none"></div>

      <div class="mx-auto max-w-7xl space-y-8 relative z-10">
        
        <!-- ═══ HTIS-Style Hero Branding Panel ═══ -->
        <div class="border border-neutral-100 bg-[#F9FAFB]/60 backdrop-blur-md rounded-2xl p-6 md:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6 shadow-sm">
          <div class="space-y-3">
            <div class="flex items-center gap-2">
              <span class="text-[10px] font-mono font-bold tracking-[0.3em] text-[#FACC15] uppercase">System Overview</span>
              <span class="text-neutral-300">•</span>
              <span class="text-[10px] font-mono text-neutral-400 uppercase tracking-widest">HTIS Mindmap v1.0</span>
            </div>
            
            <h3 class="text-2xl md:text-3xl font-black text-neutral-800 tracking-tight leading-none uppercase">
              歡迎回來, <span class="font-serif italic font-light text-neutral-500 lowercase">{{ $page.props.auth.user.name }}</span>
            </h3>
            <p class="text-xs text-neutral-500 leading-relaxed max-w-xl">
              在此快速建立 12 層級的 AI 漸進分析設計藍圖。整合 Laravel 全端代碼結構、MySQL 關聯模型與系統細節，隨時導出為標準 JSON 格式。
            </p>
          </div>
          
          <!-- Large Stats Counter -->
          <div class="border-t md:border-t-0 md:border-l border-neutral-100 pt-6 md:pt-0 md:pl-8 flex items-center md:flex-col items-end justify-between md:justify-center min-w-[120px] select-none">
            <span class="text-5xl md:text-6xl font-black font-mono text-[#1A1A1A] leading-none tracking-tighter">
              {{ String(mindmaps.length).padStart(2, '0') }}
            </span>
            <span class="text-[9px] tracking-[0.2em] text-neutral-400 font-bold uppercase mt-1">
              Active Blueprints
            </span>
          </div>
        </div>

        <!-- Bento Grid Section -->
        <div>
          <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
            <h3 class="text-xs font-mono font-bold tracking-[0.3em] text-neutral-400 uppercase">
              專案設計清單 (Blueprints)
            </h3>
            
            <!-- Folder Filter Pills -->
            <div v-if="uniqueFolders.length > 1" class="flex flex-wrap items-center gap-1.5 bg-neutral-50 p-1 rounded-xl border border-neutral-100/80 select-none">
              <button
                v-for="folder in uniqueFolders"
                :key="folder"
                @click="activeFilterFolder = folder"
                class="px-3 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer"
                :class="[activeFilterFolder === folder ? 'bg-neutral-900 text-white shadow-sm' : 'text-neutral-500 hover:text-neutral-800']"
              >
                {{ folder }}
              </button>
            </div>
          </div>

          <!-- Mindmap Bento Grid -->
          <div v-if="filteredMindmaps.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div 
              v-for="(item, index) in filteredMindmaps" 
              :key="item.id"
              class="border border-neutral-100 bg-white rounded-2xl p-5 hover:border-neutral-300 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 flex flex-col justify-between group relative overflow-hidden"
            >
              <!-- Card Top -->
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <!-- Row Number in Serif Style -->
                  <span class="text-lg font-serif italic text-[#FACC15] font-light">
                    0{{ index + 1 }}
                  </span>
                  
                  <span class="text-[9px] font-mono text-neutral-400 flex items-center gap-1.5 bg-neutral-50 px-2.5 py-1 rounded-full border border-neutral-100">
                    <CalendarIcon class="w-3 h-3 text-neutral-400" />
                    <span>{{ formatDate(item.updated_at) }}</span>
                  </span>
                </div>
                
                <div class="space-y-1.5">
                  <h4 class="text-sm font-bold text-neutral-800 group-hover:text-neutral-900 transition-colors line-clamp-1">
                    {{ item.title }}
                  </h4>
                  <p class="text-[11px] text-neutral-400 leading-relaxed line-clamp-2">
                    主核心節點: <span class="font-semibold text-neutral-500 font-mono">{{ item.data?.text || '未指定' }}</span>
                  </p>
                  <div class="flex flex-wrap gap-1.5 pt-2 select-none">
                    <span class="text-[9px] font-mono font-bold px-2 py-0.5 rounded bg-neutral-100 text-neutral-600 border border-neutral-200/50">
                      📁 {{ item.folder || '網站' }}
                    </span>
                    <span class="text-[9px] font-mono px-2 py-0.5 rounded bg-purple-50 text-purple-600 border border-purple-100 flex items-center gap-0.5">
                      👤 {{ item.user?.name || '未知' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Card Bottom controls -->
              <div class="mt-6 pt-4 border-t border-neutral-100 flex items-center justify-between bg-white relative z-10">
                <button 
                  @click="deleteMindmap(item.id)"
                  class="text-neutral-300 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition-all duration-200 cursor-pointer"
                  title="永久刪除"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
                
                <Link 
                  :href="`/mindmaps/${item.id}`"
                  class="flex items-center gap-1 text-xs font-bold text-neutral-800 hover:text-[#FACC15] transition-colors uppercase tracking-wider"
                >
                  <span>編輯藍圖</span>
                  <ArrowRightIcon class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
                </Link>
              </div>
            </div>
          </div>

          <!-- Empty State (Bento-styled) -->
          <div 
            v-else 
            class="border border-neutral-100 rounded-2xl p-12 text-center max-w-lg mx-auto bg-[#F9FAFB]/50 backdrop-blur-md shadow-sm space-y-6"
          >
            <div class="w-12 h-12 rounded-full bg-[#1A1A1A] text-white flex items-center justify-center mx-auto">
              <FileIcon class="w-5 h-5 text-[#FACC15]" />
            </div>
            
            <div class="space-y-2">
              <h4 class="text-sm font-bold text-neutral-800">尚未建立任何心智圖設計</h4>
              <p class="text-xs text-neutral-400 leading-relaxed">
                開始新增您的第一個架構心智圖，利用系統自動化的 12 層級 AI 技術雷達與 ERD 關聯設計，加速您的開發計畫。
              </p>
            </div>
            
            <button
              @click="createNewMindmap"
              :disabled="isCreating"
              class="px-6 py-2.5 bg-[#1A1A1A] hover:bg-[#2c2c2c] text-white rounded-lg text-xs font-bold uppercase tracking-widest shadow-md transition-all duration-300 disabled:opacity-50 cursor-pointer inline-flex items-center gap-1.5"
            >
              <span v-if="isCreating" class="w-3.5 h-3.5 border-2 border-t-transparent border-white rounded-full animate-spin"></span>
              <PlusIcon v-else class="w-3.5 h-3.5 text-[#FACC15]" />
              <span>建立第一個設計圖</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
