<!--
  - @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div class="section">
		<div>
			<input
				id="signature-above-quote-toggle"
				v-model="signatureAboveQuote"
				type="checkbox"
				class="checkbox">
			<label for="signature-above-quote-toggle">
				{{ t("mail", "Place signature above quoted text") }}
			</label>
			<p>
				<em>
					{{ t("mail", "If you want to use visual signature, make sure check the \"Rich Text\" option below.") }}
					<svg width="24" height="24" focusable="false"><g fill-rule="nonzero"><path d="M9.8 15.7c.3.3.3.8 0 1-.3.4-.9.4-1.2 0l-4.4-4.1a.8.8 0 010-1.2l4.4-4.2c.3-.3.9-.3 1.2 0 .3.3.3.8 0 1.1L6 12l3.8 3.7zM14.2 15.7c-.3.3-.3.8 0 1 .4.4.9.4 1.2 0l4.4-4.1c.3-.3.3-.9 0-1.2l-4.4-4.2a.8.8 0 00-1.2 0c-.3.3-.3.8 0 1.1L18 12l-3.8 3.7z"></path></g></svg>
					{{ t("mail", " use it to use HTML signature.") }}
			</em>
			</p>
		</div>
		<Multiselect
			v-if="identities.length > 1"
			:allow-empty="false"
			:options="identities"
			:searchable="false"
			:value="identity"
			label="label"
			track-by="id"
			@select="changeIdentity" />
		<div class="signature-editor">
			<TinyMCE v-model="signature" />
		</div>
		<button
			class="primary"
			:class="loading ? 'icon-loading-small-dark' : 'icon-checkmark-white'"
			:disabled="loading"
			@click="saveSignature">
			{{ t("mail", "Save signature") }}
		</button>
		<button v-if="signature" class="button-text" @click="deleteSignature">
			{{ t("mail", "Delete") }}
		</button>
	</div>
</template>

<script>
import logger from '../logger'
import TinyMCE from './TinyMCE'
import { detect, toHtml } from '../util/text'
import Vue from 'vue'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

export default {
	name: 'SignatureSettings',
	components: {
		TinyMCE,
		Multiselect,
	},
	props: {
		account: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			loading: false,
			bus: new Vue(),
			identity: null,
			signature: '',
			signatureAboveQuote: this.account.signatureAboveQuote,
		}
	},
	computed: {
		identities() {
			const identities = this.account.aliases.map((alias) => {
				return {
					id: alias.id,
					label: alias.name + ' (' + alias.alias + ')',
					signature: alias.signature,
				}
			})

			identities.unshift({
				id: -1,
				label: this.account.name + ' (' + this.account.emailAddress + ')',
				signature: this.account.signature,
			})

			return identities
		},
	},
	watch: {
		async signatureAboveQuote(val, oldVal) {
			try {
				await this.$store.dispatch('patchAccount', {
					account: this.account,
					data: {
						signatureAboveQuote: val,
					},
				})
				logger.debug('signature above quoted updated to ' + val)
			} catch (e) {
				logger.error('could not update signature above quote', { e })
				this.signatureAboveQuote = oldVal
			}
		},
	},
	beforeMount() {
		this.changeIdentity(this.identities[0])
	},
	methods: {
		changeIdentity(identity) {
			logger.debug('select identity', { identity })
			this.identity = identity
			this.signature = identity.signature
				? toHtml(detect(identity.signature)).value
				: ''
		},
		async deleteSignature() {
			this.signature = null
			await this.saveSignature()
		},
		async saveSignature() {
			this.loading = true

			let dispatchType = 'updateAccountSignature'
			const payload = {
				account: this.account,
				signature: this.signature,
			}

			if (this.identity.id > -1) {
				dispatchType = 'updateAliasSignature'
				payload.aliasId = this.identity.id
			}

			return this.$store
				.dispatch(dispatchType, payload)
				.then(() => {
					logger.info('signature updated')
					this.loading = false
				})
				.catch((error) => {
					logger.error('could not update account signature', { error })
					throw error
				})
		},
	},
}
</script>

<style lang="scss" scoped>
.ck.ck-editor__editable_inline {
  width: 100%;
  max-width: 78vw;
  height: 100px;
  border-radius: var(--border-radius) !important;
  border: 1px solid var(--color-border) !important;
  box-shadow: none !important;
}

.primary {
  padding-left: 26px;
  background-position: 6px;
  color: var(--color-main-background);

  &:after {
    left: 14px;
  }
}

.button-text {
  background-color: transparent;
  border: none;
  color: var(--color-text-maxcontrast);
  font-weight: normal;

  &:hover,
  &:focus {
    color: var(--color-main-text);
  }
}
.section {
  display: block;
  padding: 0;
  margin-bottom: 23px;
}
.multiselect--single {
  width: 100%;
}
</style>
<style>
.ck-balloon-panel {
  z-index: 10000 !important;
}
</style>
