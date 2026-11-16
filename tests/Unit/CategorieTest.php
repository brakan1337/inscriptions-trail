<?php

namespace Tests\Unit;

use App\Support\Categorie;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategorieTest extends TestCase
{
    public static function ages(): array
    {
        return [
            'cadet' => ['2010-11-30', 'CA'],
            'junior' => ['2008-01-01', 'JU'],
            'espoir' => ['2006-06-15', 'ES'],
            'senior' => ['2000-12-31', 'SE'],
            'senior jusqu\'à 34 ans' => ['1993-01-01', 'SE'],
            'master 0 dès 35 ans dans l\'année' => ['1992-12-31', 'M0'],
            'master 1 à 44 ans' => ['1983-05-05', 'M1'],
            'master 2 à 47 ans' => ['1980-05-05', 'M2'],
            'master 10' => ['1940-02-02', 'M10'],
        ];
    }

    #[DataProvider('ages')]
    public function test_la_categorie_depend_de_l_age_atteint_dans_l_annee(string $naissance, string $attendue): void
    {
        $this->assertSame($attendue, Categorie::pour(Carbon::parse($naissance), Carbon::parse('2027-05-15')));
    }
}
