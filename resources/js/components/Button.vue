<template>
    <button :type="type" :class="[bgColor, textColor]" :disabled="disabled" class="items-center border border-transparent rounded-md text-sm focus:outline-none transition ease-in-out duration-150">
        <slot></slot>
    </button>
</template>

<script>
    export default {
        props: {
            type: {
                type: String,
                default: 'submit',
            },
            color: {
                type: String,
                default: 'gray-800'
            },
            hover: {
                type: String,
                default: null
            },
            padding: {
                type: String,
                default: null
            },
            disabled: {
                type: Boolean,
                default: false
            }
        },
        computed: {
            bgColor(){
                let cls = '';
                if(this.disabled){
                    cls = 'bg-indigo-200 cursor-not-allowed';
                } else {
                    let bgcolor = 'bg-'+this.color;
                    let arr = this.color.split('-');
                    let shade = parseInt(arr[1]);
                    let hover = '';
                    let active = '';
                    if (this.hover != null) {
                        hover = 'bg-'+this.hover;
                    }
                    if (!isNaN(shade)) {
                        if (this.hover == null) {
                            hover = 'bg-'+arr[0]+'-'+(shade + 200);
                        }
                        active = 'bg-'+arr[0]+'-'+(shade + 200);
                    }
                    let focus = 'border-'+arr[0]+'-'+(shade - 100);
                    let focusShadow = 'shadow-outline-'+arr[0];

                    cls = bgcolor + (hover ? ' hover:'+hover : '') + (active ? ' active:'+active : '');
                }

                if(this.padding === null) {
                    cls += ' p-2 ';
                } else {
                    cls += ' '+this.padding+' ';
                }
                
                return cls;
            },
            textColor(){
                // Light/white backgrounds need dark text; everything else keeps white.
                // -200 and lighter shades are light backgrounds → dark text.
                if (this.color === 'white' || this.color.endsWith('-50') || this.color.endsWith('-100') || this.color.endsWith('-200')) {
                    return 'text-gray-700';
                }
                return 'text-white';
            }
        }
    }
</script>
