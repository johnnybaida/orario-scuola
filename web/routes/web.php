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
use App\Http\Controllers\GuidaController;
use App\Http\Controllers\OrarioController;
use App\Http\Controllers\QuadroOrarioController;
use App\Http\Controllers\ScansioneOrariaController;
use App\Http\Controllers\SedeController;
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
    'laboratori' => ['laboratori' => 'laboratorio'],
    'generazioni' => ['generazioni' => 'generazione'],
];

Route::middleware(['auth', 'sede'])->group(function () use ($parametriRisorse) {
    Route::get('/elimina/conseguenze', \App\Http\Controllers\ConseguenzeEliminazioneController::class)->name('elimina.conseguenze');
    Route::post('/sede', \App\Http\Controllers\SedeCorrenteController::class)->name('sede.imposta');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/guida', [GuidaController::class, 'show'])->name('guida');

    // Creazione generazioni: registrata prima di generazioni.show, altrimenti
    // "GET /generazioni/create" verrebbe intercettata dalla rotta con {generazione}.
    Route::middleware('ruolo:'.implode(',', Ruoli::GESTIONE_ANAGRAFICA))->group(function () use ($parametriRisorse) {
        Route::resource('generazioni', GenerazioneController::class)->parameters($parametriRisorse['generazioni'])->only(['create', 'store']);
    });

    // Consultazione: tutti i ruoli operativi possono vedere le anagrafiche.
    Route::middleware('ruolo:'.implode(',', Ruoli::CONSULTAZIONE))->group(function () use ($parametriRisorse) {
        Route::resource('sedi', SedeController::class)->parameters($parametriRisorse['sedi'])->except(['store', 'update', 'destroy']);
        Route::resource('aule', AulaController::class)->parameters($parametriRisorse['aule'])->except(['store', 'update', 'destroy']);
        Route::get('/mensa', [\App\Http\Controllers\MensaController::class, 'index'])->name('mensa.index');
        Route::resource('discipline', DisciplinaController::class)->parameters($parametriRisorse['discipline'])->except(['store', 'update', 'destroy']);
        Route::resource('quadri-orari', QuadroOrarioController::class)->parameters($parametriRisorse['quadri-orari'])->except(['store', 'update', 'destroy']);
        Route::resource('docenti', DocenteController::class)->parameters($parametriRisorse['docenti'])->except(['store', 'update', 'destroy']);
        Route::resource('classi', ClasseController::class)->parameters($parametriRisorse['classi'])->except(['store', 'update', 'destroy']);
        Route::resource('cattedre', CattedraController::class)->parameters($parametriRisorse['cattedre'])->except(['store', 'update', 'destroy']);
        Route::get('/impostazioni', [\App\Http\Controllers\ImpostazioniController::class, 'index'])->name('impostazioni.index');
        Route::resource('vincoli', VincoloController::class)->parameters($parametriRisorse['vincoli'])->except(['store', 'update', 'destroy']);
        Route::resource('laboratori', \App\Http\Controllers\LaboratorioController::class)->parameters($parametriRisorse['laboratori'])->except(['store', 'update', 'destroy', 'show']);
        Route::get('/laboratori-disponibilita', [\App\Http\Controllers\LaboratorioController::class, 'disponibilita'])->name('laboratori.disponibilita');
        Route::resource('generazioni', GenerazioneController::class)->parameters($parametriRisorse['generazioni'])->only(['index', 'show']);
        Route::get('/prompt-ai', [\App\Http\Controllers\PromptController::class, 'index'])->name('prompt.index');
        Route::get('/generazioni/{generazione}/stato', [GenerazioneController::class, 'stato'])->name('generazioni.stato');
        Route::get('/generazioni/{generazione}/diagnostica', [GenerazioneController::class, 'diagnostica'])->middleware('can:gestisci-anagrafica')->name('generazioni.diagnostica');

        Route::get('/scansione-oraria', [ScansioneOrariaController::class, 'index'])->name('scansione.index');

        Route::get('/orari', [OrarioController::class, 'index'])->name('orari.index');
        Route::post('/orari/{orario}/stato', [OrarioController::class, 'cambiaStato'])->name('orari.stato');
        Route::get('/orari/{orario}/tabellone', [OrarioController::class, 'tabellone'])->name('orari.tabellone');
        Route::get('/orari/{orario}/controllo', [OrarioController::class, 'controllo'])->name('orari.controllo');
        Route::get('/orari/{orario}/classe/{classe}', [OrarioController::class, 'classe'])->name('orari.classe');
        Route::get('/orari/{orario}/docente/{docente}', [OrarioController::class, 'docente'])->name('orari.docente');
        Route::get('/orari/{orario}/aula/{aula}', [OrarioController::class, 'aula'])->name('orari.aula');
        Route::get('/orari/{orario}/export/classe/{classe}', [ExportController::class, 'classe'])->name('orari.export.classe');
        Route::get('/orari/{orario}/export/classi', [ExportController::class, 'classi'])->name('orari.export.classi');
        Route::get('/orari/{orario}/export/docente/{docente}', [ExportController::class, 'docente'])->name('orari.export.docente');
        Route::get('/orari/{orario}/export/aula/{aula}', [ExportController::class, 'aula'])->name('orari.export.aula');
        Route::get('/orari/{orario}/export/aule', [ExportController::class, 'aule'])->name('orari.export.aule');
        Route::get('/orari/{orario}/export/docenti', [ExportController::class, 'docenti'])->name('orari.export.docenti');
        Route::get('/orari/{orario}/export/generale', [ExportController::class, 'generale'])->name('orari.export.generale');
    });

    // Gestione anagrafica generale (sedi, aule, discipline, quadri orari, cattedre).
    Route::middleware('ruolo:'.implode(',', Ruoli::GESTIONE_ANAGRAFICA))->group(function () use ($parametriRisorse) {
        Route::resource('sedi', SedeController::class)->parameters($parametriRisorse['sedi'])->only(['store', 'update', 'destroy']);
        Route::resource('aule', AulaController::class)->parameters($parametriRisorse['aule'])->only(['store', 'update', 'destroy']);
        Route::resource('discipline', DisciplinaController::class)->parameters($parametriRisorse['discipline'])->only(['store', 'update', 'destroy']);
        Route::resource('quadri-orari', QuadroOrarioController::class)->parameters($parametriRisorse['quadri-orari'])->only(['store', 'update', 'destroy']);
        Route::resource('cattedre', CattedraController::class)->parameters($parametriRisorse['cattedre'])->only(['store', 'update', 'destroy']);
        Route::resource('vincoli', VincoloController::class)->parameters($parametriRisorse['vincoli'])->only(['store', 'update', 'destroy']);
        Route::resource('laboratori', \App\Http\Controllers\LaboratorioController::class)->parameters($parametriRisorse['laboratori'])->only(['store', 'update', 'destroy']);
        Route::put('/mensa', [\App\Http\Controllers\MensaController::class, 'update'])->name('mensa.update');

        Route::get('/sospensioni/{sospensione}/sostituzione', [\App\Http\Controllers\SostituzioneController::class, 'form'])->name('sostituzioni.form');
        Route::post('/sospensioni/{sospensione}/sostituzione', [\App\Http\Controllers\SostituzioneController::class, 'assegna'])->name('sostituzioni.assegna');
        Route::post('/sospensioni/{sospensione}/ripristina', [\App\Http\Controllers\SostituzioneController::class, 'ripristina'])->name('sostituzioni.ripristina');

        Route::post('/copia-da-sede/{area}', \App\Http\Controllers\CopiaDaSedeController::class)->name('sede.copia');
        Route::post('/scansione-oraria/standard', [ScansioneOrariaController::class, 'standard'])->name('scansione.standard');
        Route::put('/impostazioni', [\App\Http\Controllers\ImpostazioniController::class, 'update'])->name('impostazioni.update');
        Route::put('/scansione-oraria', [ScansioneOrariaController::class, 'update'])->name('scansione.update');

        Route::post('/worker/avvia', [WorkerController::class, 'avvia'])->name('worker.avvia');
        Route::post('/worker/ferma', [WorkerController::class, 'ferma'])->name('worker.ferma');

        Route::delete('/orari/{orario}', [OrarioController::class, 'destroy'])->name('orari.destroy');
        Route::get('/orari/{orario}/duplica', [OrarioController::class, 'duplicaForm'])->name('orari.duplica.form');
        Route::post('/orari/{orario}/duplica', [OrarioController::class, 'duplica'])->name('orari.duplica');
        Route::get('/orari/{orario}/nome', [OrarioController::class, 'nomeForm'])->name('orari.nome.form');
        Route::put('/orari/{orario}', [OrarioController::class, 'aggiornaNome'])->name('orari.nome');
        Route::get('/orari/{orario}/lezioni/{lezione}/destinazioni-aule', [OrarioController::class, 'destinazioniAuleLezione'])->name('orari.lezioni.destinazioni-aule');
        Route::patch('/orari/{orario}/lezioni/{lezione}/aula', [OrarioController::class, 'cambiaAulaLezione'])->name('orari.lezioni.aula');
        Route::get('/orari/{orario}/lezioni/{lezione}/destinazioni', [OrarioController::class, 'destinazioniLezione'])->name('orari.lezioni.destinazioni');
        Route::patch('/orari/{orario}/lezioni/{lezione}/sposta', [OrarioController::class, 'spostaLezione'])->name('orari.lezioni.sposta');
        Route::patch('/orari/{orario}/lezioni/{lezione}/cattedra', [OrarioController::class, 'cambiaCattedraLezione'])->name('orari.lezioni.cattedra');
        Route::post('/orari/{orario}/lezioni/{lezione}/blocca', [OrarioController::class, 'bloccaLezione'])->name('orari.lezioni.blocca');
        Route::post('/orari/{orario}/annulla-ultima', [OrarioController::class, 'annullaUltima'])->name('orari.annulla-ultima');
        Route::post('/orari/{orario}/ripeti', [OrarioController::class, 'ripeti'])->name('orari.ripeti');
        Route::post('/orari/{orario}/avvisi/azzera', [OrarioController::class, 'azzeraAvvisi'])->name('orari.avvisi.azzera');
    });

    // Esporta/importa CSV delle liste (permessi controllati dal controller, per lista).
    Route::get('/csv/{lista}', [\App\Http\Controllers\CsvController::class, 'esporta'])->name('csv.esporta');
    Route::get('/csv/{lista}/importa', [\App\Http\Controllers\CsvController::class, 'form'])->name('csv.form');
    Route::post('/csv/{lista}/importa', [\App\Http\Controllers\CsvController::class, 'importa'])->name('csv.importa');

    // Esporta/importa i dati (ZIP con un JSON per tabella): solo l'amministratore.
    Route::middleware('can:gestisci-utenze')->prefix('dati')->name('dati.')->group(function () {
        Route::get('/', [\App\Http\Controllers\DatiController::class, 'index'])->name('index');
        Route::post('/esporta', [\App\Http\Controllers\DatiController::class, 'esporta'])->name('esporta');
        Route::post('/importa/controlla', [\App\Http\Controllers\DatiController::class, 'anteprima'])->name('anteprima');
        Route::post('/importa', [\App\Http\Controllers\DatiController::class, 'importa'])->name('importa');
    });

    // Controllo nuove versioni: solo chi amministra (quindi chi può aggiornare l'installazione).
    Route::get('/aggiornamenti', \App\Http\Controllers\AggiornamentiController::class)->middleware('can:gestisci-utenze')->name('aggiornamenti');

    // Registro delle attività: chi può approvare gli orari (amministratore, DS).
    Route::get('/audit', [\App\Http\Controllers\AuditController::class, 'index'])->middleware('can:approva-orari')->name('audit.index');

    // Utenze con accesso al sistema: solo l'amministratore.
    Route::middleware('ruolo:'.Ruoli::AMMINISTRATORE)->group(function () {
        Route::resource('utenze', UtenzaController::class)->parameters(['utenze' => 'utenza'])->except(['show']);
    });

    // Gestione docenti e classi: anche la segreteria.
    Route::middleware('ruolo:'.implode(',', Ruoli::GESTIONE_DOCENTI_CLASSI))->group(function () use ($parametriRisorse) {
        Route::resource('docenti', DocenteController::class)->parameters($parametriRisorse['docenti'])->only(['store', 'update', 'destroy']);

        Route::resource('classi', ClasseController::class)->parameters($parametriRisorse['classi'])->only(['store', 'update', 'destroy']);
    });
});
