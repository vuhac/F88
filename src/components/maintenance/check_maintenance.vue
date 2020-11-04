<template>
  <div style="display:inline-block;">
    <v-btn color="warning" small @click.stop="openCheckDialog()"
      >確認</v-btn
    >
    <v-dialog v-model="checkDialog" dark persistent max-width="650px">
        <v-card>
        <v-card-title>
            維運確認
        </v-card-title>
        <v-card-text>
            確定要開站
        </v-card-text>
        <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn @click="closeCheckDialog" color="danger">關閉</v-btn>
            <v-btn @click="callCheck" color="success">確定</v-btn>
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
      check_data: {},
      checkDialog: false
    }
  },
  methods: {
    callCheck() {
      const data = JSON.parse(JSON.stringify(this.check_data))
      const check_id = this.check_data.id
      this.$emit('call-check', data, check_id)
      this.checkDialog = false
    },
    openCheckDialog() {
      this.checkDialog = true
      const temp = JSON.parse(JSON.stringify(this.item))
      this.check_data = { ...temp }
    },
    closeCheckDialog() {
      this.checkDialog = false
    }
  }
}
</script>
