@props(['icon' => 'fa-inbox', 'title', 'message' => null])
<div {{ $attributes->class(['crm-empty']) }}><i class="fa-solid {{ $icon }}"></i><strong>{{ $title }}</strong>@if($message)<p>{{ $message }}</p>@endif</div>
