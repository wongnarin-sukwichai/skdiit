<script setup>
defineProps({
    tagline: { type: Boolean, default: false },
});
</script>

<template>
    <span class="inline-flex items-center gap-2.5">
        <!-- Interlaced knot mark -->
        <svg viewBox="0 0 48 48" class="size-11 shrink-0" aria-hidden="true">
            <defs>
                <linearGradient id="skdiit-gold" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#ffe58a" />
                    <stop offset=".5" stop-color="#ffcc29" />
                    <stop offset="1" stop-color="#d49b00" />
                </linearGradient>
            </defs>
            <g fill="none" stroke="url(#skdiit-gold)" stroke-width="4.5" stroke-linejoin="round">
                <rect x="9" y="9" width="17" height="17" rx="2" transform="rotate(45 17.5 17.5)" />
                <rect x="22" y="22" width="17" height="17" rx="2" transform="rotate(45 30.5 30.5)" />
                <rect x="22" y="9" width="17" height="17" rx="2" transform="rotate(45 30.5 17.5)" />
                <rect x="9" y="22" width="17" height="17" rx="2" transform="rotate(45 17.5 30.5)" />
            </g>
        </svg>
        <span class="flex flex-col leading-none">
            <span class="gold-text text-[2rem] font-bold tracking-wider">SKDIIT</span>
            <span v-if="tagline" class="mt-1 text-[0.6rem] font-medium tracking-[0.2em] text-neutral-300 uppercase">
                SKDIIT 2027 Academic Conference
            </span>
        </span>
    </span>
</template>
