@props(['profileUserId'])

<div class="flex w-full max-w-sm">
    <livewire:user.follow-card relationship="followers" :profile-user-id="$profileUserId" />
</div>
<div class="flex w-full max-w-sm">
    <livewire:user.follow-card relationship="following" :profile-user-id="$profileUserId" />
</div>
