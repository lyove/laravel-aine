<template>
    <transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
    <div class="fixed z-50 inset-0 overflow-y-auto" v-show="show">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center  sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="close">
                <div class="absolute inset-0 bg-black opacity-50"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-md text-left overflow-hidden shadow-xl transform transition-all modal-pop-in sm:my-8 sm:align-middle w-full relative" :class="maxWidthClass" role="dialog" aria-modal="true" aria-labelledby="modal-headline">
                
                <!-- Close button for modals without header -->
                <button
                    v-if="disableHeader && closeable"
                    @click="close"
                    class="absolute top-3 right-3 z-10 text-gray-400 hover:text-gray-600 transition-colors p-1 bg-white rounded-full shadow-sm"
                    aria-label="Close"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <div v-if="!disableHeader" class="px-6 py-4 border-b border-gray-100 bg-white">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            <slot name="title">
                            </slot>
                        </h3>
                        <button
                            v-if="closeable"
                            @click="close"
                            class="text-gray-400 hover:text-gray-600 transition-colors p-1 -mr-1"
                            aria-label="Close"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div :class="{'px-6 py-4': !noContenPadding}">
                    <slot name="content">
                    </slot>
                </div>
                
                <div v-if="!disableFooter" class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-2 mt-5">
                    <slot name="footer">
                    </slot>
                </div>
            </div>
        </div>
    </div>
    </transition>
</template>

<script>
    export default {
        props: {
            show: {
                default: false
            },
            maxWidth: {
                default: '3xl'
            },
            closeable: {
                default: true
            },
            disableHeader: {
                default: false
            },
            disableFooter: {
                default: false
            },
            noContenPadding:{
                default: false
            } 
        },

        methods: {
            close() {
                if (this.closeable) {
                    this.$emit('close')
                }
            }
        },

        watch: {
            show: {
                immediate: true,
                handler: (show) => {
                    if (show) {
                        document.body.style.overflow = 'hidden'
                    } else {
                        document.body.style.overflow = null
                    }
                }
            }
        },

        created() {
            this._closeOnEscape = (e) => {
                if (e.key === 'Escape' && this.show) {
                    this.close()
                }
            }
            document.addEventListener('keydown', this._closeOnEscape)
        },

        unmounted() {
            document.removeEventListener('keydown', this._closeOnEscape)
        },

        computed: {
            maxWidthClass() {
                return {
                    'sm': 'sm:max-w-sm',
                    'md': 'sm:max-w-md',
                    'lg': 'sm:max-w-lg',
                    'xl': 'sm:max-w-xl',
                    '2xl': 'sm:max-w-2xl',
                    '3xl': 'sm:max-w-3xl',
                    '4xl': 'sm:max-w-4xl',
                    '5xl': 'sm:max-w-5xl',
                    '6xl': 'sm:max-w-6xl',
                    '7xl': 'sm:max-w-7xl',
                }[this.maxWidth]
            }
        }
    }
</script>

<style scoped>
    @keyframes modal-pop-in {
        0% {
            opacity: 0;
            transform: scale(0.95) translateY(8px);
        }
        100% {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .modal-pop-in {
        animation: modal-pop-in 0.2s ease-out;
    }
</style>
