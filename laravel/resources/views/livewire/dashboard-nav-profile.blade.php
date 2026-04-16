<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="relative flex items-center gap-2" 
     x-data="{ open: false, darkMode: localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches) }" 
     x-init="$watch('darkMode', val => { localStorage.setItem('theme', val ? 'dark' : 'light'); document.documentElement.classList.toggle('dark', val); }); document.documentElement.classList.toggle('dark', darkMode);"
     @click.outside="open = false" @close.stop="open = false">
    
    <!-- Dark Mode Toggle Button -->
    <button type="button" @click="darkMode = !darkMode" class="p-2 mr-2 rounded-full hover:bg-stone-100 dark:hover:bg-gray-800 transition-colors text-stone-500 dark:text-gray-400 focus:outline-none">
        <svg x-show="!darkMode" class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <svg x-show="darkMode" class="w-5 h-5 text-indigo-400" style="display: none;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
    </button>
    @auth
    <button @click="open = ! open" class="flex items-center gap-2 rounded-full p-1 transition hover:bg-stone-100 dark:hover:bg-gray-800 focus:outline-none">
        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-ista-primary text-sm font-bold text-amber-400">
            {{ substr(auth()->user()->name, 0, 1) }}
        </div>
        <div class="hidden items-center gap-1 pr-2 sm:flex">
            <span class="text-sm font-medium text-stone-600 dark:text-gray-300" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
            <svg class="h-4 w-4 text-stone-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </button>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 top-[100%] z-50 mt-2 w-48 origin-top-right rounded-xl border border-stone-100 dark:border-gray-700 bg-white dark:bg-gray-800 py-1 shadow-lg ring-1 ring-black ring-opacity-5"
         style="display: none;">
        
        <div class="block border-b border-stone-100 dark:border-gray-700 px-4 py-2 sm:hidden">
            <p class="truncate text-sm font-medium text-stone-800 dark:text-gray-200">{{ auth()->user()->name }}</p>
            <p class="truncate text-xs text-stone-500 dark:text-gray-400">{{ auth()->user()->email }}</p>
        </div>

        <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-stone-700 dark:text-gray-300 transition hover:bg-stone-50 dark:hover:bg-gray-700 hover:text-ista-primary dark:hover:text-indigo-400">
            Profil
        </a>
        
        <button wire:click="logout" class="block w-full px-4 py-2 text-left text-sm text-stone-700 dark:text-gray-300 transition hover:bg-stone-50 dark:hover:bg-gray-700 hover:text-ista-primary dark:hover:text-indigo-400">
            Keluar
        </button>
    </div>
    @else
    <button @click="open = ! open" class="flex items-center gap-2 rounded-full p-1 transition hover:bg-stone-100 dark:hover:bg-gray-800 focus:outline-none">
        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-stone-300 dark:bg-gray-700 text-sm font-bold text-stone-600 dark:text-gray-300">
            G
        </div>
        <div class="hidden items-center gap-1 pr-2 sm:flex">
            <span class="text-sm font-medium text-stone-600 dark:text-gray-300">Guest</span>
            <svg class="h-4 w-4 text-stone-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </button>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 top-[100%] z-50 mt-2 w-48 origin-top-right rounded-xl border border-stone-100 dark:border-gray-700 bg-white dark:bg-gray-800 py-1 shadow-lg ring-1 ring-black ring-opacity-5"
         style="display: none;">
        
        <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-stone-700 dark:text-gray-300 transition hover:bg-stone-50 dark:hover:bg-gray-700 hover:text-ista-primary dark:hover:text-indigo-400">
            Login
        </a>
    </div>
    @endauth
</div>
