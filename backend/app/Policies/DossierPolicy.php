<?php

namespace App\Policies;

class DossierPolicy extends HierarchiePolicy
{
    protected string $permissionGestion = 'dossiers.gerer';
}
