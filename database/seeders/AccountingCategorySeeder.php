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
     * Each root maps to its ordered children (name => optional hint comment). The hint
     * is shown to the AI categorizer to nudge it toward the right leaf; direction is set
     * on the root and inherited by every child.
     *
     * @var array<string, array<string, array<string, ?string>>>
     */
    private array $tree = [
        'income' => [
            'Beiträge' => [
                'Elternbeitrag' => "Stichwort im Zweck: 'Elternbeitrag' oder 'Elternentgelt'.",
                'Essensgeld' => "Verpflegungsbeitrag. Stichwort im Zweck: 'Essensgeld' oder 'Essen'.",
                'Kaution' => "Einmalige Kaution. Stichwort im Zweck: 'Kaution'.",
                'Vereinsbeitrag' => "Mitgliedsbeitrag im Trägerverein, nicht das Elternentgelt. Stichwort im Zweck: 'Vereinsbeitrag'.",
                'Beitrag für Hortfreizeit' => "Stichwort im Zweck: 'Hortfreizeit'.",
            ],
            'Erträge' => [
                'EKI Förderung' => null,
                'Baykibig' => null,
                'Untervermietung' => null,
                'Ekiplus' => null,
            ],
        ],
        'expense' => [
            'Konsumgüter' => [
                'Ausflüge' => null,
                'Basteln' => null,
                'Zeitschriften Abo' => null,
                'Lebensmittel' => null,
                'Hortausstattung' => null,
                'Hortfreizeit' => null,
                'Drogerie' => null,
                'Büromaterial' => null,
            ],
            'Personalkosten' => [
                'Krankenkasse' => null,
                'Altersversorgung' => null,
                'Gehalt' => null,
                'Lohnsteuer' => null,
                'Fortbildung' => null,
                'Lohnbuchhaltung' => null,
            ],
            'Raumkosten' => [
                'Reinigung' => null,
                'Miete' => null,
                'GEZ' => null,
                'Strom' => null,
                'Handwerker und Prüfungen' => null,
            ],
            'Kommunikation' => [
                'Internet' => null,
                'Telefon' => null,
            ],
            'Versicherung' => [
                'Gewerbeversicherung' => null,
                'Rechtsschutz' => null,
                'D&O' => null,
                'Haftpflicht' => null,
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

                foreach ($children as $childName => $comment) {
                    Category::create([
                        'name' => $childName,
                        'parent_id' => $root->id,
                        'direction' => $root->direction,
                        'comment' => $comment,
                        'position' => ++$childPosition,
                    ]);
                }
            }
        }
    }
}
