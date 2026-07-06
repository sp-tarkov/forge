@props(['profileUser', 'authFollowIds' => collect()])

<div class="grid w-full grid-cols-2 gap-6">
    <div class="col-span-full flex w-full md:col-span-1 lg:col-span-2">
        <livewire:user.follow-card
            relationship="followers"
            :profile-user="$profileUser"
            :auth-follow-ids="$authFollowIds"
        />
    </div>
    <div class="col-span-full flex w-full md:col-span-1 lg:col-span-2">
        <livewire:user.follow-card
            relationship="following"
            :profile-user="$profileUser"
            :auth-follow-ids="$authFollowIds"
        />
    </div>
</div>
