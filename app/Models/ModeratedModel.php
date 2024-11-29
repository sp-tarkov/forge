<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class ModeratedModel extends Model
{
    abstract public function getFriendlyName(): string;

    public function toggleDisabled(): void
    {
        $this->disabled = ! $this->disabled;
        $this->save();
    }
}
