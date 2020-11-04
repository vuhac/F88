<template>
  <v-layout row justify-center align-center>
    <v-flex class="px-3" xs12>
      <v-subheader>
        歷史維護紀錄(維護數量：{{ totalItems }})
        <v-spacer></v-spacer>
      </v-subheader>
      <v-data-table
        :headers="headers"
        :items="maintenanceHistory"
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
          <div class="text-no-wrap text-truncate" style="width: 300px;">
            {{ item.note }}
          </div>
        </template>
        <template v-slot:item.maintenance_type="{ item }">
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
        <template v-slot:item.last_end_date="{ item }">
          <template v-if="item.status === 'cancel'">
            取消
          </template>
          <template v-else>
            {{ item.last_end_date }}
          </template>
        </template>
        <template v-slot:item.status="{ item }">
          {{ maintenanceStatus.find((i) => i.value === item.status).name }}
          <v-icon
            :color="
              maintenanceStatus.find((i) => i.value === item.status).color
            "
            >mdi-brightness-1</v--icon
          >
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
    'pagination': httpVueLoader('../pagination.vue')
  },
  props: {
    fv: {
      type: Object,
      default() {
        return {}
      }
    },
    apiurl: {
      type: String,
      default: ''
    }
  },
  data: function() {
    return {
      maintenanceHistory: [],
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
        { text: '上次結束時間', value: 'last_end_date', sortable: false },
        {
          text: '備註',
          value: 'note',
          sortable: false,
          width: 300,
          class: 'text-no-wrap'
        }
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
        { name: '已開站', color: 'danger', value: 'open' }
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
    // this.connect()
  },
  mounted() {
    this.init()
  },
  methods: {
    async init() {
      const self = this;
      await axios.get(self.apiurl+'/management/maintenance/history', {
        params: {
          search: self.filter.search,
          page: this.pagination.page
        }
      })
        .then(function(response) {
          self.maintenanceHistory = response.data.details.data
          self.totalItems = response.data.details.total
        })
        .catch(function(error) {
          if (error) {
            self.maintenanceHistory = []
            self.totalItems = 0
          }
        });
    },
    setFilter() {
      if (this.pagination.page !== 1) this.pagination.page = 1
      else {
        this.init()
      }
    }
  }
};
</script>
