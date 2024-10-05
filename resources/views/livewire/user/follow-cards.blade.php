@props([
    'profileUser',
    'authFollowIds' => collect(),
])

<div class="w-full max-w-sm">
    <div class="flex w-full max-w-sm">
        <livewire:user.follow-card relationship="followers" :profile-user="$profileUser" :auth-follow-ids="$authFollowIds" />
    </div>
    <div class="flex w-full max-w-sm">
        <livewire:user.follow-card relationship="following" :profile-user="$profileUser" :auth-follow-ids="$authFollowIds" />
    </div>
</div>
