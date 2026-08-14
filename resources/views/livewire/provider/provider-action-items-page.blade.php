<x-portal.action-items-layout
    :items="$items"
    :grouped-tasks="$items['grouped_tasks']"
    cases-show-route="provider.cases.show"
    :documents-route="route('provider.documents')"
    :show-provider="false"
    header-component="provider"
/>
