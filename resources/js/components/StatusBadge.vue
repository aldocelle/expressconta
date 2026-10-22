<script setup lang="ts">import {statusLabels} from '../api';defineProps<{status:string}>();</script>
<template><span class="badge" :class="status">{{statusLabels[status]||status}}</span></template>
