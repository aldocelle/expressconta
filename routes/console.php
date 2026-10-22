<?php

use App\Models\Study;
use App\Models\StudyMembership;
use App\Models\User;
use App\Models\WorkTemplate;
use App\Services\Workflows;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('expressconta:generate {period?}', function () {
    $period = $this->argument('period') ?? now('America/Santiago')->format('Y-m');
    if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
        $this->error('Usa YYYY-MM.');

        return 1;
    }$this->info(json_encode(app(Workflows::class)->generate($period)));
})->purpose('Genera trabajos y cargos recurrentes sin duplicados');
Schedule::command('expressconta:generate')->dailyAt('06:00')->timezone('America/Santiago')->withoutOverlapping();

Artisan::command('expressconta:setup {name : Nombre del estudio} {email : Correo administrador}', function () {
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
        $this->error('Correo inválido o ya registrado.');

        return 1;
    }
    $password = $this->secret('Contraseña del administrador (mínimo 12 caracteres)');
    if (strlen((string) $password) < 12) {
        $this->error('Contraseña demasiado corta.');

        return 1;
    }
    DB::transaction(function () use ($email, $password) {
        $s = Study::create(['name' => $this->argument('name')]);
        $u = User::create(['name' => 'Administrador', 'email' => $email, 'password' => $password]);
        StudyMembership::create(['study_id' => $s->id, 'user_id' => $u->id, 'role' => 'admin']);
        WorkTemplate::create(['study_id' => $s->id, 'name' => 'Contabilidad mensual', 'checklist' => [['title' => 'Verificar antecedentes', 'done' => false], ['title' => 'Preparar informe', 'done' => false]], 'requests' => [['title' => 'Cartola bancaria del período', 'instructions' => 'Adjunta el PDF del período solicitado.', 'required' => true], ['title' => 'Respaldos de gastos', 'required' => true]]]);
    });
    $this->info('Estudio y administrador creados.');
})->purpose('Crea un estudio vacío con su primer administrador, sin cargar datos demo');
