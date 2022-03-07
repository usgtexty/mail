<template>
	<div class="custom-tinymce-editor" :id="id" v-html="text"></div>
</template>

<script>
import 'tinymce'
import 'tinymce/themes/silver'
import 'tinymce/icons/default'
import 'tinymce/skins/ui/oxide/skin.css'
import 'tinymce/plugins/advlist'
import 'tinymce/plugins/code'
import 'tinymce/plugins/autoresize'
import 'tinymce/plugins/autolink'
import 'tinymce/plugins/link'
import 'tinymce/plugins/lists'
import 'tinymce/plugins/quickbars'
import 'tinymce/plugins/image'
import 'tinymce/plugins/help'
import 'tinymce/skins/ui/oxide/content.css'
import 'tinymce/skins/content/default/content.css'
export default {
	name: 'TinyMCE',
	props: {
		value: {
			type: String,
			required: true,
		}
	},
	data () {
		return {
			id: 'tinymce-x-app-',
			text: ''
		}
	},
	mounted () {
		this.id = 'tinymce-x-app-' + this._uid
		this.text = this.value
		setTimeout(() => {
			tinymce.init({
				selector: '#' + this.id,
				menubar: false,
				statusbar: false,
				plugins: [
					'advlist code autoresize autolink link lists image help'
				],
				toolbar: 'undo redo | bold italic | forecolor backcolor | code | alignleft aligncenter alignright alignjustify | bullist numlist | inserttable link image',
				contextmenu: 'undo redo | inserttable | cell row column deletetable | help',
				skin: false,
				content_css: false,
				content_style: false,
				setup: editor => {
					editor.on('input', e => {
						this.$emit('input', e.target.innerHTML)
					});
				}
			})
		}, 200)
	},
	beforeDestroy () {
		tinymce.remove('#' + this.id)
	}
}
</script>

<style>
.tox.tox-silver-sink.tox-tinymce-aux {
	z-index: 9999;
}
.tox.tox-tinymce {
	flex-grow: 1;
}
</style>

<style lang="scss" scoped>
.custom-tinymce-editor {
	border: none;
}
.mce-content-body table[data-mce-selected] {
	outline: 0;
}
</style>
