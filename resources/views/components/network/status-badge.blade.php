@props(['status','statuses'=>config('zigo_network_map.statuses')])
<span class="badge status-{{ $status }}">{{ $statuses[$status]['label'] ?? strtoupper($status) }}</span>
