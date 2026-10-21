<?php

namespace App\Policies;

use App\Models\Utilisateur;

class JournalPolicy
{
    public function viewAny(Utilisateur $user): bool
    {
        return $user->checkPermissionTo('journal.voir');
    }
}
