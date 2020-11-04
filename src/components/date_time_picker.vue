<template>
  <v-menu
    v-model="menu"
    :close-on-content-click="false"
    transition="v-scale-transition"
    :max-width="290"
  >
    <template v-slot:activator="{ on }">
      <v-text-field
        :value="actualDatetime"
        :label="label"
        :error-messages="errorMessages"
        :rules="rules"
        :disabled="disabled"
        readonly
        v-on="on"
        @input="actualDatetime"
      ></v-text-field>
    </template>
    <v-card>
      <v-tabs v-model="selectedTab">
        <v-tab href="#calendar">
          <v-icon>mdi-calendar-range</v-icon>
        </v-tab>
        <v-tab href="#timer">
          <v-icon>mdi-clock-outline</v-icon>
        </v-tab>
      </v-tabs>
      <v-tabs-items v-model="selectedTab">
        <v-tab-item value="calendar">
          <v-date-picker
            v-model="dateModel"
            no-title
            scrollable
            actions
            color="primary"
            @input="checkDate"
          ></v-date-picker>
        </v-tab-item>
        <v-tab-item value="timer">
          <v-time-picker
            ref="timer"
            v-model="timeModel"
            scrollable
            format="24hr"
            actions
            color="primary"
            @input="checkMinutes"
          ></v-time-picker>
        </v-tab-item>
      </v-tabs-items>
    </v-card>
  </v-menu>
</template>
<script>

module.exports = {
  props: {
    datetime: {
      type: String,
      default: '',
      required: false
    },
    label: {
      type: String,
      default: ''
    },
    rules: {
      type: Array,
      default() {
        return []
      }
    },
    disabled: {
      type: Boolean,
      default: false
    },
    errorMessages: {
      type: String,
      default: '',
      required: false
    }
  },
  data() {
    return {
      moment,
      dateTime: this.datetime,
      dateModel: '',
      timeModel: '',
      menu: false,
      selectedTab: 'calendar'
    }
  },
  computed: {
    actualDatetime() {
      if (!this.timeModel || !this.dateModel) return ''
      else return this.dateModel + ' ' + this.timeModel
    }
  },

  watch: {
    menu(val) {
      if (val === false) {
        this.selectedTab = 'calendar'
        if (this.$refs.timer) this.$refs.timer.selectingHour = true
      }
    },
    datetime(val) {
      if (val !== '') {
        this.dateModel = moment(val).format('YYYY-MM-DD')
        this.timeModel = moment(val).format('HH:mm')
        this.$emit('input', this.actualDatetime)
      } else {
        this.dateModel = ''
        this.timeModel = ''
      }
    }
  },
  created() {
    if (this.dateTime !== '') {
      this.dateModel = moment(this.dateTime).format('YYYY-MM-DD')
      this.timeModel = moment(this.dateTime).format('HH:mm')
    } else if (this.dateTime === '') {
      this.dateModel = ''
      this.timeModel = ''
    }
  },
  methods: {
    checkMinutes(val) {
      // if (this.$refs.timer.selectingHour === false) {
      // }
      this.timeModel = val
      this.$emit('input', this.actualDatetime)
    },
    checkDate(val) {
      this.dateModel = val
      this.$emit('input', this.actualDatetime)
      this.selectedTab = 'timer'
    }
  }
}
</script>
