<template>
  <div :class="['post-item', { 'post-item--hidden': post.state === 'hidden' }]">
    <div class="post-item__meta">
      <time :datetime="post.created_at">{{ formatTime(post.created_at) }}</time>
      <StatusChip v-if="post.state !== 'visible'" :status="post.state" />
      <span v-if="isEditable" class="post-item__edit-window">
        Edit window: {{ editWindowRemaining }}
      </span>
    </div>

    <!-- Edit mode -->
    <template v-if="editing">
      <div v-if="blockedTerms.length > 0" class="sensitive-word-alert" role="alert">
        <p>Blocked terms:</p>
        <ul><li v-for="t in blockedTerms" :key="t.term"><code>{{ t.term }}</code></li></ul>
      </div>
      <textarea v-model="editBody" rows="4" class="field__input" :disabled="submitting" />
      <div class="post-item__actions">
        <button class="btn btn--sm btn--secondary" @click="cancelEdit()">Cancel</button>
        <button
          class="btn btn--sm btn--primary"
          :disabled="submitting || blockedTerms.length > 0"
          @click="saveEdit()"
        >
          {{ submitting ? 'Saving…' : 'Save' }}
        </button>
      </div>
      <p v-if="editError" class="form-error">{{ editError }}</p>
    </template>

    <template v-else>
      <p class="post-item__body">{{ post.body }}</p>
      <div class="post-item__actions">
        <button v-if="canEdit && !readOnly && isEditable" class="btn btn--sm btn--ghost" @click="startEdit()">Edit</button>
        <button v-if="!readOnly && post.state === 'visible'" class="btn btn--sm btn--ghost" @click="showReport = true">Report</button>

        <!-- Moderation actions -->
        <template v-if="canModerate">
          <button v-if="post.state === 'visible'" class="btn btn--sm btn--danger" :disabled="readOnly" @click="hidePost()">Hide</button>
          <button v-if="post.state === 'hidden'"  class="btn btn--sm btn--secondary" :disabled="readOnly" @click="restorePost()">Restore</button>
        </template>
      </div>
    </template>

    <!-- Report modal -->
    <ConfirmModal
      :open="showReport"
      title="Report Post"
      message="Describe the reason for this report:"
      confirm-label="Submit Report"
      @confirm="submitReport()"
      @cancel="showReport = false"
    >
      <textarea v-model="reportReason" rows="3" class="field__input" placeholder="Reason…" />
    </ConfirmModal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import type { Post } from '@/types/api'
import { threadsAdapter }    from '@/adapters/threads'
import { moderationAdapter } from '@/adapters/moderation'
import StatusChip   from '@/components/ui/StatusChip.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'

const props = defineProps<{
  post: Post
  threadId: number
  canEdit: boolean
  canModerate: boolean
  readOnly: boolean
}>()
const emit = defineEmits<{ edited: []; reported: []; hidden: []; restored: [] }>()

const editing     = ref(false)
const editBody    = ref(props.post.body)
const editError   = ref('')
const submitting  = ref(false)
const blockedTerms = ref<Array<{ term: string; start: number; end: number }>>([])
const showReport  = ref(false)
const reportReason = ref('')

const EDIT_WINDOW_MS = 15 * 60 * 1000

const isEditable = computed(() => {
  const createdMs = new Date(props.post.created_at).getTime()
  return Date.now() - createdMs < EDIT_WINDOW_MS
})

const editWindowRemaining = computed(() => {
  const createdMs    = new Date(props.post.created_at).getTime()
  const remainingMs  = EDIT_WINDOW_MS - (now.value - createdMs)
  if (remainingMs <= 0) return '0:00'
  const mins = Math.floor(remainingMs / 60000)
  const secs = Math.floor((remainingMs % 60000) / 1000)
  return `${mins}:${secs.toString().padStart(2, '0')}`
})

const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | null = null

onMounted(() => { timer = setInterval(() => { now.value = Date.now() }, 5000) })
onUnmounted(() => { if (timer) clearInterval(timer) })

function formatTime(iso: string) { return new Date(iso).toLocaleString() }

function startEdit() { editing.value = true; editBody.value = props.post.body }
function cancelEdit() { editing.value = false; editError.value = '' }

async function saveEdit() {
  editError.value = ''
  if (!editBody.value.trim()) { editError.value = 'Body cannot be empty.'; return }
  submitting.value = true
  try {
    await threadsAdapter.updatePost(props.threadId, props.post.id, { body: editBody.value })
    editing.value = false
    emit('edited')
  } catch (e: any) {
    if (e?.code === 'SENSITIVE_WORDS_BLOCKED') {
      blockedTerms.value = e.blocked_terms ?? []
      editError.value    = 'Blocked words detected.'
    } else if (e?.code === 'EDIT_WINDOW_EXPIRED') {
      editError.value = 'The 15-minute edit window has expired.'
    } else {
      editError.value = e?.message ?? 'Failed to save.'
    }
  } finally {
    submitting.value = false
  }
}

async function hidePost() {
  await moderationAdapter.hidePost(props.threadId, props.post.id, 'Admin action')
  emit('hidden')
}

async function restorePost() {
  await moderationAdapter.restorePost(props.threadId, props.post.id)
  emit('restored')
}

async function submitReport() {
  if (!reportReason.value.trim()) return
  await moderationAdapter.reportPost(props.post.id, reportReason.value)
  showReport.value   = false
  reportReason.value = ''
  emit('reported')
}
</script>
