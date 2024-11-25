@props([
    'profileUser',
    'authFollowIds' => collect(),
])

<div class="grid grid-cols-2 w-full gap-6">
    <div class="col-span-full md:col-span-1 lg:col-span-2 flex w-full">
        <livewire:user.follow-card relationship="followers" :profile-user="$profileUser" :auth-follow-ids="$authFollowIds" />
    </div>
    <div class="col-span-full md:col-span-1 lg:col-span-2 flex w-full">
        <livewire:user.follow-card relationship="following" :profile-user="$profileUser" :auth-follow-ids="$authFollowIds" />
    </div>
</div>
