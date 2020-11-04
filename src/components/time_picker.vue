<template>
  <v-menu
    v-model="menu"
    :close-on-content-click="false"
    :max-width="290"
    transition="v-scale-transition"
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
        <v-tab href="#timer">
          <v-icon>mdi-clock-outline</v-icon>
        </v-tab>
      </v-tabs>
      <v-tabs-items v-model="selectedTab">
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
    date: {
      type: String,
      default: '',
      required: false
    },
    time: {
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
      timeModel: this.time,
      menu: false,
      selectedTab: 'calendar'
    }
  },
  computed: {
    actualDatetime() {
      if (!this.timeModel) return ''
      else return this.timeModel
    }
  },

  watch: {
    menu(val) {
      if (val === false) {
        this.selectedTab = 'timer'
        if (this.$refs.timer) this.$refs.timer.selectingHour = true
      }
    }
  },
  created() {
    if (this.dateTime !== '') {
      this.timeModel = this.dateTime
    } else if (this.dateTime === '') {
      this.timeModel = ''
    }
  },
  methods: {
    checkMinutes(val) {
      // if (this.$refs.timer.selectingHour === false) {
      // }
      this.timeModel = val
      this.$emit('input', this.actualDatetime)
    }
  }
}
</script>
