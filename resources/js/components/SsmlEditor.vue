<template>
    <!-- HTML mode: render full rich-text content, then hydrate every .ssml-block -->
    <div v-if="html" ref="htmlHost" class="ssml-editor-html" v-html="sanitizedHtml"></div>

    <!-- Model mode: single SSML model, editable or read-only -->
    <div v-else ref="host" class="ssml-editor" :class="{ 'ssml-editor--readonly': readOnly }" :style="{ minHeight: minHeight }"></div>
</template>

<script>
import { loadSsmlEditor } from "../utils/loadSsmlEditor";

/**
 * SsmlEditor
 */
export default {
    name: "SsmlEditor",
    props: {
        modelValue: {
            type: [String, Object],
            default: "",
        },
        html: {
            type: Boolean,
            default: false,
        },
        readOnly: {
            type: Boolean,
            default: false,
        },
        placeholder: {
            type: String,
            default: "",
        },
        minHeight: {
            type: String,
        },
        className: {
            type: String,
            default: "",
        },
        features: {
            type: [Object, Boolean],
            default: undefined,
        },
    },
    emits: ["update:modelValue", "change", "ready"],
    data() {
        return {
            editor: null,
            ssmlInstances: [],
            internalUpdating: false,
            loadError: "",
        };
    },
    computed: {
        sanitizedHtml() {
            return this.sanitizeHtml(this.modelValue);
        },
    },
    watch: {
        modelValue: {
            handler(val) {
                if (this.html) {
                    this.$nextTick(() => this.renderHtmlBlocks());
                    return;
                }
                if (this.internalUpdating || !this.editor) {
                    return;
                }
                const next = this.normalizeModel(val);
                const current = this.editor.getValue();
                if (JSON.stringify(next) !== JSON.stringify(current)) {
                    this.editor.setValue(next);
                }
            },
            deep: true,
        },
        readOnly(val) {
            if (!this.html) {
                this.editor?.setOptions({ readOnly: val });
            }
        },
        placeholder(val) {
            if (!this.html) {
                this.editor?.setOptions({ placeholder: val });
            }
        },
        className(val) {
            if (!this.html) {
                this.editor?.setOptions({ className: val });
            }
        },
        features: {
            handler(val) {
                if (!this.html) {
                    this.editor?.setOptions({ features: val });
                }
            },
            deep: true,
        },
    },
    mounted() {
        if (this.html) {
            this.$nextTick(() => this.renderHtmlBlocks());
        } else {
            this.init();
        }
    },
    beforeUnmount() {
        if (this.html) {
            this.destroyHtmlBlocks();
        } else {
            this.destroy();
        }
    },
    methods: {
        // ================================================================
        // HTML sanitization (prevent XSS)
        // ================================================================
        sanitizeHtml(html) {
            if (!html) return "";
            if (typeof html !== "string") return "";

            const doc = new DOMParser().parseFromString(html, "text/html");

            // Remove dangerous elements
            const dangerous = doc.querySelectorAll("script, iframe, object, embed, link, meta, style, form");
            dangerous.forEach(el => el.remove());

            // Remove event handlers and dangerous attributes from all elements
            doc.querySelectorAll("*").forEach(el => {
                for (const attr of Array.from(el.attributes)) {
                    const name = attr.name.toLowerCase();
                    // Remove on* event handlers
                    if (name.startsWith("on")) {
                        el.removeAttribute(attr.name);
                        continue;
                    }
                    // Remove javascript: URLs
                    if (name === "href" || name === "src" || name === "action" || name === "xlink:href") {
                        const value = attr.value.trim().toLowerCase();
                        if (value.startsWith("javascript:") || value.startsWith("data:text/html") || value.startsWith("vbscript:")) {
                            el.removeAttribute(attr.name);
                        }
                    }
                    // Remove srcdoc
                    if (name === "srcdoc") {
                        el.removeAttribute(attr.name);
                    }
                }
            });

            return doc.body.innerHTML;
        },

        // ================================================================
        // Model parsing
        // ================================================================
        normalizeModel(val) {
            if (val === null || val === undefined || val === "") {
                return "";
            }
            if (typeof val === "object") {
                return val;
            }
            try {
                return JSON.parse(val);
            } catch (e) {
                return val;
            }
        },

        // ================================================================
        // html mode: hydrate .ssml-block elements into read-only renderers
        // ================================================================

        async renderHtmlBlocks() {
            this.destroyHtmlBlocks();

            if (!this.$refs.htmlHost) {
                return;
            }

            const blocks = this.$refs.htmlHost.querySelectorAll(".ssml-block");
            if (blocks.length === 0) {
                return;
            }

            let SSMLEditor;
            try {
                SSMLEditor = await loadSsmlEditor();
            } catch (e) {
                this.loadError = e.message || String(e);
                console.error("Failed to load SSML editor:", e);
                return;
            }

            blocks.forEach((block) => {
                const data = block.getAttribute("data-ssml");
                if (!data) {
                    return;
                }
                try {
                    const model = JSON.parse(data);
                    block.innerHTML = "";
                    const editor = new SSMLEditor({
                        el: block,
                        value: model,
                        readOnly: true,
                    });
                    this.ssmlInstances.push(editor);
                } catch (e) {
                    console.error("Failed to render SSML block:", e);
                }
            });
        },

        destroyHtmlBlocks() {
            this.ssmlInstances.forEach((editor) => {
                try {
                    editor.destroy();
                } catch (e) {
                    // ignore destroy errors
                }
            });
            this.ssmlInstances = [];
        },

        // ================================================================
        // model mode: single editor instance
        // ================================================================

        async init() {
            if (this.editor) {
                return;
            }

            let SSMLEditor;
            try {
                SSMLEditor = await loadSsmlEditor();
            } catch (e) {
                this.loadError = e.message || String(e);
                console.error("Failed to load SSML editor:", e);
                return;
            }

            if (!this.$refs.host) {
                return;
            }

            this.editor = new SSMLEditor({
                el: this.$refs.host,
                value: this.normalizeModel(this.modelValue),
                placeholder: this.placeholder,
                readOnly: this.readOnly,
                className: this.className,
                features: this.features,
                onChange: () => {
                    const model = this.editor ? this.editor.getValue() : null;
                    if (!model) {
                        return;
                    }
                    this.internalUpdating = true;
                    this.$emit("update:modelValue", JSON.stringify(model));
                    this.$emit("change", model);
                    this.$nextTick(() => {
                        this.internalUpdating = false;
                    });
                },
            });

            this.$emit("ready", this.editor);
        },

        destroy() {
            if (this.editor) {
                try {
                    this.editor.destroy();
                } catch (e) {
                    // ignore destroy errors
                }
                this.editor = null;
            }
        },

        // ================================================================
        // Public API (available via template ref)
        // ================================================================

        /** Current value: raw HTML (html mode) or model object (model mode). */
        getValue() {
            if (this.html) {
                return this.modelValue;
            }
            if (this.editor) {
                return this.editor.getValue();
            }
            return this.normalizeModel(this.modelValue);
        },

        /** Current value as a JSON string. */
        getJson() {
            if (this.html) {
                return this.modelValue || "";
            }
            return JSON.stringify(this.getValue());
        },

        /** Generated SSML markup (model mode). Pass { includeHints: true } for a lossless round-trip. */
        getSSML(options) {
            if (!this.html && this.editor && typeof this.editor.getSSML === "function") {
                return this.editor.getSSML(options);
            }
            return "";
        },

        /** Replace the whole model (model mode only). */
        setValue(value) {
            if (this.html) {
                console.warn(
                    "SsmlEditor: setValue is not available in html mode; update :model-value instead."
                );
                return;
            }
            if (this.editor) {
                this.editor.setValue(this.normalizeModel(value));
            }
        },

        /** Load a model from an SSML XML string (model mode only). */
        setSSML(xml) {
            if (!this.html && this.editor && typeof this.editor.setSSML === "function") {
                this.editor.setSSML(xml);
            }
        },

        /** Update editor options at runtime (model mode only). */
        setOptions(opts) {
            if (!this.html && this.editor && typeof this.editor.setOptions === "function") {
                this.editor.setOptions(opts);
            }
        },

        focus() {
            if (!this.html && this.editor && typeof this.editor.focus === "function") {
                this.editor.focus();
            }
        },
    },
};
</script>

<style scoped>
/* ---- model mode container ---- */
.ssml-editor {
    width: 100%;
    box-sizing: border-box;
}

.ssml-editor :deep(.se-editor) {
    min-height: 200px;
}

.ssml-editor :deep(.se-placeholder) {
    top: 10px;
    left: 14px;
    line-height: 2rem;
    font-size: 15px;
    display: block;
    width: calc(100% - 28px);
    color: #9ca3af;
}

/* Read-only model mode: drop the editing chrome, behave like a renderer */
.ssml-editor--readonly {
    border-color: transparent;
    background: transparent;
    padding: 0;
    overflow: visible;
    max-height: none;
}

/* ---- html mode container ---- */
.ssml-editor-html {
    width: 100%;
    box-sizing: border-box;
}

/* Hydrated SSML blocks inside html mode render as plain content (no editor chrome) */
.ssml-editor-html :deep(.se-editor) {
    border: none;
    background: transparent;
    box-shadow: none;
}

.ssml-editor-html :deep(.se-content) {
    padding: 0;
}
</style>
