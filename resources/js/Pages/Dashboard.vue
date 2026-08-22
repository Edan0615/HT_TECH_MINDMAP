<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
  Plus as PlusIcon, 
  Trash2 as TrashIcon, 
  FileText as FileIcon, 
  ArrowRight as ArrowRightIcon,
  Calendar as CalendarIcon
} from '@lucide/vue';

const props = defineProps({
  mindmaps: {
    type: Array,
    default: () => []
  }
});

const isCreating = ref(false);

const createNewMindmap = async () => {
  isCreating.value = true;
  try {
    const res = await window.axios.post('/mindmaps', {
      title: '未命名專案心智圖',
      data: {
        id: 'root',
        text: '未命名專案心智圖',
        color: '#1b1b1f',
        details: '雙擊或點擊齒輪編輯此節點細節。',
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
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-neutral-800 leading-tight">
          專案設計心智圖控制台
        </h2>
        <button
          @click="createNewMindmap"
          :disabled="isCreating"
          class="flex items-center gap-1.5 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all disabled:opacity-50 cursor-pointer"
        >
          <span v-if="isCreating" class="w-3.5 h-3.5 border-2 border-t-transparent border-white rounded-full animate-spin"></span>
          <PlusIcon v-else class="w-3.5 h-3.5" />
          <span>建立新心智圖</span>
        </button>
      </div>
    </template>

    <div class="py-10 bg-neutral-50/50 min-h-[calc(100vh-7rem)]">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
        <!-- Dashboard stats / intro banner -->
        <div class="bg-gradient-to-r from-purple-900 to-indigo-950 rounded-2xl p-6 text-white shadow-sm flex items-center justify-between">
          <div class="space-y-1">
            <h3 class="text-base font-bold">歡迎回來，{{ $page.props.auth.user.name }}！</h3>
            <p class="text-xs text-purple-200/80">在這裡管理所有專案的 12 層 AI 架構設計藍圖與心智圖草稿，並與團隊成員進行內網分享。</p>
          </div>
          <div class="text-right">
            <span class="text-3xl font-black font-mono">{{ mindmaps.length }}</span>
            <span class="block text-[10px] text-purple-200 uppercase tracking-wider font-semibold">已存心智圖總數</span>
          </div>
        </div>

        <!-- Mindmap grid -->
        <div v-if="mindmaps.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div 
            v-for="item in mindmaps" 
            :key="item.id"
            class="bg-white rounded-2xl border border-neutral-100 shadow-sm hover:shadow-md transition-all flex flex-col justify-between overflow-hidden group"
          >
            <!-- Card Header -->
            <div class="p-5 space-y-3">
              <div class="flex items-center justify-between">
                <span class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                  <FileIcon class="w-4 h-4" />
                </span>
                <span class="text-[10px] text-neutral-400 font-medium flex items-center gap-1">
                  <CalendarIcon class="w-3 h-3" />
                  <span>{{ formatDate(item.updated_at) }}</span>
                </span>
              </div>
              
              <div class="space-y-1">
                <h4 class="text-sm font-bold text-neutral-800 group-hover:text-purple-600 transition-colors line-clamp-1">
                  {{ item.title }}
                </h4>
                <p class="text-[11px] text-neutral-400 line-clamp-2">
                  節點樹根名稱: {{ item.data?.text || '未指定' }}
                </p>
              </div>
            </div>

            <!-- Card Actions -->
            <div class="px-5 py-3.5 bg-neutral-50/50 border-t border-neutral-50 flex items-center justify-between">
              <button 
                @click="deleteMindmap(item.id)"
                class="text-neutral-400 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition-colors cursor-pointer"
                title="刪除心智圖"
              >
                <TrashIcon class="w-4 h-4" />
              </button>
              
              <Link 
                :href="`/mindmaps/${item.id}`"
                class="flex items-center gap-1 text-xs font-semibold text-purple-600 hover:text-purple-700 transition-colors"
              >
                <span>進入編輯</span>
                <ArrowRightIcon class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" />
              </Link>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div 
          v-else 
          class="bg-white rounded-2xl border border-neutral-100 p-12 text-center max-w-xl mx-auto shadow-sm space-y-4"
        >
          <div class="w-12 h-12 rounded-full bg-neutral-50 text-neutral-400 flex items-center justify-center mx-auto">
            <FileIcon class="w-6 h-6" />
          </div>
          <div class="space-y-1">
            <h4 class="text-sm font-bold text-neutral-800">尚未建立任何心智圖</h4>
            <p class="text-xs text-neutral-400">建立第一個心智圖專案，開始利用 12 層 AI 漸進分析快速設計您的軟體工程藍圖！</p>
          </div>
          <button
            @click="createNewMindmap"
            :disabled="isCreating"
            class="px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-semibold shadow-sm transition-all disabled:opacity-50 cursor-pointer inline-flex items-center gap-1.5"
          >
            <span v-if="isCreating" class="w-3.5 h-3.5 border-2 border-t-transparent border-white rounded-full animate-spin"></span>
            <PlusIcon v-else class="w-3.5 h-3.5" />
            <span>建立第一個專案</span>
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
