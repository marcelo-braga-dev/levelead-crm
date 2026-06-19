<?php

namespace App\DomainServices;

use App\Enums\AuditActorType;
use Illuminate\Support\Facades\Auth;

/**
 * Resolve quem é o "ator" responsável pela próxima escrita em um model com a trait Auditable.
 *
 * Em requisições HTTP, o ator padrão é o usuário autenticado — nenhuma chamada explícita é
 * necessária. Jobs/Commands que escrevem em lote (ex.: ProcessCsvImportJob, comandos agendados)
 * devem chamar actingAs() antes de processar e reset() depois, dentro de um try/finally, para não
 * deixar o contexto "vazar" para o próximo job executado no mesmo worker.
 */
class AuditContext
{
    /** @var array{type: AuditActorType, id: int|null, label: string}|null */
    private static ?array $actor = null;

    public static function actingAs(AuditActorType $type, ?int $id, string $label): void
    {
        self::$actor = ['type' => $type, 'id' => $id, 'label' => $label];
    }

    /**
     * @return array{type: AuditActorType, id: int|null, label: string}
     */
    public static function current(): array
    {
        if (self::$actor !== null) {
            return self::$actor;
        }

        if (Auth::check()) {
            return [
                'type' => AuditActorType::User,
                'id' => Auth::id(),
                'label' => Auth::user()->name,
            ];
        }

        return [
            'type' => AuditActorType::System,
            'id' => null,
            'label' => 'system',
        ];
    }

    public static function reset(): void
    {
        self::$actor = null;
    }
}
