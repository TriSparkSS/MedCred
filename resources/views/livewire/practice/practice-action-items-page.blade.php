<x-portal.action-items-layout
    :items="$items"
    :grouped-tasks="$items['grouped_tasks']"
    cases-show-route="practice.cases.show"
    :documents-route="route('practice.documents')"
    :show-provider="true"
    header-component="practice"
/>
