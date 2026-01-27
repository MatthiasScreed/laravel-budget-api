<?php

namespace App\Events;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public Category $category
    ) {}
}
