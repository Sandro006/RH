<?php

declare(strict_types=1);

namespace App\Libraries\Common;

use CodeIgniter\Database\BaseConnection;

/**
 * Centralise les changements de statut et leur traçabilité.
 * Les tables sont choisies explicitement afin d'éviter toute entrée utilisateur
 * directement utilisée comme nom de table ou de colonne.
 */
class WorkflowService
{
    private const CONFIGURATIONS = [
        'demande' => ['table' => 'demande_recrutement', 'id' => 'id_demande', 'status' => 'statut', 'history' => 'demande_recrutement_status_historique'],
        'offre' => ['table' => 'offre_emploi', 'id' => 'id_offre', 'status' => 'statut', 'history' => 'offre_emploi_status_historique'],
        'candidature' => ['table' => 'candidature', 'id' => 'id_candidature', 'status' => 'statut', 'history' => 'candidature_status_historique'],
        'entretien' => ['table' => 'entretien', 'id' => 'id_entretien', 'status' => 'statut', 'history' => 'entretien_status_historique'],
        'proposition' => ['table' => 'proposition_embauche', 'id' => 'id_proposition', 'status' => 'statut', 'history' => 'proposition_embauche_status_historique'],
        'revendication' => ['table' => 'revendication', 'id' => 'id_revendication', 'status' => 'statut_id', 'history' => 'revendication_status_historique'],
        'salarie' => ['table' => 'salarie', 'id' => 'id_salarie', 'status' => 'statut', 'history' => 'salarie_status_historique'],
        'contrat' => ['table' => 'contrat', 'id' => 'id_contrat', 'status' => 'statut', 'history' => 'contrat_status_historique'],
        'onboarding' => ['table' => 'onboarding', 'id' => 'id_onboarding', 'status' => 'statut', 'history' => 'onboarding_status_historique'],
        'onboarding_tache' => ['table' => 'onboarding_tache', 'id' => 'id_tache', 'status' => 'statut', 'history' => 'onboarding_tache_status_historique'],
    ];

    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    public function changerStatut(
        string $entite,
        int $id,
        string $statutCible,
        ?SessionUtilisateur $utilisateur = null,
        ?string $commentaire = null
    ): void {
        $configuration = self::CONFIGURATIONS[$entite] ?? null;

        if ($configuration === null) {
            throw new \InvalidArgumentException("Entité workflow inconnue : {$entite}");
        }

        $db = $this->db ?? db_connect();
        $ligne = $db->table($configuration['table'])
            ->select($configuration['status'])
            ->where($configuration['id'], $id)
            ->get()
            ->getRowArray();

        if ($ligne === null) {
            throw new \RuntimeException("Objet {$entite} #{$id} introuvable");
        }

        $statusTable = $this->tableStatuts($entite);
        $nouveau = $db->table($statusTable)
            ->select('id')
            ->where('designation', $statutCible)
            ->get()
            ->getRowArray();

        if ($nouveau === null) {
            throw new \RuntimeException("Statut inconnu : {$statutCible}");
        }

        $ancien = $ligne[$configuration['status']] ?? null;
        $nouvelId = (int) $nouveau['id'];

        if ((int) $ancien === $nouvelId) {
            return;
        }

        $db->transStart();
        $db->table($configuration['table'])
            ->where($configuration['id'], $id)
            ->update([$configuration['status'] => $nouvelId]);

        $db->table($configuration['history'])->insert([
            $this->historyIdColumn($entite) => $id,
            'statut_ancien' => $ancien !== null ? (int) $ancien : $nouvelId,
            'statut_nouveau' => $nouvelId,
            'date_changement' => date('Y-m-d H:i:s'),
            'id_utilisateur' => $utilisateur?->idUtilisateur,
            'commentaire' => $commentaire,
        ]);
        $db->transComplete();
    }

    private function tableStatuts(string $entite): string
    {
        return match ($entite) {
            'demande', 'offre' => 'status',
            'candidature' => 'status_candidature',
            'entretien' => 'status_entretien',
            'proposition' => 'status_proposition_embauche',
            'revendication' => 'status_revendication',
            'salarie' => 'status_salarie',
            'contrat' => 'status_contrat',
            'onboarding' => 'status_onboarding',
            'onboarding_tache' => 'status_onboarding_tache',
        };
    }

    private function historyIdColumn(string $entite): string
    {
        return match ($entite) {
            'demande' => 'id_demande',
            'offre' => 'id_offre',
            'candidature' => 'id_candidature',
            'entretien' => 'id_entretien',
            'proposition' => 'id_proposition',
            'revendication' => 'id_revendication',
            'salarie' => 'id_salarie',
            'contrat' => 'id_contrat',
            'onboarding' => 'id_onboarding',
            'onboarding_tache' => 'id_tache',
        };
    }
}
