@extends('hr::portal.layouts.app')

@section('title', 'Notices - Employee Portal')
@section('page-title', 'Notices')

@section('content')
<div class="card">
    <div class="card-title"><i class="fa-solid fa-bullhorn" style="color:var(--primary); margin-right:6px;"></i>Notice Board</div>

    @forelse($notices as $notice)
        <div class="notice-item">
            <div class="notice-item-header">
                <div class="notice-item-title">{{ $notice->title }}</div>
                <div class="notice-item-date">{{ optional($notice->published_at)->format('d M Y') }}</div>
            </div>
            @if($notice->description)
                <div class="notice-item-body">{{ $notice->description }}</div>
            @endif
        </div>
    @empty
        <div class="empty-state">
            <div class="empty-icon"><i class="fa-regular fa-bell-slash"></i></div>
            <div class="empty-text">No notices published yet.</div>
        </div>
    @endforelse
</div>
@endsection
