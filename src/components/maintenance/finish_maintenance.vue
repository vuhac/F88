<template>
  <div style="display:inline-block;">
    <v-btn color="secondary" small @click.stop="openFinishDialog()"
      >完成</v-btn
    >
    <v-dialog v-model="finishDialog" dark persistent max-width="650px">
        <v-card>
        <v-card-title>
            維運完成
        </v-card-title>
        <v-card-text>
            確定已完成？
        </v-card-text>
        <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn @click="closeFinishDialog" color="danger">關閉</v-btn>
            <v-btn @click="callfinish" color="success">確定</v-btn>
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
    }
  },
  data() {
    return {
      finish_data: {},
      finishDialog: false
    }
  },
  methods: {
    callfinish() {
      const finishData = JSON.parse(JSON.stringify(this.finish_data))

      finishData.execution_status = 'finish'
      finishData.last_end_date = moment()
        .add(1, 'minutes')
        .format('YYYY-MM-DD HH:mm:ss')
      finishData.maintenanc_type = finishData.maintenance_type

      delete finishData.byday
      delete finishData.cycle
      delete finishData.end_date
      delete finishData.note
      delete finishData.start_date
      delete finishData.status
      delete finishData.website
      delete finishData.weekly_byday
      delete finishData.maintenance_type

      this.$emit('call-finish', finishData, finishData.id)
      this.finishDialog = false
    },
    openFinishDialog() {
      this.finishDialog = true
      const temp = JSON.parse(JSON.stringify(this.item))
      this.finish_data = { ...temp }
    },
    closeFinishDialog() {
      this.finishDialog = false
    }
  }
}
</script>
