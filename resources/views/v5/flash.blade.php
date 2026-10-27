@if (! empty($flash['error']))
    <div role="alert" class="card mb-6 text-sm text-red-500" v-pre>{{ $flash['error'] }}</div>
@elseif (! empty($flash['success']))
    <div role="status" class="card mb-6 text-sm text-green-600" v-pre>{{ $flash['success'] }}</div>
@endif
