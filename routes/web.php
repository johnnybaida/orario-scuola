<?php

use App\Http\Controllers\AulaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CattedraController;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisciplinaController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GenerazioneController;
use App\Http\Controllers\OrarioController;
use App\Http\Controllers\QuadroOrarioController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\SostegnoController;
use App\Http\Controllers\UtenzaController;
use App\Http\Controllers\VincoloController;
use App\Http\Controllers\WorkerController;
use App\Support\Ruoli;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// I nomi dei parametri sono espliciti perché l'inflector inglese di Laravel
// non singolarizza correttamente i nomi di dominio in italiano (es. "cattedre").
$parametriRisorse = [
    'sedi' => ['sedi' => 'sede'],
    'aule' => ['aule' => 'aula'],
    'discipline' => ['discipline' => 'disciplina'],
    'quadri-orari' => ['quadri-orari' => 'quadroOrario'],
    'docenti' => ['docenti' => 'docente'],
    'classi' => ['classi' => 'classe'],
    'cattedre' => ['cattedre' => 'cattedra'],
    'vincoli' => ['vincoli' => 'vincolo'],
    'generazioni' => ['generazioni' => 'generazione'],
];

Route::middleware('auth')->group(function () use ($parametriRisorse) {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Creazione generazioni: registrata prima di generazioni.show, altrimenti
    // "GET /generazioni/create" verrebbe intercettata dalla rotta con {generazione}.
    Route::middleware('ruolo:'.implode(',', Ruoli::GESTIONE_ANAGRAFICA))->group(function () use ($parametriRisorse) {
        Route::resource('generazioni', GenerazioneController::class)->parameters($parametriRisorse['generazioni'])->only(['create', 'store']);
    });

    // Consultazione: tutti i ruoli operativi possono vedere le anagrafiche.
    Route::middleware('ruolo:'.implode(',', Ruoli::CONSULTAZIONE))->group(function () use ($parametriRisorse) {
        Route::resource('sedi', SedeController::class)->parameters($parametriRisorse['sedi'])->except(['store', 'update', 'destroy']);
        Route::resource('aule', AulaController::class)->parameters($parametriRisorse['aule'])->except(['store', 'update', 'destroy']);
        Route::resource('discipline', DisciplinaController::class)->parameters($parametriRisorse['discipline'])->except(['store', 'update', 'destroy']);
        Route::resource('quadri-orari', QuadroOrarioController::class)->parameters($parametriRisorse['quadri-orari'])->except(['store', 'update', 'destroy']);
        Route::resource('docenti', DocenteController::class)->parameters($parametriRisorse['docenti'])->except(['store', 'update', 'destroy']);
        Route::resource('classi', ClasseController::class)->parameters($parametriRisorse['classi'])->except(['store', 'update', 'destroy']);
        Route::resource('cattedre', CattedraController::class)->parameters($parametriRisorse['cattedre'])->except(['store', 'update', 'destroy']);
        Route::resource('vincoli', VincoloController::class)->parameters($parametriRisorse['vincoli'])->except(['store', 'update', 'destroy']);
        Route::resource('generazioni', GenerazioneController::class)->parameters($parametriRisorse['generazioni'])->only(['index', 'show']);
        Route::get('/generazioni/{generazione}/stato', [GenerazioneController::class, 'stato'])->name('generazioni.stato');

        Route::get('/orari', [OrarioController::class, 'index'])->name('orari.index');
        Route::get('/orari/{orario}/classe/{classe}', [OrarioController::class, 'classe'])->name('orari.classe');
        Route::get('/orari/{orario}/docente/{docente}', [OrarioController::class, 'docente'])->name('orari.docente');
        Route::get('/orari/{orario}/export/classe/{classe}', [ExportController::class, 'classe'])->name('orari.export.classe');
        Route::get('/orari/{orario}/export/docente/{docente}', [ExportController::class, 'docente'])->name('orari.export.docente');
        Route::get('/orari/{orario}/export/generale', [ExportController::class, 'generale'])->name('orari.export.generale');
    });

    // Gestione anagrafica generale (sedi, aule, discipline, quadri orari, cattedre).
    Route::middleware('ruolo:'.implode(',', Ruoli::GESTIONE_ANAGRAFICA))->group(function () use ($parametriRisorse) {
        Route::resource('sedi', SedeController::class)->parameters($parametriRisorse['sedi'])->only(['store', 'update', 'destroy']);
        Route::resource('aule', AulaController::class)->parameters($parametriRisorse['aule'])->only(['store', 'update', 'destroy']);
        Route::resource('discipline', DisciplinaController::class)->parameters($parametriRisorse['discipline'])->only(['store', 'update', 'destroy']);
        Route::resource('quadri-orari', QuadroOrarioController::class)->parameters($parametriRisorse['quadri-orari'])->only(['store', 'update', 'destroy']);
        Route::post('/quadri-orari/{quadroOrario}/righe', [QuadroOrarioController::class, 'storeRiga'])->name('quadri-orari.righe.store');
        Route::delete('/quadri-orari/righe/{riga}', [QuadroOrarioController::class, 'destroyRiga'])->name('quadri-orari.righe.destroy');
        Route::resource('cattedre', CattedraController::class)->parameters($parametriRisorse['cattedre'])->only(['store', 'update', 'destroy']);
        Route::resource('vincoli', VincoloController::class)->parameters($parametriRisorse['vincoli'])->only(['store', 'update', 'destroy']);

        Route::post('/worker/avvia', [WorkerController::class, 'avvia'])->name('worker.avvia');
        Route::post('/worker/ferma', [WorkerController::class, 'ferma'])->name('worker.ferma');

        Route::patch('/orari/{orario}/lezioni/{lezione}/sposta', [OrarioController::class, 'spostaLezione'])->name('orari.lezioni.sposta');
        Route::patch('/orari/{orario}/lezioni/{lezione}/cattedra', [OrarioController::class, 'cambiaCattedraLezione'])->name('orari.lezioni.cattedra');
        Route::post('/orari/{orario}/lezioni/{lezione}/blocca', [OrarioController::class, 'bloccaLezione'])->name('orari.lezioni.blocca');
        Route::post('/orari/{orario}/annulla-ultima', [OrarioController::class, 'annullaUltima'])->name('orari.annulla-ultima');
        Route::post('/orari/{orario}/avvisi/azzera', [OrarioController::class, 'azzeraAvvisi'])->name('orari.avvisi.azzera');
    });

    // Utenze con accesso al sistema: solo l'amministratore.
    Route::middleware('ruolo:'.Ruoli::AMMINISTRATORE)->group(function () {
        Route::resource('utenze', UtenzaController::class)->parameters(['utenze' => 'utenza'])->except(['show']);
    });

    // Gestione docenti e classi: anche la segreteria.
    Route::middleware('ruolo:'.implode(',', Ruoli::GESTIONE_DOCENTI_CLASSI))->group(function () use ($parametriRisorse) {
        Route::resource('docenti', DocenteController::class)->parameters($parametriRisorse['docenti'])->only(['store', 'update', 'destroy']);
        Route::get('/docenti-import', [DocenteController::class, 'importForm'])->name('docenti.import.form');
        Route::post('/docenti-import', [DocenteController::class, 'import'])->name('docenti.import');
        Route::put('/docenti/{docente}/indisponibilita', [DocenteController::class, 'updateIndisponibilita'])->name('docenti.indisponibilita.update');

        Route::resource('classi', ClasseController::class)->parameters($parametriRisorse['classi'])->only(['store', 'update', 'destroy']);
        Route::get('/classi-import', [ClasseController::class, 'importForm'])->name('classi.import.form');
        Route::post('/classi-import', [ClasseController::class, 'import'])->name('classi.import');
        Route::put('/classi/{classe}/slot-attivi', [ClasseController::class, 'updateSlotAttivi'])->name('classi.slot-attivi.update');

        Route::post('/classi/{classe}/sostegno/fabbisogni', [SostegnoController::class, 'storeFabbisogno'])->name('sostegno.fabbisogni.store');
        Route::delete('/classi/{classe}/sostegno/fabbisogni/{fabbisogno}', [SostegnoController::class, 'destroyFabbisogno'])->name('sostegno.fabbisogni.destroy');
        Route::post('/classi/{classe}/sostegno/assegnazioni', [SostegnoController::class, 'storeAssegnazione'])->name('sostegno.assegnazioni.store');
        Route::delete('/classi/{classe}/sostegno/assegnazioni/{assegnazione}', [SostegnoController::class, 'destroyAssegnazione'])->name('sostegno.assegnazioni.destroy');
        Route::put('/classi/{classe}/sostegno/conteggio', [SostegnoController::class, 'updateConteggio'])->name('sostegno.conteggio.update');
    });
});
