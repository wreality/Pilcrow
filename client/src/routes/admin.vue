<template>
  <route-gate :loading="loading" :allowed="canAccessAdmin">
    <router-view />
  </route-gate>
</template>

<script setup lang="ts">
import RouteGate from "src/components/RouteGate.vue"
import { useCurrentUser } from "src/use/user"

// Layout wrapper for everything under /admin. Self-gates the whole subtree on
// the server-resolved admin_area ability (denial renders a 403 in place) and
// holds the root "Administration" breadcrumb so children only have to declare
// their own leaf crumb.
definePage({
  meta: {
    crumb: {
      label: "breadcrumbs.admin.administration",
      to: { name: "admin:dashboard" }
    }
  }
})

const { currentUserQuery, canAccessAdmin } = useCurrentUser()
const loading = currentUserQuery.loading
</script>
