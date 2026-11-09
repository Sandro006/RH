<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Entities\Admin\Utilisateur;
use App\Libraries\Auth\SessionManager;
use App\Libraries\Common\AuditService;
use App\Libraries\Common\SessionUtilisateur;
use App\Models\Common\ActionModel;
use App\Models\Common\JournalAuditModel;

trait AuthTestTrait
{
    protected function chargerDonneesAuth(): void
    {
        $this->executerScriptSql(ROOTPATH . 'script/db/02_seeds.sql');

        $this->db->table('journal_audit')->emptyTable();
        $this->db->table('utilisateur')
            ->where('id_utilisateur >', 0)
            ->update([
                'tentatives_echec'          => 0,
                'verrouille_jusqu_a'        => null,
                'actif'                     => true,
                'doit_changer_mot_de_passe' => false,
            ]);
        $this->db->table('utilisateur')->like('identifiant', 'test_', 'after')->delete();

        $hashes = [
            'admin'     => '$2y$10$mJLmOD2k7sV8QMF6maNtreXOD5X083DEs9ENp/qFQd5X0tSk/VHv.',
            'rh'        => '$2y$10$AAP7eNjEbOJs72tZMs6xx.1Hfle36kvKtWRJL4tlbUMWhICLJ0xTq',
            'manager'   => '$2y$10$GVPzEf9DToA1nO9z0m90fut3hNaJmjvoyjZcZOagMS9I6yI21h9s2',
            'recruteur' => '$2y$10$CRLHG5ooLm5EmSiEVbU2HerXuU7yjg00U/MIXEqRdUtNO6F8X98Dm',
            'direction' => '$2y$10$ns9UBPjwwhYZIbcf3Y3NieW2YdctoMkrgozaX42Y9Kj3RsQwKQfZ.',
            'controle'  => '$2y$10$7yKWvBhj40vg7DA9nLWQJeZTboO53aJGYfzx3ib8tOJNGYxHVb1SK',
            'salarie'   => '$2y$10$DdVdvoZbZS/EgFil9s7nGuxCLC0haojA87B.7pVry2ySHpkezaz7q',
        ];

        foreach ($hashes as $identifiant => $hash) {
            $this->db->table('utilisateur')
                ->where('identifiant', $identifiant)
                ->update([
                    'mot_de_passe_hash'         => $hash,
                    'doit_changer_mot_de_passe' => false,
                ]);
        }
    }

    private function executerScriptSql(string $chemin): void
    {
        $contenu = file_get_contents($chemin);

        if ($contenu === false) {
            throw new \RuntimeException("Script SQL introuvable : {$chemin}");
        }

        foreach (explode(';', $contenu) as $requete) {
            $requete = trim($requete);

            if ($requete !== '') {
                $this->db->query($requete);
            }
        }
    }

    protected function auditService(): AuditService
    {
        return new AuditService(
            new JournalAuditModel(),
            new ActionModel(),
        );
    }

    protected function sessionManagerFactice(): SessionManager
    {
        return new class extends SessionManager {
            public function ouvrir(Utilisateur $utilisateur, string $adresseIp = ''): SessionUtilisateur
            {
                return new SessionUtilisateur(
                    (int) $utilisateur->id_utilisateur,
                    (string) $utilisateur->identifiant,
                    (string) $utilisateur->role,
                    $utilisateur->id_employe !== null ? (int) $utilisateur->id_employe : null,
                    $adresseIp,
                    date('c'),
                    time(),
                    (bool) ($utilisateur->doit_changer_mot_de_passe ?? false),
                );
            }

            public function detruire(): void
            {
            }

            public function marquerMotDePasseChange(): void
            {
            }
        };
    }

    protected function sessionUtilisateur(string $role = 'admin'): SessionUtilisateur
    {
        $ligne = $this->db->table('utilisateur')
            ->where('role', $role)
            ->where('actif', true)
            ->orderBy('id_utilisateur', 'ASC')
            ->get()
            ->getRow();

        if ($ligne === null) {
            throw new \RuntimeException("Aucun utilisateur actif pour le rôle {$role}");
        }

        return new SessionUtilisateur(
            (int) $ligne->id_utilisateur,
            (string) $ligne->identifiant,
            (string) $ligne->role,
            $ligne->id_employe !== null ? (int) $ligne->id_employe : null,
            '127.0.0.1',
            date('c'),
            time(),
            (bool) $ligne->doit_changer_mot_de_passe,
        );
    }

    protected function idUtilisateur(string $identifiant): int
    {
        $ligne = $this->db->table('utilisateur')
            ->select('id_utilisateur')
            ->where('identifiant', $identifiant)
            ->get()
            ->getRow();

        if ($ligne === null) {
            throw new \RuntimeException("Utilisateur {$identifiant} introuvable");
        }

        return (int) $ligne->id_utilisateur;
    }
}
