<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\DocumentRequest;
use App\Models\Engagement;
use App\Models\FileVersion;
use App\Models\Report;
use App\Models\Study;
use App\Models\StudyMembership;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkTemplate;
use App\Services\Workflows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use RefreshDatabase;

    protected Study $study;

    protected User $admin;

    protected User $client;

    protected Company $company;

    protected WorkItem $work;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->study = Study::create(['name' => 'Estudio de prueba']);
        $this->admin = $this->user('admin');
        $this->client = $this->user('client');
        $this->company = $this->company();
        $this->company->members()->attach($this->client->id);
        $this->work = WorkItem::create(['study_id' => $this->study->id, 'company_id' => $this->company->id, 'title' => 'Contabilidad', 'period' => '2026-10', 'status' => 'waiting', 'checklist' => [['title' => 'Revisar', 'done' => false]]]);
    }

    protected function user(string $role): User
    {
        $u = User::create(['name' => $role, 'email' => uniqid().'@example.com', 'password' => 'Safe-test-password']);
        StudyMembership::create(['study_id' => $this->study->id, 'user_id' => $u->id, 'role' => $role]);

        return $u;
    }

    protected function company(?int $study = null): Company
    {
        return Company::create(['study_id' => $study ?? $this->study->id, 'name' => 'Empresa '.uniqid(), 'rut' => uniqid(), 'service' => 'Contabilidad', 'monthly_fee' => 150000]);
    }

    protected function asUser(User $u): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($u, 'web')->withSession(['study_id' => $this->study->id, 'password_hash_web' => null]);
    }

    protected function doc(): DocumentRequest
    {
        return DocumentRequest::create(['study_id' => $this->study->id, 'company_id' => $this->company->id, 'work_item_id' => $this->work->id, 'title' => 'Cartola', 'required' => true]);
    }

    public function test_guest_cannot_access_api(): void
    {
        $this->getJson('/api/companies')->assertUnauthorized();
    }

    public function test_login_and_logout(): void
    {
        $this->postJson('/api/login', ['email' => $this->admin->email, 'password' => 'Safe-test-password'])->assertOk()->assertJsonPath('role', 'admin');
        $this->postJson('/api/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_login_rejects_invalid_password(): void
    {
        $this->postJson('/api/login', ['email' => $this->admin->email, 'password' => 'wrong'])->assertUnprocessable();
    }

    public function test_client_only_lists_assigned_companies(): void
    {
        $other = $this->company();
        $this->asUser($this->client)->getJson('/api/companies')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/companies/'.$other->id)->assertNotFound();
    }

    public function test_study_id_payload_cannot_change_scope(): void
    {
        $s = Study::create(['name' => 'Otro estudio']);
        $other = $this->company($s->id);
        $this->asUser($this->admin)->getJson('/api/companies?study_id='.$s->id)->assertOk()->assertJsonMissing(['id' => $other->id, 'name' => $other->name]);
        $this->postJson('/api/study', ['study_id' => $s->id])->assertForbidden();
    }

    public function test_client_cannot_create_company_or_mark_document_accepted(): void
    {
        $d = $this->doc();
        $this->asUser($this->client)->postJson('/api/companies', ['name' => 'Intruso'])->assertForbidden();
        $this->patchJson('/api/documents/'.$d->id, ['status' => 'accepted', 'version' => 1])->assertForbidden();
    }

    public function test_accountant_must_have_company_assignment(): void
    {
        $u = $this->user('accountant');
        $this->asUser($u)->getJson('/api/works')->assertOk()->assertExactJson([]);
        $this->company->members()->attach($u->id);
        $this->getJson('/api/works')->assertJsonCount(1);
    }

    public function test_rut_validation_and_tenant_owned_fields(): void
    {
        $this->asUser($this->admin)->postJson('/api/companies', ['name' => 'Nueva', 'rut' => '76000000-1', 'service' => 'Contabilidad', 'monthly_fee' => 100])->assertUnprocessable();
        $this->postJson('/api/companies', ['name' => 'Nueva', 'rut' => '76.000.001-9', 'service' => 'Contabilidad', 'monthly_fee' => 100, 'study_id' => 999])->assertCreated()->assertJsonPath('study_id', $this->study->id);
    }

    public function test_upload_is_private_and_does_not_accept_document(): void
    {
        $d = $this->doc();
        $res = $this->asUser($this->client)->postJson('/api/documents/'.$d->id.'/upload', ['file' => UploadedFile::fake()->createWithContent('cartola.txt', 'Cartola de prueba')])->assertCreated()->assertJsonMissingPath('path');
        $this->assertDatabaseHas('document_requests', ['id' => $d->id, 'status' => 'received']);
        $file = FileVersion::find($res->json('id'));
        Storage::disk('local')->assertExists($file->path);
        $this->get('/api/files/'.$file->id)->assertOk();
        $other = $this->user('client');
        $this->asUser($other)->getJson('/api/files/'.$file->id)->assertNotFound();
    }

    public function test_document_review_requires_current_file_and_version(): void
    {
        $d = $this->doc();
        $f = $this->asUser($this->client)->postJson('/api/documents/'.$d->id.'/upload', ['file' => UploadedFile::fake()->createWithContent('a.txt', 'Contenido')])->json('id');
        $this->asUser($this->admin)->patchJson('/api/documents/'.$d->id, ['status' => 'accepted', 'version' => 1, 'file_id' => $f])->assertConflict();
        $this->patchJson('/api/documents/'.$d->id, ['status' => 'correction', 'version' => 2, 'file_id' => $f])->assertUnprocessable();
        $this->patchJson('/api/documents/'.$d->id, ['status' => 'accepted', 'version' => 2, 'file_id' => $f])->assertOk();
        $this->assertDatabaseCount('document_reviews', 1);
    }

    public function test_new_upload_preserves_old_version(): void
    {
        $d = $this->doc();
        $this->asUser($this->client);
        foreach (['uno', 'dos'] as $text) {
            $this->postJson('/api/documents/'.$d->id.'/upload', ['file' => UploadedFile::fake()->createWithContent($text.'.txt', $text)])->assertCreated();
        }$this->assertDatabaseCount('file_versions', 2);
        $this->assertDatabaseHas('document_requests', ['id' => $d->id, 'version' => 3]);
    }

    public function test_work_cannot_advance_without_accepted_documents(): void
    {
        $this->doc();
        $this->asUser($this->admin)->patchJson('/api/works/'.$this->work->id, ['status' => 'preparing', 'version' => 1])->assertUnprocessable();
    }

    public function test_work_checklist_and_version_guard(): void
    {
        $this->asUser($this->admin)->patchJson('/api/works/'.$this->work->id, ['status' => 'preparing', 'version' => 1])->assertOk();
        $this->patchJson('/api/works/'.$this->work->id, ['status' => 'review', 'version' => 1])->assertConflict();
        $this->patchJson('/api/works/'.$this->work->id, ['status' => 'review', 'version' => 2])->assertUnprocessable();
        $this->putJson('/api/works/'.$this->work->id.'/checklist', ['version' => 2, 'checklist' => [['title' => 'Revisar', 'done' => true]]])->assertOk();
        $this->patchJson('/api/works/'.$this->work->id, ['status' => 'review', 'version' => 3])->assertOk();
    }

    public function test_accountant_cannot_approve(): void
    {
        $u = $this->user('accountant');
        $this->company->members()->attach($u->id);
        $this->work->update(['status' => 'review', 'checklist' => []]);
        $this->asUser($u)->patchJson('/api/works/'.$this->work->id, ['status' => 'approved', 'version' => 1])->assertForbidden();
    }

    public function test_cannot_deliver_without_published_report(): void
    {
        $this->work->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $this->admin->id, 'checklist' => []]);
        $this->asUser($this->admin)->patchJson('/api/works/'.$this->work->id, ['status' => 'delivered', 'version' => 1])->assertUnprocessable();
    }

    public function test_reports_are_hidden_until_approved_and_published(): void
    {
        $r = Report::create(['study_id' => $this->study->id, 'company_id' => $this->company->id, 'work_item_id' => $this->work->id, 'title' => 'Borrador']);
        Storage::disk('local')->put('report.txt', 'Contenido');
        $f = $r->files()->create(['study_id' => $this->study->id, 'company_id' => $this->company->id, 'uploaded_by' => $this->admin->id, 'path' => 'report.txt', 'original_name' => 'report.txt', 'size' => 9, 'mime' => 'text/plain']);
        $this->asUser($this->client)->getJson('/api/reports')->assertExactJson([]);
        $this->getJson('/api/files/'.$f->id)->assertNotFound();
        $this->work->update(['status' => 'approved', 'approved_by' => $this->admin->id, 'approved_at' => now(), 'checklist' => []]);
        $this->asUser($this->admin)->postJson('/api/reports/'.$r->id.'/publish')->assertOk();
        $this->asUser($this->client)->getJson('/api/reports')->assertJsonCount(1);
        $this->get('/api/files/'.$f->id)->assertOk();
        $this->assertDatabaseHas('deliveries', ['report_id' => $r->id, 'action' => 'download']);
    }

    public function test_notes_are_filtered_on_server(): void
    {
        $co = Conversation::create(['study_id' => $this->study->id, 'company_id' => $this->company->id, 'subject' => 'Consulta']);
        foreach ([false, true] as $internal) {
            $co->messages()->create(['user_id' => $this->admin->id, 'body' => $internal ? 'SECRETO INTERNO' : 'Visible', 'internal' => $internal]);
        }$this->asUser($this->client)->getJson('/api/conversations')->assertOk()->assertJsonCount(1, '0.messages')->assertDontSee('SECRETO INTERNO');
        $this->postJson('/api/conversations/'.$co->id.'/messages', ['body' => 'Intento', 'internal' => true])->assertForbidden();
    }

    public function test_payments_partial_idempotent_and_no_overpayment(): void
    {
        $ch = Charge::create(['study_id' => $this->study->id, 'company_id' => $this->company->id, 'period' => '2026-10', 'description' => 'Honorarios', 'amount' => 150000, 'due_on' => today()->addDays(3)]);
        $data = ['amount' => 50000, 'paid_on' => today('America/Santiago')->toDateString(), 'reference' => 'TRANSFER-1'];
        $this->asUser($this->admin)->postJson('/api/charges/'.$ch->id.'/payments', $data)->assertCreated();
        $this->postJson('/api/charges/'.$ch->id.'/payments', $data)->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->postJson('/api/charges/'.$ch->id.'/payments', array_replace($data, ['amount' => 60000]))->assertConflict();
        $this->postJson('/api/charges/'.$ch->id.'/payments', array_replace($data, ['reference' => 'T2', 'amount' => 100001]))->assertUnprocessable();
        $this->getJson('/api/charges')->assertJsonPath('0.balance', 100000)->assertJsonPath('0.payment_status', 'partial');
        $this->asUser($this->client)->postJson('/api/charges/'.$ch->id.'/payments', $data)->assertForbidden();
    }

    public function test_recurring_generation_is_idempotent(): void
    {
        $t = WorkTemplate::create(['study_id' => $this->study->id, 'name' => 'Mensual', 'checklist' => [], 'requests' => [['title' => 'Cartola', 'required' => true]]]);
        Engagement::create(['study_id' => $this->study->id, 'company_id' => $this->company->id, 'work_template_id' => $t->id, 'monthly_fee' => 150000, 'starts_on' => '2026-10-01']);
        $f = app(Workflows::class);
        $this->assertSame(['works' => 1, 'charges' => 1], $f->generate('2026-10'));
        $this->assertSame(['works' => 0, 'charges' => 0], $f->generate('2026-10'));
        $this->assertDatabaseCount('charges', 1);
        $this->assertDatabaseCount('document_requests', 1);
        $this->company->update(['archived_at' => now()]);
        $this->assertSame(['works' => 0, 'charges' => 0], $f->generate('2026-11'));
    }

    public function test_new_user_access_assignments_cannot_cross_study(): void
    {
        $s = Study::create(['name' => 'Otro']);
        $co = $this->company($s->id);
        $this->asUser($this->admin)->postJson('/api/members',['name' => 'Invitado', 'email' => 'new@example.com', 'password' => 'Secure-test-password', 'role' => 'client', 'company_ids' => [$co->id]])->assertNotFound();
        $this->assertDatabaseMissing('users',['email' => 'new@example.com']);
    }
}
