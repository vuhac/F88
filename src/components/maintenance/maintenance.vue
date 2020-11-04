<template>
  <v-layout row justify-center align-center>
    <v-flex class="px-3" xs12>
      <v-subheader>
        維護設定(維護數量：{{ totalItems }})
        <v-spacer></v-spacer>
        <add
          ref="addSiteDialog"
          :FV="fv"
          :website="website"
          :maintenance-type="maintenanceType"
          :frequency="frequency"
          :maintenance-status="maintenanceStatus"
          :execution-status="executionStatus"
          :byday="byday"
          :bydate="bydate"
          @call-add-new-maintenance="add"
        ></add>
      </v-subheader>
      <v-data-table
        :headers="headers"
        :items="maintenance"
        :server-items-length="totalItems"
        :options.sync="pagination"
        :must-sort="true"
      >
        <template v-slot:item.execution_status="{ item }">
          <div v-if="item.execution_status">
            {{
              executionStatus.find((i) => i.value === item.execution_status)
                .name
            }}
          </div>
        </template>
        <template v-slot:item.note="{ item }">
          <div class="text-no-wrap text-truncate">
            {{ item.note }}
          </div>
        </template>
        <template v-slot:item.maintenance_type="{ item }">
          <v-chip
            :color="
              maintenanceStatus.find((i) => i.value === item.status).color
            "
            small
            filled
          >
            {{ maintenanceStatus.find((i) => i.value === item.status).name }}
          </v-chip>
          <v-tooltip v-if="$vuetify.breakpoint.width < 1680" bottom>
            <template v-slot:activator="{ on }">
              <v-icon
                :color="
                  maintenanceStatus.find((i) => i.value === item.status).color
                "
                v-on="on"
                >mdi-brightness-1</v-icon
              >
            </template>
            {{ maintenanceStatus.find((i) => i.value === item.status).name }}
          </v-tooltip>
          {{
            maintenanceType.find((i) => i.value === item.maintenance_type).name
          }}
        </template>
        <template v-slot:item.start_date="{ item }">
          <div class="text-no-wrap text-truncate">
            <template v-if="item.maintenance_type === 'regular'">
              <template v-if="item.cycle !== null">
                {{ frequency.find((i) => i.value === item.cycle).name }}
                </template>
                <template v-if="item.cycle === 'WEEKLY'">
                  {{ byday.find((i) => i.value === item.byday).name }}
                </template>
                <template v-if="item.cycle === 'MONTHLY'">
                  {{ bydate.find((i) => i.value === item.byday).name }}
              </template>
              {{ moment(item.start_date).format('HH:mm:ss') }}
            </template>
            <template v-else>
              {{ item.start_date }}
            </template>
          </div>
        </template>
        <template v-slot:item.end_date="{ item }">
          <div class="text-no-wrap text-truncate">
            <template v-if="item.maintenance_type === 'regular'">
              <template v-if="item.cycle !== null">
                {{ frequency.find((i) => i.value === item.cycle).name }}
                </template>
                <template v-if="item.cycle === 'WEEKLY'">
                  {{ byday.find((i) => i.value === item.byday).name }}
                </template>
                <template v-if="item.cycle === 'MONTHLY'">
                  {{ bydate.find((i) => i.value === item.byday).name }}
              </template>
              {{ moment(item.end_date).format('HH:mm:ss') }}
            </template>
            <template v-else>
              {{ item.end_date }}
            </template>
          </div>
        </template>
        <template v-slot:item.status="{ item }">
          {{ maintenanceStatus.find((i) => i.value === item.status).name }}
          <v-icon
            :color="
              maintenanceStatus.find((i) => i.value === item.status).color
            "
            >mdi-brightness-1</v-icon
          >
        </template>
        <template v-slot:item.operation="{ item }">
          <template v-if="item.execution_status === 'maintain' && identity === '1'">
            <finish
              ref="finishSiteDialog"
              :item="item"
              @call-finish="maintenanceFinish"
            ></finish>
          </template>
          <template v-else-if="item.execution_status === 'finish'">
            <check
              ref="checkSiteDialog"
              :item="item"
              @call-check="saveCheck"
            ></check>
          </template>
          <template v-else-if="item.maintenance_type === 'reserve'">
            <cancel
              ref="cancelSiteDialog"
              :item="item"
              @call-cancel="cancel"
            ></cancel>
          </template>
          <template v-else-if="item.maintenance_type === 'regular'">
            <edit
              ref="editSiteDialog"
              :item="item"
              :FV="fv"
              :maintenance-type="maintenanceType"
              :frequency="frequency"
              :maintenance-status="maintenanceStatus"
              :execution-status="executionStatus"
              :byday="byday"
              :bydate="bydate"
              @call-edit="saveEdit"
            ></edit>
          </template>
        </template>
        <v-alert v-slot:no-results :value="true" color="error" icon="warning">
          Your search for "{{ search }}" found no results.
        </v-alert>
      </v-data-table>
      <pagination
        :total-items="totalItems"
        :pagination="pagination"
        :sort="sort"
        @fetch="init()"
      />
    </v-flex>
  </v-layout>
</template>


