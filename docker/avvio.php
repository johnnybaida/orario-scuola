<?php

// Inizializzazione al boot del container, dopo migrazioni e seed: password dell'amministratore (ADMIN_PASSWORD, facoltativa)
// e avvio del worker di coda, così le generazioni partono subito. Non usa tinker (è una dipendenza di sviluppo).

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if ($password = getenv('ADMIN_PASSWORD')) {
    App\Models\User::query()->where('email', 'amministratore@scuola.test')->first()?->update(['password' => $password]);
}

try {
    $app->make(App\Services\QueueWorker::class)->avvia();
} catch (Throwable $e) {
    fwrite(STDERR, "Worker di coda non avviato: {$e->getMessage()}\n");
}
