<template>
    <PlaceholderInputLabelContainer>
        <input :id="this.id"
               :value="this.modelValue"
               @input="this.$emit('update:modelValue', $event.target.value)"
               type="number"
               class="input peer"
               :class="[isSmall ? '' : 'h-12', classes]"
               placeholder="placeholder"
               :min="min"
                :max="max"
                :maxlength="maxlength"
               :step="step"
        :required="required"/>
        <PlaceholderLabel :for="this.id" :label="this.label" :is-small="isSmall" :classes="classes"/>
    </PlaceholderInputLabelContainer>
</template>

<script>
import {defineComponent} from "vue";
import PlaceholderInputLabelContainer from "@/Components/Inputs/Container/PlaceholderInputLabelContainer.vue";
import PlaceholderLabel from "@/Components/Inputs/Labels/PlaceholderLabel.vue";

export default defineComponent({
    components: {PlaceholderLabel, PlaceholderInputLabelContainer},
    props: {
        id: {
            type: String,
            required: true
        },
        label: {
            type: String,
            required: true
        },
        modelValue: {
            type: [String, null, Number],
            required: true
        },
        isSmall: {
            type: Boolean,
            default: false
        },
        required: {
            type: Boolean,
            default: false
        },
        min: {
            type: Number,
            default: 0
        },
        max: {
            type: Number,
            default: 200
        },
        maxlength: {
            type: Number,
            default: 200
        },
        step: {
            type: Number,
            default: 1
        },
        classes: {
            type: String,
            default: ''
        }
    },
    emits: [
        'update:modelValue'
    ]
});
</script>
