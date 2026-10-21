<?php

declare(strict_types=1);

namespace App\Libraries\Common;

use App\Models\Common\ActionModel;
use App\Models\Common\JournalAuditModel;

class AuditService
{
    public function __construct(
        private readonly JournalAuditModel $journalAuditModel,
        private readonly ActionModel $actionModel,
    ) {
    }

    public function journaliser(
        ?int $idUtilisateur,
        string $action,
        string $entite,
        int|string|null $idEntite = null,
        array|string|null $details = null,
        ?string $adresseIp = null,
    ): void {
        try {
            $actionId = $this->actionModel->assurer($action);

            $this->journalAuditModel->inserer([
                'id_utilisateur' => $idUtilisateur,
                'action'         => $actionId,
                'entite'         => $entite,
                'id_entite'      => $idEntite !== null ? (string) $idEntite : null,
                'details'        => $this->serialiserDetails($details),
                'adresse_ip'     => $adresseIp,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Journalisation audit impossible : ' . $e->getMessage());
        }
    }

    public function journaliserConnexion(int $idUtilisateur, string $adresseIp): void
    {
        $this->journaliser($idUtilisateur, 'LOGIN', 'utilisateur', $idUtilisateur, null, $adresseIp);
    }

    public function journaliserDeconnexion(int $idUtilisateur, string $adresseIp): void
    {
        $this->journaliser($idUtilisateur, 'LOGOUT', 'utilisateur', $idUtilisateur, null, $adresseIp);
    }

    public function journaliserEchec(?int $idUtilisateur, string $identifiant, string $adresseIp): void
    {
        $this->journaliser(
            $idUtilisateur,
            'REFUS',
            'auth',
            $identifiant,
            ['identifiant' => $identifiant],
            $adresseIp
        );
    }

    private function serialiserDetails(array|string|null $details): ?string
    {
        if ($details === null) {
            return null;
        }

        if (is_string($details)) {
            return $details;
        }

        $details = $this->masquerSecrets($details);

        return json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function masquerSecrets(array $details): array
    {
        $secrets = ['mot_de_passe', 'motDePasse', 'mot_de_passe_hash', 'motDePasseHash', 'password', 'token'];

        foreach ($secrets as $cle) {
            if (array_key_exists($cle, $details)) {
                $details[$cle] = '***';
            }
        }

        return $details;
    }
}
