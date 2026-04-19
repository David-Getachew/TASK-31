import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/adapters/http'
import type { Course, Section, Thread, Post } from '@/types/api'
import type { PaginatedResponse, ApiResponse } from '@/types'

function extractCollection<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) return payload as T[]
  if (payload && typeof payload === 'object') {
    const nested = (payload as { data?: unknown }).data
    if (Array.isArray(nested)) return nested as T[]
  }
  return []
}

export const useCoursesStore = defineStore('courses', () => {
  const courses    = ref<Course[]>([])
  const sections   = ref<Section[]>([])
  const threads    = ref<Thread[]>([])
  const activeThread = ref<Thread | null>(null)
  const posts      = ref<Post[]>([])
  const loading    = ref(false)
  const error      = ref<string | null>(null)

  async function fetchCourses() {
    loading.value = true
    error.value   = null
    try {
      const res   = await http.get<PaginatedResponse<Course>>('/courses')
      courses.value = extractCollection<Course>(res.data.data)
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to load courses'
    } finally {
      loading.value = false
    }
  }

  async function fetchSections(courseId: number) {
    loading.value = true
    error.value   = null
    try {
      const res     = await http.get<PaginatedResponse<Section>>('/sections', { params: { course_id: courseId } })
      sections.value = extractCollection<Section>(res.data.data)
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to load sections'
    } finally {
      loading.value = false
    }
  }

  async function fetchThreads(sectionId: number, params?: { thread_type?: string; search?: string }) {
    loading.value = true
    error.value   = null
    try {
      const res    = await http.get<PaginatedResponse<Thread>>('/threads', { params: { section_id: sectionId, ...params } })
      threads.value = extractCollection<Thread>(res.data.data)
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to load discussions'
    } finally {
      loading.value = false
    }
  }

  async function fetchThread(id: number) {
    loading.value = true
    error.value   = null
    try {
      const res          = await http.get<ApiResponse<Thread>>(`/threads/${id}`)
      activeThread.value = res.data.data
    } catch (e: any) {
      error.value = e?.message ?? 'Thread not found'
    } finally {
      loading.value = false
    }
  }

  async function fetchPosts(threadId: number) {
    loading.value = true
    try {
      const res   = await http.get<PaginatedResponse<Post>>(`/threads/${threadId}/posts`)
      posts.value = extractCollection<Post>(res.data.data)
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to load posts'
    } finally {
      loading.value = false
    }
  }

  function reset() {
    courses.value      = []
    sections.value     = []
    threads.value      = []
    activeThread.value = null
    posts.value        = []
    error.value        = null
  }

  return {
    courses, sections, threads, activeThread, posts,
    loading, error,
    fetchCourses, fetchSections, fetchThreads, fetchThread, fetchPosts, reset,
  }
})
