<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, Model $entity, array $details = []): void
    {
        $c = app(StudyContext::class);
        AuditEvent::create(['study_id' => $c->studyId, 'user_id' => $c->user->id, 'company_id' => $entity instanceof Company ? $entity->id : $entity->company_id, 'action' => $action, 'entity_type' => class_basename($entity), 'entity_id' => $entity->id, 'details' => $details]);
    }
}
