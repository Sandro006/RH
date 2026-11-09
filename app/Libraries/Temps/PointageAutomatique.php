<?php
declare(strict_types=1);
namespace App\Libraries\Temps;
use App\Libraries\Common\SessionUtilisateur;
use App\Exceptions\DonneesIncoherentesException;
final class PointageAutomatique
{
    public function salarie(SessionUtilisateur $session): ?int
    {
        if ($session->idEmploye === null) return null;
        $e = db_connect()->table('employe')->select('id_salarie')->where('id', $session->idEmploye)->get()->getRowArray();
        return !empty($e['id_salarie']) ? (int) $e['id_salarie'] : null;
    }
    public function enregistrer(SessionUtilisateur $session, string $type): void
    {
        $id = $this->salarie($session); if ($id === null) return;
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Indian/Antananarivo'));
        $db = db_connect(); $db->transStart();
        $db->query('SELECT pg_advisory_xact_lock(?, ?)', [86743, $id]);
        $dernier = $db->table('temps_pointage')->where('id_salarie', $id)
            ->where('instant >=', $now->format('Y-m-d') . ' 00:00:00')
            ->where('instant <', $now->modify('+1 day')->format('Y-m-d') . ' 00:00:00')
            ->orderBy('instant', 'DESC')->get(1)->getRowArray();
        if (($type === 'entree' && (!$dernier || $dernier['type'] === 'sortie')) || ($type === 'sortie' && $dernier && $dernier['type'] === 'entree')) {
            $instant = $now->format('Y-m-d H:i:s');
            if ($dernier && $dernier['instant'] === $instant) { $db->transComplete(); throw new DonneesIncoherentesException('Pointage trop rapproché : réessayez dans une seconde.'); }
            $db->table('temps_pointage')->insert(['id_salarie' => $id, 'instant' => $instant, 'type' => $type]);
        }
        $db->transComplete();
        if (!$db->transStatus()) throw new \RuntimeException('Échec de l’enregistrement du pointage.');
    }
}
