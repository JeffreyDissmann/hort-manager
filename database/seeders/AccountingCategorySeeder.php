<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CategoryDirection;
use App\Models\Accounting\Category;
use Illuminate\Database\Seeder;

/** The Hort's real booking-category tree (income + expense). */
class AccountingCategorySeeder extends Seeder
{
    /**
     * Each root maps to its ordered children. Direction is set on the root and
     * inherited by every child.
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $tree = [
        'income' => [
            'Elternbeitrag' => [
                'Elternbeitrag',
                'Essensgeld',
                'Kaution',
                'Vereinsbeitrag',
                'Beitrag für Hortfreizeit',
            ],
            'Erträge' => [
                'EKI Förderung',
                'Baykibig',
                'Untervermietung',
                'Ekiplus',
            ],
        ],
        'expense' => [
            'Konsumgüter' => [
                'Ausflüge',
                'Basteln',
                'Zeitschriften Abo',
                'Lebensmittel',
                'Hortausstattung',
                'Hortfreizeit',
                'Drogerie',
                'Büromaterial',
            ],
            'Personalkosten' => [
                'Krankenkasse',
                'Altersversorgung',
                'Gehalt',
                'Lohnsteuer',
                'Fortbildung',
                'Lohnbuchhaltung',
            ],
            'Raumkosten' => [
                'Reinigung',
                'Miete',
                'GEZ',
                'Strom',
                'Handwerker und Prüfungen',
            ],
            'Kommunikation' => [
                'Internet',
                'Telefon',
            ],
            'Versicherung' => [
                'Gewerbeversicherung',
                'Rechtsschutz',
                'D&O',
                'Haftpflicht',
            ],
            'Bankgebühren' => [],
            'Investitionen in den Hort' => [],
            'KKT' => [],
            'Justizkase' => [],
        ],
    ];

    public function run(): void
    {
        foreach ($this->tree as $direction => $roots) {
            $rootPosition = 0;

            foreach ($roots as $rootName => $children) {
                $root = Category::create([
                    'name' => $rootName,
                    'direction' => CategoryDirection::from($direction),
                    'position' => ++$rootPosition,
                ]);

                $childPosition = 0;

                foreach ($children as $childName) {
                    Category::create([
                        'name' => $childName,
                        'parent_id' => $root->id,
                        'direction' => $root->direction,
                        'position' => ++$childPosition,
                    ]);
                }
            }
        }
    }
}
