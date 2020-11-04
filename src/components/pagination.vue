<template>
  <div class="text-xs-center pt-2">
    <v-pagination
      v-model="pagination.page"
      :length="pages"
      :total-visible="$vuetify.breakpoint.xsOnly ? 5 : 7"
    ></v-pagination>
  </div>
</template>

<script>
module.exports = {
  props: {
    totalItems: { type: Number, default: 0 },
    pagination: {
      type: Object,
      default() {
        return {}
      }
    },
    sort: {
      type: Object,
      default() {
        return {}
      }
    }
  },
  data() {
    return {}
  },
  computed: {
    pages() {
      if (this.pagination.rowsPerPage == null || this.totalItems == null) {
        return 0
      }
      return Math.ceil(this.totalItems / this.pagination.rowsPerPage)
    }
  },
  watch: {
    pagination: {
      handler() {
        this.sort.order_by =
          !!this.pagination.sortBy[0] === true ? this.pagination.sortBy[0] : ''
        this.sort.sort = !!this.pagination.sortDesc[0] === true ? 'DESC' : 'ASC'
        this.fetch()
      },
      deep: true
    }
  },
  methods: {
    fetch() {
      this.$emit('fetch')
    }
  }
}
</script>
