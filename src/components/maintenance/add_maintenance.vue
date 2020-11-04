<template>
  <div>
    <v-btn color="primary" small @click.stop="openAddDialog"
      >新增維護設定</v-btn
    >
    <v-dialog v-model="addDialog" persistent max-width="750px">
      <v-card>
        <v-card-title>
          <span>新增維護設定</span>
        </v-card-title>
        <v-card-text>
          <v-form ref="form" lazy-validation>
            <v-container grid-list-md fluid>
              <v-layout wrap>
                <v-flex xs12>
                  <v-radio-group
                    v-model="maintenance_type"
                    row
                    :rules="[fv.required]"
                    class="d-flex justify-space-between"
                  >
                    <v-radio
                      label="固定維護"
                      value="regular"
                      color="primary"
                    ></v-radio>
                    <v-radio
                      label="緊急維護"
                      value="emergency"
                      color="primary"
                    ></v-radio>
                    <v-radio
                      label="預約維護"
                      value="reserve"
                      color="primary"
                    ></v-radio>
                  </v-radio-group>
                </v-flex>
                <v-flex v-if="!frequencyTF" xs12 md6>
                  <time-picker
                    v-model="dl_new.start_time"
                    :datetime="dl_new.start_time"
                    :disabled="dateTF"
                    :rules="dateTF ? [] : [fv.required]"
                    label="*開始時間"
                  >
                  </time-picker>
                </v-flex>
                <v-flex v-else xs12 md6>
                  <datetime-picker
                    v-model="dl_new.start_date"
                    :datetime="dl_new.start_date"
                    :disabled="dateTF"
                    :rules="dateTF ? [] : [fv.required, start_dateCheck]"
                    label="*開始時間"
                  >
                  </datetime-picker>
                </v-flex>
                <v-flex v-if="!frequencyTF" xs12 md6>
                  <time-picker
                    v-model="dl_new.end_time"
                    :datetime="dl_new.end_time"
                    :rules="[fv.required, end_dateCheck]"
                    label="*預計結束時間"
                  >
                  </time-picker>
                </v-flex>
                <v-flex v-else xs12 md6>
                  <datetime-picker
                    v-model="dl_new.end_date"
                    :datetime="dl_new.end_date"
                    :rules="[fv.required, end_dateCheck]"
                    label="*預計結束時間"
                  >
                  </datetime-picker>
                </v-flex>
                <v-flex v-if="!frequencyTF" xs6>
                  <div>
                    <v-select
                      v-model="dl_new.cycle"
                      :items="frequency"
                      :rules="frequencyTF ? [] : [fv.required]"
                      label="*頻率"
                      item-text="name"
                      item-value="value"
                    ></v-select>
                  </div>
                </v-flex>
                <v-flex v-if="!frequencyTF" xs6>
                  <div>
                    <v-select
                      v-model="dl_new.byday"
                      :value="dl_new.byday"
                      :items="dl_new.cycle === 'MONTHLY' ? bydate : byday"
                      :rules="frequencyTF ? [] : [fv.required]"
                      label="*固定哪天"
                      item-text="name"
                      item-value="value"
                    ></v-select>
                  </div>
                </v-flex>
                <v-flex xs12 md12>
                  <v-textarea
                    v-model="dl_new.note"
                    outlined
                    label="備註"
                  ></v-textarea>
                </v-flex>
              </v-layout>
            </v-container>
            <small>*為必填選項</small>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="danger" @click="closeAddDialog">關閉</v-btn>
          <v-btn color="success" @click="callAddNewMaintenance">新增</v-btn>
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
    website: {
      type: Array,
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
    datetime: {
      type: String,
      default: '',
      required: false
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
      addDialog: false,
      dl_new: {
        website: website
      },
      frequencyTF: true,
      dateTF: false,
      valid: {
        1: { start_date: '', end_date: '' }
      }
    }
  },
  computed: {
    start_dateCheck() {
      return moment(this.dl_new.start_date).diff(
        moment(),
        'hours',
        true
      ) < 1
        ? '開始時間必須至少在現在時間1小時後'
        : true
    },
    end_dateCheck() {
      if (!this.frequencyTF) {
        return moment(
          moment().format('YYYY-MM-DD') + ' ' + this.dl_new.end_time
        ).diff(
          moment(
            moment().format('YYYY-MM-DD') + ' ' + this.dl_new.start_time
          )
        ) < 1
          ? '結束時間必須晚於開始時間'
          : true
      } else {
        return moment(this.dl_new.end_date).diff(
          moment(this.dl_new.start_date)
        ) < 1
          ? '結束時間必須晚於開始時間'
          : moment(this.dl_new.end_date).diff(
              moment(),
              'hours',
              true
            ) < 1
          ? '結束時間不得早於現在時間'
          : true
      }
    }
  },
  watch: {
    maintenance_type(value) {
      if (value === 'regular') {
        this.frequencyTF = false
        this.dateTF = false
      } else if (value === 'emergency') {
        this.frequencyTF = true
        this.dateTF = true
        this.dl_new.start_date = ''
        this.dl_new.cycle = ''
        this.dl_new.byday = ''
      } else {
        this.frequencyTF = true
        this.dateTF = false
        this.dl_new.cycle = ''
        this.dl_new.byday = ''
        this.dl_new.start_date = moment()
          .add(1, 'hours')
          .add(1, 'minutes')
          .format('YYYY-MM-DD HH:mm:ss')
      }
    }
  },
  methods: {
    callAddNewMaintenance() {
      if (this.$refs.form.validate()) {
        const data = JSON.parse(JSON.stringify(this.dl_new))
        if (!this.frequencyTF) {
          data.start_date = moment(
            moment().format('YYYY-MM-DD') + ' ' + this.dl_new.start_time
          )
            .add(1, 'days')
            .format('YYYY-MM-DD HH:mm:ss')
          data.end_date = moment(
            moment().format('YYYY-MM-DD') + ' ' + this.dl_new.end_time
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
        data.website = JSON.stringify(data.website)
        data.maintenance_type = this.maintenance_type
        data.status = 'start'
        if (this.dl_new.cycle === 'MONTHLY') {
          data.monyhly_byday = data.byday
          delete data.weekly_byday
        } else if (this.dl_new.cycle === 'WEEKLY') {
          data.weekly_byday = data.byday
          delete data.monyhly_byday
        }
        delete data.byday
        delete data.start_time
        delete data.end_time
        this.$emit('call-add-new-maintenance', data)
      } else {
        alert('必填選項未填')
      }
    },
    openAddDialog() {
      this.addDialog = true
      this.dl_new = {
        start_date: '',
        end_date: '',
        start_time: '',
        end_time: '',
        website: [{
          name: 'JIG',
          // url: this.website
          url: ["https://we379875.jutainet.com","https://yuhui.jutainet.com","https://beyuhui0802.jutainet.com"] //測試用
        }]
      }
    },
    closeAddDialog() {
      this.addDialog = false
      this.clearForm()
    },
    clearForm() {
      this.dl_new = {
        start_date: '',
        end_date: '',
        frequency: 'no'
      }
      this.maintenance_type = ''
      this.status = ''
      this.$refs.form.resetValidation()
    }
  }
}
</script>
