<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

class StoryPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * The listing endpoints only ever query through the authenticated user's
     * own relations, so there is nothing left to scope at this point.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Story $story): bool
    {
        return $this->owns($user, $story);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Story $story): bool
    {
        return $this->owns($user, $story);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Story $story): bool
    {
        return $this->owns($user, $story);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Story $story): bool
    {
        return $this->owns($user, $story);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Story $story): bool
    {
        return $this->owns($user, $story);
    }

    /**
     * A story belongs to exactly one user.
     *
     * Admins deliberately get no bypass here. What an admin may see of a user's
     * content is still an open decision (Fizzy #96), and until it is settled the
     * default has to be the restrictive one.
     */
    private function owns(User $user, Story $story): bool
    {
        return $user->id === $story->user_id;
    }
}
