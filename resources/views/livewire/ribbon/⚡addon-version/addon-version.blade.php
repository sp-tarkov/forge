<div>
    @if ($this->ribbonData)
        <x-ribbon
            :color="$this->ribbonData['color']"
            :label="$this->ribbonData['label']"
        />
    @endif
</div>