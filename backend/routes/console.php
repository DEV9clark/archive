<?php

use Illuminate\Support\Facades\Schedule;

// Purge mensuelle des audits au-delà de la durée de conservation
Schedule::command('audit:purge')->monthly();

// Tokens Sanctum expirés
Schedule::command('sanctum:prune-expired --hours=24')->daily();
