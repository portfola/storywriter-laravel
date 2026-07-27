<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

class StoryPolicy
{
    /**
     * Admins may see and hear every user's stories.
     *
     * A story is private to the person who made it -- there is no publishing,
     * and one user cannot reach another's. What an *admin* may see was never
     * decided anywhere in the code, so it defaulted to whatever the missing
     * ownership checks happened to allow. The decision (Fizzy #96) is the
     * permissive one, deliberately this time: admins can read any story's
     * content, for moderation and support.
     *
     * Read-only on purpose. Only the two viewing abilities are granted here, so
     * update, delete and restore fall through to the checks below and stay with
     * the owner -- an admin can read a child's storybook, not rewrite it.
     *
     * Falling through is why this returns null rather than false for everyone
     * else: a false here would be a flat deny that stopped an owner reaching
     * their own stories.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() && in_array($ability, ['viewAny', 'view'], true)) {
            return true;
        }

        return null;
    }

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
