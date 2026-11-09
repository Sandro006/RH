<?php

declare(strict_types=1);

namespace App\Services\Conges;

/**
 * NotificationService : notifications workflow (congés).
 * Aucune table de notification n'existe dans le schéma Bloc 7 — ce service
 * est donc un socle log + appel au provider à brancher (e-mail/Slack) :
 * les méthodes gardent la signature TODO, la livraison réelle est isolée
 * dans notifier() pour pouvoir la brancher sans toucher aux appelants.
 */
class NotificationService
{
    public function notifierManager(int $idDemande, array $contexte = []): void
    {
        $this->notifier('manager', $idDemande, $contexte);
    }

    public function notifierRh(int $idDemande, array $contexte = []): void
    {
        $this->notifier('rh', $idDemande, $contexte);
    }

    public function notifierSalarie(int $idDemande, array $contexte = []): void
    {
        $this->notifier('salarie', $idDemande, $contexte);
    }

    private function notifier(string $destinataire, int $idDemande, array $contexte): void
    {
        $payload = json_encode(array_merge($contexte, [
            'destinataire' => $destinataire,
            'id_demande'   => $idDemande,
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        log_message('info', "[Conges] Notification {$destinataire} demande #{$idDemande} : {$payload}");

        // TODO(reel) : brancher le provider (e-mail/Slack) ici, ou alimenter
        // une table de notification côté app.
    }
}
