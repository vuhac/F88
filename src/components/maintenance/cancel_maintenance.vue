<template>
  <div style="display:inline-block;">
    <v-btn color="primary" small @click.stop="openCancelDialog()"
      >取消</v-btn
    >
    <v-dialog v-model="cancelDialog" persistent max-width="750px">
      <v-card>
        <v-card-title>
          <span>取消維護</span>
        </v-card-title>
        <v-card-text>
          確定要取消？
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="danger" @click="closeCancelDialog">取消</v-btn>
          <v-btn color="success" @click="callCancel">確定</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
module.exports = {
  props: {
    item: {
      type: Object,
      default() {
        return {}
      }
    },
    editData: {
      type: Object,
      default() {
        return {}
      }
    }
  },
  data() {
    return {
      cancelDialog: false,
      edit_data: {}
    }
  },
  methods: {
    callCancel() {
      const data = JSON.parse(JSON.stringify(this.edit_data))

      data.status = 'cancel'
      const edit_id = data.id

      delete data.start_date
      delete data.end_date
      delete data.website
      delete data.id
      delete data.weekly_byday
      delete data.monyhly_byday
      delete data.byday
      delete data.execution_status
      delete data.last_end_date
      delete data.cycle

      this.$emit('call-cancel', data, edit_id)
      this.cancelDialog = false
    },
    openCancelDialog() {
      this.cancelDialog = true
      const temp = JSON.parse(JSON.stringify(this.item))
      const editdata = { ...temp }
      this.edit_data = editdata
    },
    closeCancelDialog() {
      this.cancelDialog = false
    }
  }
}
</script>
