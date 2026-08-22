<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="皇榳架構設計心智圖系統 - HT Mindmap" />
    
    <div class="relative bg-white text-[#1A1A1A] min-h-screen flex flex-col items-center justify-center p-6 overflow-hidden">
        <!-- Top Navigation -->
        <header v-if="canLogin" class="absolute top-0 right-0 p-8 z-10 flex gap-6 text-xs font-bold uppercase tracking-widest no-select">
            <Link
                v-if="$page.props.auth.user"
                :href="route('dashboard')"
                class="text-neutral-500 hover:text-[#1A1A1A] transition-colors border-b-2 border-transparent hover:border-[#1A1A1A] pb-1"
            >
                進入控制台 (Dashboard)
            </Link>

            <template v-else>
                <Link
                    :href="route('login')"
                    class="text-neutral-500 hover:text-[#1A1A1A] transition-colors border-b-2 border-transparent hover:border-[#1A1A1A] pb-1"
                >
                    登入 (Log in)
                </Link>

                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="text-neutral-500 hover:text-[#1A1A1A] transition-colors border-b-2 border-transparent hover:border-[#1A1A1A] pb-1"
                >
                    註冊 (Register)
                </Link>
            </template>
        </header>

        <!-- Main Content -->
        <main class="flex flex-col items-center justify-center text-center relative z-10">
            <!-- Masking container for the logo -->
            <div class="overflow-hidden mb-8">
                <div class="w-24 h-24 bg-[#1A1A1A] text-white flex items-center justify-center text-5xl font-black rounded-sm shadow-2xl hover:scale-105 hover:rotate-2 transition-all duration-300 select-none animate-slide-up">
                    HT
                </div>
            </div>

            <!-- Title -->
            <div class="overflow-hidden space-y-2 animate-slide-up-delay">
                <h1 class="text-3xl md:text-5xl font-bold tracking-tight text-[#1A1A1A]">
                    皇榳架構設計心智圖系統
                </h1>
                <p class="font-serif italic text-neutral-400 text-sm md:text-base">
                    HUANG TING SYSTEM ARCHITECTURE DESIGN MINDMAP
                </p>
            </div>

            <!-- Get Started Call to Action -->
            <div class="mt-12 animate-slide-up-delay-more">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="px-8 py-3 bg-[#1A1A1A] hover:bg-[#2b2b2b] text-white text-xs uppercase tracking-widest font-bold rounded-lg shadow-lg hover:shadow-xl transition-all cursor-pointer inline-flex items-center gap-2"
                >
                    <span>進入控制台</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </Link>
                <Link
                    v-else
                    :href="route('login')"
                    class="px-8 py-3 bg-[#1A1A1A] hover:bg-[#2b2b2b] text-white text-xs uppercase tracking-widest font-bold rounded-lg shadow-lg hover:shadow-xl transition-all cursor-pointer inline-flex items-center gap-2"
                >
                    <span>開始使用</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </Link>
            </div>

            <!-- Dedication Section -->
            <div class="mt-20 max-w-xl text-center flex flex-col items-center space-y-6 animate-slide-up-delay-more">
                <div class="w-12 h-[1px] bg-neutral-200"></div>
                <p class="text-xs font-serif italic text-neutral-400 leading-relaxed max-w-sm">
                    「快樂的秘訣就是，擁有蘋果的時候只想著蘋果!」
                </p>
                
                <div class="space-y-0.5">
                    <div class="text-[9px] tracking-[0.4em] text-neutral-800 font-bold uppercase">
                        Huang Ting Internet Studio
                    </div>
                    <div class="text-[9px] tracking-[0.2em] text-neutral-400 font-light uppercase">
                        建立時間 2020年
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Bottom branding -->
        <footer class="absolute bottom-6 text-center text-[10px] text-neutral-400 font-mono select-none">
            HTIS • Mindmap System v1.0
        </footer>
    </div>
</template>

<style scoped>
@keyframes slideUp {
  from {
    transform: translateY(40px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.animate-slide-up {
  animation: slideUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-slide-up-delay {
  animation: slideUp 1.2s cubic-bezier(0.16, 1, 0.3, 1) 0.2s forwards;
  opacity: 0;
}

.animate-slide-up-delay-more {
  animation: slideUp 1.5s cubic-bezier(0.16, 1, 0.3, 1) 0.4s forwards;
  opacity: 0;
}
</style>
