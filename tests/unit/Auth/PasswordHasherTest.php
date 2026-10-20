<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Libraries\Auth\PasswordHasher;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PasswordHasherTest extends CIUnitTestCase
{
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hasher = new PasswordHasher();
    }

    public function testHacherPuisVerifierLeMotDePasse(): void
    {
        $hash = $this->hasher->hacher('Admin1234.RH');

        $this->assertNotSame('Admin1234.RH', $hash);
        $this->assertTrue($this->hasher->verifier('Admin1234.RH', $hash));
        $this->assertFalse($this->hasher->verifier('Mauvais1234.RH', $hash));
        $this->assertFalse($this->hasher->doitEtreRehashe($hash));
    }

    public function testMotDePasseTemporaireRespecteLaPolitique(): void
    {
        $temporaire = $this->hasher->genererTemporaire();

        $this->assertTrue($this->hasher->validerComplexite($temporaire));
        $this->assertGreaterThanOrEqual(12, strlen($temporaire));
        $this->assertLessThanOrEqual(72, strlen($temporaire));
    }

    public function testPolitiqueRefuseLesMotsDePasseTropCourtsOuTropSimples(): void
    {
        $this->assertFalse($this->hasher->validerComplexite('Court1!'));
        $this->assertFalse($this->hasher->validerComplexite('motdepasseuniquement'));
        $this->assertFalse($this->hasher->validerComplexite('123456789012'));
        $this->assertTrue($this->hasher->validerComplexite('Admin1234.RH'));
    }
}
