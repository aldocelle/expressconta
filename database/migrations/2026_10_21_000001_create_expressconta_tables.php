<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('studies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('study_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('role', 20);
            $t->unique(['study_id', 'user_id']);
            $t->timestamps();
        });
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->string('name');
            $t->string('trade_name')->nullable();
            $t->string('rut', 20);
            $t->string('business')->nullable();
            $t->string('address')->nullable();
            $t->string('contact_name')->nullable();
            $t->string('contact_email')->nullable();
            $t->string('contact_phone')->nullable();
            $t->string('service')->default('Contabilidad mensual');
            $t->unsignedBigInteger('monthly_fee')->default(150000);
            $t->date('starts_on')->nullable();
            $t->foreignId('responsible_id')->nullable()->constrained('users');
            $t->timestamp('archived_at')->nullable();
            $t->unique(['study_id', 'rut']);
            $t->index(['study_id', 'archived_at']);
            $t->timestamps();
        });
        Schema::create('company_memberships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->unique(['company_id', 'user_id']);
            $t->timestamps();
        });
        Schema::create('work_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->string('name');
            $t->json('checklist');
            $t->json('requests');
            $t->boolean('active')->default(true);
            $t->unique(['study_id', 'name']);
            $t->timestamps();
        });
        Schema::create('engagements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('work_template_id')->constrained();
            $t->unsignedBigInteger('monthly_fee');
            $t->date('starts_on');
            $t->boolean('active')->default(true);
            $t->unique(['company_id', 'work_template_id']);
            $t->timestamps();
        });
        Schema::create('work_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('engagement_id')->nullable()->constrained();
            $t->string('title');
            $t->string('period', 7);
            $t->string('status')->default('not_started');
            $t->foreignId('responsible_id')->nullable()->constrained('users');
            $t->foreignId('reviewer_id')->nullable()->constrained('users');
            $t->date('due_on')->nullable();
            $t->json('checklist');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->unique(['engagement_id', 'period']);
            $t->index(['study_id', 'period', 'status']);
            $t->timestamps();
        });
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('work_item_id')->nullable()->constrained();
            $t->string('title');
            $t->string('status')->default('pending');
            $t->string('priority')->default('normal');
            $t->foreignId('responsible_id')->nullable()->constrained('users');
            $t->date('due_on')->nullable();
            $t->timestamps();
        });
        Schema::create('document_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('work_item_id')->constrained();
            $t->string('title');
            $t->text('instructions')->nullable();
            $t->date('due_on')->nullable();
            $t->boolean('required')->default(true);
            $t->string('status')->default('pending');
            $t->text('review_comment')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
        });
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('work_item_id')->constrained();
            $t->string('title');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
        Schema::create('file_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('document_request_id')->nullable()->constrained();
            $t->foreignId('report_id')->nullable()->constrained();
            $t->foreignId('uploaded_by')->constrained('users');
            $t->string('original_name');
            $t->string('path');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->timestamps();
        });
        Schema::create('document_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('file_version_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('decision');
            $t->text('comment')->nullable();
            $t->timestamps();
        });
        Schema::create('charges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('engagement_id')->nullable()->constrained();
            $t->string('period', 7);
            $t->string('description');
            $t->unsignedBigInteger('amount');
            $t->date('due_on');
            $t->unique(['engagement_id', 'period']);
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('charge_id')->constrained();
            $t->foreignId('recorded_by')->constrained('users');
            $t->unsignedBigInteger('amount');
            $t->date('paid_on');
            $t->string('reference', 100);
            $t->unique(['charge_id', 'reference']);
            $t->timestamps();
        });
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->foreignId('company_id')->constrained();
            $t->string('subject');
            $t->string('status')->default('open');
            $t->timestamps();
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->text('body');
            $t->boolean('internal')->default(false);
            $t->timestamps();
        });
        Schema::create('deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('action');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->foreignId('company_id')->nullable()->constrained();
            $t->string('action');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->json('details')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['study_id', 'created_at']);
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['failed_jobs', 'jobs', 'audit_events', 'notifications', 'deliveries', 'messages', 'conversations', 'payments', 'charges', 'document_reviews', 'file_versions', 'reports', 'document_requests', 'tasks', 'work_items', 'engagements', 'work_templates', 'company_memberships', 'companies', 'study_memberships', 'studies', 'password_reset_tokens', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