<script crossorigin="anonymous">
module.exports = {
  components: {
    'edit': httpVueLoader('./edit_maintenance.vue'),
    'add': httpVueLoader('./add_maintenance.vue'),
    'check': httpVueLoader('./check_maintenance.vue'),
    'finish': httpVueLoader('./finish_maintenance.vue'),
    'cancel': httpVueLoader('./cancel_maintenance.vue'),
    'pagination': httpVueLoader('../pagination.vue')
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
    domain: {
      type: String,
      default: ''
    },
    apiurl: {
      type: String,
      default: ''
    },
    identity: {
      type: String,
      default: ''
    }
  },
  data: function() {
    return {
      editData: {},
      checkData: {},
      finishData: {},
      cancelData: {},
      maintenance: [],
      maintenanceConfiguration: {},
      singleExpand: false,
      totalItems: 0,
      moment,
      filter: {
        search: ''
      },
      sort: {},
      pagination: { rowsPerPage: 10 },
      headers: [
        { text: '維護種類', value: 'maintenance_type', sortable: false },
        { text: '開始時間', value: 'start_date', sortable: false },
        { text: '預計結束時間', value: 'end_date', sortable: false },
        {
          text: '備註',
          value: 'note',
          sortable: false,
          width: 300,
          class: 'text-no-wrap'
        },
        { text: '維護狀態', value: 'execution_status', sortable: false },
        { text: '操作', value: 'operation', sortable: false }
      ],
      dl_status: null,
      maintenanceType: [
        { name: '固定', value: 'regular' },
        { name: '緊急', value: 'emergency' },
        { name: '預約', value: 'reserve' }
      ],
      frequency: [
        { name: '每周', value: 'WEEKLY' },
        { name: '每月', value: 'MONTHLY' }
      ],
      maintenanceStatus: [
        { name: '啟用', color: 'success', value: 'start' },
        { name: '暫停', color: 'warning', value: 'pause' },
        { name: '取消', color: 'danger', value: 'cancel' },
        { name: '已結束', color: 'warning', value: 'finish' }
      ],
      executionStatus: [
        { name: '維護中', color: 'success', value: 'maintain' },
        { name: '已結束', color: 'warning', value: 'finish' },
        { name: '已開站', color: 'danger', value: 'open' },
        { name: '', color: 'danger', value: 'no_status' }
      ],
      byday: [
        { name: '星期天', value: 'SUN' },
        { name: '星期一', value: 'MON' },
        { name: '星期二', value: 'TUE' },
        { name: '星期三', value: 'WED' },
        { name: '星期四', value: 'THU' },
        { name: '星期五', value: 'FRI' },
        { name: '星期六', value: 'SAT' }
      ],
      bydate: [],
      rec: '收到的数据'
    };
  },
  created() {
    for (let i = 0; i < 31; i++) {
      this.bydate.push({
        name: i + 1 + '號',
        value: String(i + 1)
      })
    }
  },
  mounted() {
    this.init()
  },
  methods: {
    async init() {
      const self = this;
      await axios.get(self.apiurl+'/management/maintenance/setting', {
        params: {
          search: self.filter.search,
          page: this.pagination.page
        }
      })
        .then(function(response) {
          self.maintenance = response.data.details.data
          self.totalItems = response.data.details.total
        })
        .catch(function(error) {
          if (error) {
            self.maintenance = []
            self.totalItems = 0
          }
        });
    },
    async saveEdit(data, edit_id) {
      const self = this
      await axios.put(self.apiurl+'/management/maintenance/setting/configuration/'+edit_id, data)
        .then(function(response) {
          self.init()
        })
        .catch(function(error) {
          if (error) {
             alert('編輯失敗，請檢查資料是否正確')
          }
        })
    },
    async add(data) {
      const self = this
      await axios.post(self.apiurl+'/management/maintenance/setting/addition', data)
        .then(function(response) {
          self.init()
          self.$refs.addSiteDialog.closeAddDialog()
          console.log('true');
        })
        .catch(function(error) {
          if (error) {
            if (error.response.status === 500) {
              alert('新增失敗，請檢查站台代碼與站台名稱是否有重複')
            }
            if (error.response.status === 400) {
              alert('新增失敗，請檢查資料是否正確')
            }
          }
        })
    },
    async saveCheck(data, check_id) {
      const self = this
      await axios.post(self.apiurl+'/management/maintenance/setting/cs-confirmation/' + check_id)
        .then(function(response) {
          self.$refs.addSiteDialog.closeAddDialog()
          alert('以確認開站')
        })
        .catch(function(error) {
          if (error) {
            // self.$store.dispatch('alert/danger', error.response.data.message)
          }
        })
    },
    async cancel(data, edit_id) {
      const self = this
      await axios.put(self.apiurl+'/management/maintenance/setting/configuration/'+edit_id, data)
        .then(function(response) {
          self.init()
        })
        .catch(function(error) {
          if (error) {
             alert('編輯失敗，請檢查資料是否正確')
          }
        })
    },
    async maintenanceFinish(data, finish_id) {
      const self = this
      await axios.put(self.apiurl+'/management/maintenance/it-notify/change-execution-status/'+finish_id, data)
        .then(function(response) {
          self.init()
        })
        .catch(function(error) {
          if (error) {
             alert('編輯失敗，請檢查資料是否正確')
          }
        })
    },
    setFilter() {
      if (this.pagination.page !== 1) this.pagination.page = 1
      else {
        this.init()
      }
    },
    connect() {
      const vm = this
      vm.client = Stomp.client(process.env.mqUrl)
      const headers = process.env.mqheader

      // vm.client.debug = null
      vm.client.connect(
        headers.login,
        headers.passcode,
        (frame) => {
          // console.info('连接成功!');
          this.connected = true

          vm.client.subscribe('/exchange/front_notify', function(data) {
            const datas = JSON.parse(data.body)

            if (datas.to === 'maintenance_setting') {
              vm.statusUpdate()
            } else if (datas.to === 'maintenance_history') {
              vm.addHistorry()
            }
          })
        },
        (error) => {
          // console.info('连接失败!')
          console.log(error)
          this.connected = false
        },
        headers.vhost
      )
    }
  }
};
</script>
