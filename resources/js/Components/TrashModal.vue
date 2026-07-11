<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { confirmDelete } from '@/utils/confirm'

const props = defineProps({
    endpoint: String,
})

const emit = defineEmits(['close', 'updated'])

const items = ref([])
const loading = ref(false)

// Load dư liệu thùng rác khi modal được mở
const loadTrash = async () => {
    if (!props.endpoint) return // ❗ chặn undefined

    loading.value = true
    try {
        const res = await fetch(`/${props.endpoint}/trash`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })

        items.value = await res.json()
    } catch (e) {
        console.error('Lỗi load trash', e)
    }
    loading.value = false
}

onMounted(loadTrash)

/* ================= RESTORE ================= */
const restore = (id) => {
    router.post(`/${props.endpoint}/${id}/restore`, {}, {
        onSuccess: () => {
            items.value = items.value.filter(i => i.id !== id)
            emit('updated')
        }
    })
}

/* ================= FORCE DELETE ================= */
const forceDelete = (id) => {
    confirmDelete('Bạn có chắc chắn muốn xóa vĩnh viễn?', () => {
        router.delete(`/${props.endpoint}/${id}/force`, {
            onSuccess: () => {
                items.value = items.value.filter(i => i.id !== id)
                emit('updated')
            }
        })
    })
}
</script>

<template>
  <div class="w-full">
      <div v-if="loading" class="text-center p-5 text-gray-500">
          Đang tải...
      </div>

      <table v-else class="w-full border">
          <thead class="bg-gray-100">
              <tr>
                  <th class="p-2 w-16">ID</th>
                  <th class="p-2">Tên</th>
                  <th class="p-2 w-40 text-center">Hành động</th>
              </tr>
          </thead>

          <tbody>
              <tr v-for="item in items" :key="item.id" class="border-t">
                  <td class="p-2 text-center">{{ item.id }}</td>
                  <td class="p-2">{{ item.name }}</td>

                  <td class="p-2 text-center">
                      <div class="flex gap-2 justify-center">
                          <button @click="restore(item.id)" class="px-2 py-1 bg-green-600 text-white text-sm rounded">
                              Khôi phục
                          </button>
                          <button @click="forceDelete(item.id)" class="px-2 py-1 bg-red-600 text-white text-sm rounded">
                              Xóa
                          </button>
                      </div>
                  </td>
              </tr>

              <tr v-if="items.length === 0">
                  <td colspan="3" class="text-center p-5 text-gray-400">
                      Thùng rác trống
                  </td>
              </tr>
          </tbody>
      </table>
  </div>
</template>