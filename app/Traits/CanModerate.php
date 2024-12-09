<?php

namespace App\Traits;

trait CanModerate
{
    public function toggleDisabled(): void
    {
        $this->disabled = ! $this->disabled;
        $this->save();
    }
}
