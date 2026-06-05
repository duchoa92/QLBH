<template>
  <div class="bg-slate-100 min-h-screen lg:h-screen lg:overflow-hidden">

    <div class="flex flex-col lg:flex-row h-full">

      <div class="w-full lg:w-[70%] p-3 flex flex-col">
        <slot name="main" />
      </div>

      <div class="w-full lg:w-[30%] p-3 flex flex-col bg-white border-t lg:border-l">
        <slot name="sidebar" />
      </div>

    </div>

  </div>
</template>
