@props(['outcome'])
<x-badge :variant="match ($outcome) { 'success' => 'success', 'failed' => 'danger', default => 'neutral' }">{{ ucfirst($outcome) }}</x-badge>
