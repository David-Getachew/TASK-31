import { config } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach } from 'vitest'
import { indexedDB, IDBKeyRange } from 'fake-indexeddb'

Object.defineProperty(globalThis, 'indexedDB', { value: indexedDB, writable: true })
Object.defineProperty(globalThis, 'IDBKeyRange', { value: IDBKeyRange, writable: true })

beforeEach(() => {
  setActivePinia(createPinia())
})

config.global.stubs = {}
