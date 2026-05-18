<script setup lang="ts">
import { computed, inject, ref, watch, type ComputedRef } from 'vue';
import { getLocalizedDayJs } from '@/packages/ui/src/utils/time';
import { useFocus } from '@vueuse/core';
import { TextInput } from '@/packages/ui/src';
import type { Organization } from '@/packages/api/src';

// This has to be a localized timestamp, not UTC
const model = defineModel<string | null>({
    default: null,
});

const props = withDefaults(
    defineProps<{
        focus?: boolean;
    }>(),
    {
        focus: false,
    }
);

const organization = inject<ComputedRef<Organization>>('organization');
const timePickerTabZeroSeconds = inject<boolean | undefined>('timePickerTabZeroSeconds');

const timeDisplayFormat = computed(() =>
    organization?.value?.time_format === '12-hours' ? 'hh:mm A' : 'HH:mm'
);

function formatInputDisplay(value: string | null) {
    return value ? getLocalizedDayJs(value).format(timeDisplayFormat.value) : null;
}

function onTab() {
    if (timePickerTabZeroSeconds === false) {
        return;
    }
    if (!model.value) {
        return;
    }
    const current = getLocalizedDayJs(model.value);
    if (current.second() !== 0) {
        model.value = current.set('seconds', 0).format();
        emit('changed', model.value);
    }
    inputValue.value = formatInputDisplay(model.value);
}

function updateTime(event: Event) {
    const target = event.target as HTMLInputElement;
    const newValue = target.value.trim();
    const twelveHourMatch = newValue.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
    if (twelveHourMatch && organization?.value?.time_format === '12-hours') {
        const hoursStr = twelveHourMatch[1];
        const minutesStr = twelveHourMatch[2];
        const ampmStr = twelveHourMatch[3];
        if (!hoursStr || !minutesStr || !ampmStr) {
            inputValue.value = formatInputDisplay(model.value);
            return;
        }
        let newHours = parseInt(hoursStr);
        const newMinutes = Math.min(parseInt(minutesStr), 59);
        const ampm = ampmStr.toUpperCase();
        if (newHours === 12) {
            newHours = ampm === 'AM' ? 0 : 12;
        } else if (ampm === 'PM') {
            newHours += 12;
        }
        const currentTime = getLocalizedDayJs(model.value);
        if (currentTime.hour() !== newHours || currentTime.minute() !== newMinutes) {
            model.value = currentTime
                .set('hours', newHours)
                .set('minutes', newMinutes)
                .set('seconds', 0)
                .format();
            emit('changed', model.value);
        }
    } else if (newValue.split(':').length === 2) {
        const [hours, minutes] = newValue.split(':') as [string, string];
        if (!isNaN(parseInt(hours)) && !isNaN(parseInt(minutes))) {
            const currentTime = getLocalizedDayJs(model.value);
            const newHours = Math.min(parseInt(hours), 23);
            const newMinutes = Math.min(parseInt(minutes), 59);

            // Only update if hours or minutes are different
            if (currentTime.hour() !== newHours || currentTime.minute() !== newMinutes) {
                model.value = currentTime
                    .set('hours', newHours)
                    .set('minutes', newMinutes)
                    .set('seconds', 0)
                    .format();
                emit('changed', model.value);
            }
        }
    }
    // check if input is only numbers
    else if (/^\d+$/.test(newValue)) {
        if (newValue.length === 4) {
            // parse 1300 to 13:00
            const [hours, minutes] = [newValue.slice(0, 2), newValue.slice(2, 4)];
            model.value = getLocalizedDayJs(model.value)
                .set('hours', Math.min(parseInt(hours), 23))
                .set('minutes', Math.min(parseInt(minutes), 59))
                .set('seconds', 0)
                .format();
            emit('changed', model.value);
        } else if (newValue.length === 3) {
            // parse 130 to 01:30
            const [hours, minutes] = [newValue.slice(0, 1), newValue.slice(1, 3)];
            model.value = getLocalizedDayJs(model.value)
                .set('hours', Math.min(parseInt(hours), 23))
                .set('minutes', Math.min(parseInt(minutes), 59))
                .set('seconds', 0)
                .format();
            emit('changed', model.value);
        } else if (newValue.length === 2) {
            // parse 13 to 13:00
            model.value = getLocalizedDayJs(model.value)
                .set('hours', Math.min(parseInt(newValue), 23))
                .set('minutes', 0)
                .set('seconds', 0)
                .format();
            emit('changed', model.value);
        } else if (newValue.length === 1) {
            // parse 1 to 01:00
            model.value = getLocalizedDayJs(model.value)
                .set('hours', Math.min(parseInt(newValue), 23))
                .set('minutes', 0)
                .set('seconds', 0)
                .format();
            emit('changed', model.value);
        }
    }

    inputValue.value = formatInputDisplay(model.value);
}

watch(model, (value) => {
    inputValue.value = formatInputDisplay(value);
});

const timeInput = ref<HTMLInputElement | null>(null);
const emit = defineEmits(['changed']);

useFocus(timeInput, { initialValue: props.focus });

const inputValue = ref(formatInputDisplay(model.value));
</script>

<template>
    <TextInput
        v-bind="$attrs"
        ref="timeInput"
        v-model="inputValue"
        class="text-center w-full"
        data-testid="time_picker_input"
        type="text"
        @blur="updateTime"
        @keydown.tab="onTab"
        @keydown.enter.prevent="updateTime"
        @focus="($event.target as HTMLInputElement).select()"
        @mouseup="($event.target as HTMLInputElement).select()"
        @click="($event.target as HTMLInputElement).select()"
        @pointerup="($event.target as HTMLInputElement).select()" />
</template>

<style scoped></style>
