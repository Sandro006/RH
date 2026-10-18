<?php

declare(strict_types=1);

namespace Tests\Unit\Personnel;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\Personnel\MatriculeService;
use App\Models\Personnel\SalarieModel;

/** @internal */
final class MatriculeServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = false;
    protected $refresh   = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Charge la fixture minimale
        $this->db->query(file_get_contents(ROOTPATH . 'script/fixtures_embauche.sql'));
    }

    public function testPremierMatricule(): void
    {
        $svc = new MatriculeService(new SalarieModel());
        $matricule = $svc->genererMatricule('2026-06-02');

        $this->assertSame('MAT-2026-00001', $matricule);
    }

    public function testDeuxiemeMatricule(): void
    {
        $model = new SalarieModel();
        $svc   = new MatriculeService($model);

        $m1 = $svc->genererMatricule('2026-06-02');
        $model->insert([
            'matricule' => $m1,
            'nom'       => 'Test',
            'prenom'    => 'User',
            'statut'    => 1,
        ]);

        $m2 = $svc->genererMatricule('2026-06-02');
        $this->assertSame('MAT-2026-00002', $m2);
    }

    public function testAnneeDifferente(): void
    {
        $svc = new MatriculeService(new SalarieModel());

        $m2025 = $svc->genererMatricule('2025-12-31');
        $m2026 = $svc->genererMatricule('2026-01-01');

        $this->assertSame('MAT-2025-00001', $m2025);
        $this->assertSame('MAT-2026-00001', $m2026);
    }

    public function testPasDeCollision(): void
    {
        $model = new SalarieModel();
        $svc   = new MatriculeService($model);

        // Insère manuellement MAT-2026-00001 pour simuler une collision
        $model->insert([
            'matricule' => 'MAT-2026-00001',
            'nom'       => 'Collision',
            'prenom'    => 'Test',
            'statut'    => 1,
        ]);

        $m = $svc->genererMatricule('2026-06-02');
        $this->assertSame('MAT-2026-00002', $m);
    }
}