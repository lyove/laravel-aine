import { pinyin } from 'pinyin-pro';

export default {
    install(app){
        app.config.globalProperties.$slugify = function(text, ampersand = 'and'){
            if (text === null || text === undefined) {
                return '';
            }

            let converted = text.toString();

            if (/[\u4e00-\u9fff]/.test(converted)) {
                converted = pinyin(converted, {
                    toneType: 'none',
                    type: 'array',
                    nonZh: 'consecutive',
                }).join('');
            }

            const a = 'àáäâèéëêìíïîòóöôùúüûñçßÿýỳœæŕśńṕẃǵǹḿǘẍźḧğüşöçı'
            const b = 'aaaaeeeeiiiioooouuuuncsyyyoarsnpwgnmuxzhgusoci'
            const p = new RegExp(a.split('').join('|'), 'g')
    
            return converted.toLowerCase()
                .replace(/[\s_]+/g, '-')
                .replace(p, c =>
                b.charAt(a.indexOf(c)))
                .replace(/&/g, `-${ampersand}-`)
                .replace(/[^\w-]+/g, '')
                .replace(/--+/g, '-')
            .replace(/^-+|-+$/g, '');
        }
    }
}
