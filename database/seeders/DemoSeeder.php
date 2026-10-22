<?php

namespace Database\Seeders;

use App\Models\Charge;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Delivery;
use App\Models\Engagement;
use App\Models\Report;
use App\Models\Study;
use App\Models\StudyMembership;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkTemplate;
use App\Rules\Rut;
use App\Services\Workflows;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('El seed demo solo se permite en local o testing.');
        }
        $password = env('DEMO_PASSWORD');
        if (! $password || strlen($password) < 12) {
            throw new \RuntimeException('Define DEMO_PASSWORD con al menos 12 caracteres.');
        }
        if (User::where('email', 'admin@expressconta.test')->exists()) {
            throw new \RuntimeException('El demo ya está cargado. Usa una base vacía.');
        }
        DB::transaction(function () use ($password) {
            $s = Study::create(['name' => 'Costa & Asociados']);
            $users = [];
            foreach ([['Carolina Costa', 'admin', 'admin'], ['Matías Rojas', 'accountant', 'contador'], ['Daniela Soto', 'reviewer', 'revisor'], ['Francisca Pérez', 'client', 'cliente']] as [$name,$role,$mail]) {
                $u = User::create(['name' => $name, 'email' => $mail.'@expressconta.test', 'password' => $password]);
                StudyMembership::create(['study_id' => $s->id, 'user_id' => $u->id, 'role' => $role]);
                $users[$role] = $u;
            }
            $template = WorkTemplate::create(['study_id' => $s->id, 'name' => 'Contabilidad mensual', 'checklist' => [['title' => 'Verificar antecedentes', 'done' => false], ['title' => 'Preparar informe', 'done' => false]], 'requests' => [['title' => 'Cartola bancaria del período', 'instructions' => 'Adjunta el PDF del período solicitado.', 'required' => true], ['title' => 'Respaldos de gastos', 'instructions' => 'Adjunta los respaldos disponibles.', 'required' => true]]]);
            $names = ['Café Puerto', 'Clínica Los Aromos', 'Transportes Ruta Sur', 'Taller Pacífico', 'Diseño Cerro Alegre', 'Comercial Bahía'];
            $ruts = ['76.000.001-9', '76.000.002-7', '76.000.003-5', '76.000.004-3', '76.000.005-1', '76.000.006-K'];
            $companies = [];
            foreach ($names as $i => $name) {
                $co = Company::create(['study_id' => $s->id, 'name' => $name, 'trade_name' => $name, 'rut' => Rut::normalize($ruts[$i]), 'contact_name' => $i === 0 ? 'Francisca Pérez' : 'Contacto de '.$name, 'contact_email' => 'contacto'.$i.'@example.com', 'service' => 'Contabilidad mensual', 'monthly_fee' => 150000, 'responsible_id' => $i % 2 ? $users['accountant']->id : $users['admin']->id, 'starts_on' => today('America/Santiago')->startOfMonth()]);
                $co->members()->attach([$users['accountant']->id, $users['reviewer']->id]);
                if ($i < 2) {
                    $co->members()->attach($users['client']->id);
                }Engagement::create(['study_id' => $s->id, 'company_id' => $co->id, 'work_template_id' => $template->id, 'monthly_fee' => 150000, 'starts_on' => today('America/Santiago')->startOfMonth()]);
                $companies[] = $co;
            }
            $period = now('America/Santiago')->format('Y-m');
            app(Workflows::class)->generate($period);
            $works = WorkItem::where('study_id', $s->id)->orderBy('id')->get();
            foreach ($works as $i => $w) {
                if ($i >= 3) {
                    $w->requests()->update(['status' => 'accepted']);
                    $w->update(['status' => $i === 5 ? 'delivered' : 'review', 'checklist' => [['title' => 'Verificar antecedentes', 'done' => true], ['title' => 'Preparar informe', 'done' => true]], 'approved_by' => $i === 5 ? $users['admin']->id : null, 'approved_at' => $i === 5 ? now() : null]);
                }Task::create(['study_id' => $s->id, 'company_id' => $w->company_id, 'work_item_id' => $w->id, 'title' => $i < 3 ? 'Revisar antecedentes pendientes' : 'Revisar informe mensual', 'due_on' => today('America/Santiago')->addDays($i - 1), 'responsible_id' => $w->responsible_id, 'priority' => $i === 0 ? 'high' : 'normal']);
            }
            $doc = $works[0]->requests()->first();
            $path = 'demo/cartola-periodo-anterior.txt';
            Storage::disk('local')->put($path, 'Documento ficticio de demostración. Cartola del período anterior.');
            $doc->files()->create(['study_id' => $s->id, 'company_id' => $doc->company_id, 'uploaded_by' => $users['client']->id, 'original_name' => 'cartola-periodo-anterior.txt', 'path' => $path, 'mime' => 'text/plain', 'size' => Storage::disk('local')->size($path)]);
            $doc->update(['status' => 'correction', 'review_comment' => 'La cartola corresponde al período anterior. Adjunta el período solicitado.']);
            foreach (Charge::orderBy('id')->get() as $i => $charge) {
                if ($i >= 2) {
                    $charge->payments()->create(['recorded_by' => $users['admin']->id, 'amount' => $i === 2 ? 75000 : 150000, 'paid_on' => today('America/Santiago'), 'reference' => 'DEMO-'.$charge->id]);
                }if ($i === 0) {
                    $charge->update(['due_on' => today('America/Santiago')->subDays(10)]);
                }
            }
            $conv = Conversation::create(['study_id' => $s->id, 'company_id' => $companies[0]->id, 'subject' => 'Corrección de cartola bancaria']);
            $conv->messages()->create(['user_id' => $users['admin']->id, 'body' => 'Hola, Francisca. Necesitamos la cartola del período solicitado para continuar.', 'internal' => false]);
            $conv->messages()->create(['user_id' => $users['admin']->id, 'body' => 'Nota interna: verificar período del archivo nuevo antes de aceptar.', 'internal' => true]);
            $w = $works[5];
            $report = Report::create(['study_id' => $s->id, 'company_id' => $w->company_id, 'work_item_id' => $w->id, 'title' => 'Informe mensual · '.$period, 'approved_by' => $users['admin']->id, 'approved_at' => now(), 'published_at' => now()]);
            $path = 'demo/informe.txt';
            Storage::disk('local')->put($path, 'Informe ficticio de ejemplo. Sin valor contable.');
            $report->files()->create(['study_id' => $s->id, 'company_id' => $w->company_id, 'uploaded_by' => $users['admin']->id, 'original_name' => 'informe-demostracion.txt', 'path' => $path, 'mime' => 'text/plain', 'size' => Storage::disk('local')->size($path)]);
            Delivery::create(['report_id' => $report->id, 'user_id' => $users['admin']->id, 'action' => 'published']);
        });
    }
}
