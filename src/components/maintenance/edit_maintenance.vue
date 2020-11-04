<template>
  <div style="display:inline-block;">
    <v-btn color="primary" small @click.stop="openEditDialog()"
      >編輯</v-btn
    >
    <v-dialog v-model="editDialog" persistent max-width="750px">
      <v-card>
        <v-card-title>
          <span>編輯維護設定</span>
        </v-card-title>
        <v-card-text>
          <v-form ref="form" lazy-validation>
            <v-container grid-list-md>
              <v-layout wrap>
                <v-flex xs12>
                  <v-text-field
                    v-model="edit_data.maintenance_type"
                    label="維護種類"
                    disabled
                  ></v-text-field>
                </v-flex>
                <v-flex v-if="!frequencyTF" xs12 md6>
                  <time-picker
                    v-model="edit_data.start_time"
                    :datetime="edit_data.start_time"
                    :rules="[fv.required]"
                    label="*開始時間"
                  >
                  </time-picker>
                </v-flex>
                <v-flex v-else xs12 md6>
                  <datetime-picker
                    v-model="edit_data.start_date"
                    label="*開始時間"
                    :error-messages="valid[1].date"
                    :datetime="edit_data.start_date"
                    :rules="[fv.required]"
                  >
                  </datetime-picker>
                </v-flex>
                <v-flex v-if="!frequencyTF" xs12 md6>
                  <time-picker
                    v-model="edit_data.end_time"
                    :datetime="edit_data.end_time"
                    :rules="[fv.required]"
                    label="*開始時間"
                  >
                  </time-picker>
                </v-flex>
                <v-flex v-else xs12 sm6>
                  <datetime-picker
                    v-model="edit_data.end_date"
                    label="*預計結束時間"
                    :error-messages="valid[1].date"
                    :datetime="edit_data.end_date"
                    :rules="[fv.required]"
                  >
                  </datetime-picker>
                </v-flex>
                <v-flex xs6>
                  <div>
                    <v-autocomplete
                      v-model="edit_data.cycle"
                      label="頻率"
                      :items="frequency"
                      item-text="name"
                      item-value="value"
                      :disabled="frequencyTF"
                    ></v-autocomplete>
                  </div>
                </v-flex>
                <v-flex xs6>
                  <div>
                    <v-autocomplete
                      v-model="edit_data.byday"
                      label="固定哪天"
                      :items="edit_data.cycle === 'MONTHLY' ? bydate : byday"
                      item-text="name"
                      item-value="value"
                      :disabled="frequencyTF"
                    ></v-autocomplete>
                  </div>
                </v-flex>
                <v-flex xs12 md12>
                  <v-textarea
                    v-model="edit_data.note"
                    outlined
                    label="*維護公告"
                    hint="*必填選項"
                    :rules="[ fv.max50]"
                  ></v-textarea>
                </v-flex>
                <v-radio-group
                  v-model="edit_data.status"
                  label="狀態"
                  row
                  class="d-flex justify-space-between"
                >
                  <v-radio label="開始" value="start" color="primary"></v-radio>
                  <v-radio label="暫停" value="pause" color="primary"></v-radio>
                  <v-radio label="取消" value="cancel" color="primary"></v-radio>
                </v-radio-group>
              </v-layout>
            </v-container>
            <small>*為必填選項</small>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="danger" @click="closeEditDialog">關閉</v-btn>
          <v-btn color="success" @click="callEdit">儲存</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
module.exports = {
  components: {
    'datetime-picker': httpVueLoader('../date_time_picker.vue'),
    'time-picker': httpVueLoader('../time_picker.vue')
  },
  props: {
    fv: {
      type: Object,
      default() {
        return {}
      }
    },
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
    },
    maintenanceType: {
      type: Array,
      default() {
        return []
      }
    },
    frequency: {
      type: Array,
      default() {
        return []
      }
    },
    maintenanceStatus: {
      type: Array,
      default() {
        return []
      }
    },
    executionStatus: {
      type: Array,
      default() {
        return []
      }
    },
    byday: {
      type: Array,
      default() {
        return []
      }
    },
    bydate: {
      type: Array,
      default() {
        return []
      }
    }
  },
  data() {
    return {
      moment,
      maintenance_type: '',
      status: '',
      editDialog: false,
      edit_data: {},
      stepper: 1,
      valid: {
        1: { website_code: '', website_name: '', date: '' },
        2: {},
        3: {}
      }
      // datetime: '2019-01-01 00:00'
    }
  },
  computed: {
    frequencyTF() {
      if (this.edit_data.maintenance_type === 'regular') {
        return false
      } else {
        return true
      }
    }
  },
  methods: {
    selectRemove(item) {
      const index = this.dl_new.operate.indexOf(item.value)
      if (index >= 0) this.dl_new.operate.splice(index, 1)
    },
    callEdit() {
      if (this.$refs.form.validate()) {
        const temp = JSON.parse(JSON.stringify(this.item))
        const data = JSON.parse(JSON.stringify(this.edit_data))
        data.website =
          !!data.website === true ? JSON.stringify(data.website) : '[]'
        if (!this.frequencyTF) {
          data.start_date = moment(
            moment().format('YYYY-MM-DD') + ' ' + this.edit_data.start_time
          )
            .add(1, 'days')
            .format('YYYY-MM-DD HH:mm:ss')
          data.end_date = moment(
            moment().format('YYYY-MM-DD') + ' ' + this.edit_data.end_time
          )
            .add(1, 'days')
            .format('YYYY-MM-DD HH:mm:ss')
        } else {
          data.start_date =
            !!data.start_date === true
              ? (data.start_date = moment(data.start_date).format(
                  'YYYY-MM-DD HH:mm:ss'
                ))
              : (data.start_date = moment()
                  .add(1, 'hours')
                  .add(1, 'minutes')
                  .format('YYYY-MM-DD HH:mm:ss'))
          data.end_date = moment(data.end_date).format('YYYY-MM-DD HH:mm:ss')
        }
        if (data.start_date === temp.start_date) {
          delete data.start_date
        } else {
          data.start_date = moment(data.start_date).format(
            'YYYY-MM-DD HH:mm:ss'
          )
        }
        if (data.end_date === temp.end_date) {
          delete data.end_date
        } else {
          data.end_date = moment(data.end_date).format(
            'YYYY-MM-DD HH:mm:ss'
          )
        }
        if (data.end_date === temp.end_date) {
          delete data.end_date
        } 
        if (data.maintenance_type !== 'regular') {
          delete data.cycle
        } else if (data.cycle === 'WEEKLY') {
          data.weekly_byday = data.byday
          delete data.monyhly_byday
        } else if (data.cycle === 'MONTHLY') {
          data.monyhly_byday = data.byday
          delete data.weekly_byday
        }
        delete data.id
        delete data.byday
        delete data.execution_status
        delete data.last_end_date
        const edit_id = this.edit_data.id
        this.$emit('call-edit', data, edit_id)
        this.editDialog = false
      } else {
        alert('必填選項未填')
      }
    },
    openEditDialog() {
      this.editDialog = true
      const temp = JSON.parse(JSON.stringify(this.item))
      const editdata = { ...temp }
      this.edit_data = editdata
      this.edit_data.start_time = moment(this.edit_data.start_date).format('HH:mm:ss')
      this.edit_data.end_time = moment(this.edit_data.end_date).format('HH:mm:ss')
    },
    closeEditDialog() {
      this.editDialog = false
    }
  }
}
</script>
