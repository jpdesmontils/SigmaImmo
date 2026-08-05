<?php
class PlanService
{
    const FREE_FAVORITES_LIMIT = 10;

    public function normalizePlan($plan)
    {
        return in_array($plan, array('free', 'paid', 'admin'), true) ? $plan : 'free';
    }

    public function canAddFavorite($user, $activeFavoritesCount)
    {
        $plan = $this->normalizePlan(isset($user['plan']) ? $user['plan'] : 'free');
        return $plan !== 'free' || $activeFavoritesCount < self::FREE_FAVORITES_LIMIT;
    }

    public function defaultVisibility($user)
    {
        $plan = $this->normalizePlan(isset($user['plan']) ? $user['plan'] : 'free');
        return $plan === 'free' ? 'shared' : 'private';
    }

    public function isAdmin($user)
    {
        return isset($user['plan']) && $user['plan'] === 'admin';
    }
}
