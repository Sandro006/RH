<?php

declare(strict_types=1);

namespace App\Controllers\Recrutement;

use App\Controllers\BaseController;
use App\Services\Recrutement\CandidatureService;
use App\Services\Recrutement\OffreEmploiService;
use CodeIgniter\HTTP\ResponseInterface;

final class CandidaturePubliqueController extends BaseController
{
    public function offres(): string
    {
        return view('layouts/public', [
            'title' => 'Offres d’emploi · Gestion RH',
            'content' => view('pages/recrutement/candidatures-publiques'),
            'scripts' => ['/assets/js/pages/recrutement/candidatures-publiques.js'],
        ]);
    }

    public function formulaire(int $id): string
    {
        return view('layouts/public', [
            'title' => 'Déposer une candidature · Gestion RH',
            'content' => view('pages/recrutement/candidature-publique-form', ['offreId' => $id]),
            'scripts' => ['/assets/js/pages/recrutement/candidature-publique-form.js'],
        ]);
    }

    public function deposer(int $id): ResponseInterface
    {
        $data = $this->request->getPost();
        $data['id_offre'] = $id;
        $data['origine_candidature'] = 'EXTERNE';

        foreach (['nom', 'prenom', 'email', 'telephone', 'adresse'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                return $this->error('Veuillez renseigner tous les champs obligatoires.', 422);
            }
        }

        $cv = $this->request->getFile('cv');
        if ($cv === null || !$cv->isValid() || $cv->hasMoved()) {
            return $this->error('Un CV valide est obligatoire.', 422);
        }
        if ($cv->getSizeByUnit('mb') > 5 || !in_array(strtolower((string) $cv->getExtension()), ['pdf', 'doc', 'docx'], true)) {
            return $this->error('Le CV doit être au format PDF, DOC ou DOCX et ne pas dépasser 5 Mo.', 422);
        }

        $directory = WRITEPATH . 'uploads/candidatures';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return $this->error('Le stockage du CV est indisponible.', 500);
        }

        $filename = $cv->getRandomName();
        $storedPath = 'uploads/candidatures/' . $filename;
        try {
            $cv->move($directory, $filename);
            $data['cv'] = $storedPath;
            $candidatureId = (new CandidatureService())->postuler($data);
            return $this->response->setStatusCode(201)->setJSON(['data' => ['id_candidature' => $candidatureId]]);
        } catch (\Throwable $exception) {
            $file = $directory . DIRECTORY_SEPARATOR . $filename;
            if (is_file($file)) {
                unlink($file);
            }
            return $this->error($exception->getMessage(), $exception instanceof \DomainException ? 409 : 400);
        }
    }

    private function error(string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['error' => $message]);
    }
}
