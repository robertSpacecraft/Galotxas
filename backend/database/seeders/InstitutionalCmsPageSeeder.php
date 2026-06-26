<?php

namespace Database\Seeders;

use App\Enums\CmsBlockType;
use App\Enums\CmsPageStatus;
use App\Models\CmsPage;
use Illuminate\Database\Seeder;

class InstitutionalCmsPageSeeder extends Seeder
{
    /**
     * @var array<int, array{slug: string, title: string, description: string}>
     */
    private array $pages = [
        [
            'slug' => 'prensa-media',
            'title' => 'Prensa & Media',
            'description' => 'Información pública para prensa, comunicación y recursos media de Galotxas.',
        ],
        [
            'slug' => 'nosotros',
            'title' => 'Nosotros',
            'description' => 'Información institucional sobre Galotxas y su actividad deportiva.',
        ],
        [
            'slug' => 'federaciones',
            'title' => 'Federaciones',
            'description' => 'Información sobre relación federativa, seguros y enlaces oficiales.',
        ],
        [
            'slug' => 'academy',
            'title' => 'Academy',
            'description' => 'Información pública sobre aprendizaje, escuela y actividades formativas.',
        ],
        [
            'slug' => 'documentos',
            'title' => 'Documentos',
            'description' => 'Normativa, documentos públicos y recursos informativos de Galotxas.',
        ],
        [
            'slug' => 'federarse',
            'title' => 'Federarse',
            'description' => 'Información básica para personas interesadas en federarse.',
        ],
    ];

    public function run(): void
    {
        foreach ($this->pages as $pageData) {
            $page = CmsPage::query()->firstOrCreate(
                ['slug' => $pageData['slug']],
                [
                    'title' => $pageData['title'],
                    'status' => CmsPageStatus::PUBLISHED->value,
                    'published_at' => now(),
                    'seo_title' => $pageData['title'],
                    'seo_description' => $pageData['description'],
                ]
            );

            if (!$page->wasRecentlyCreated) {
                continue;
            }

            $page->blocks()->createMany([
                [
                    'type' => CmsBlockType::HEADING->value,
                    'sort_order' => 10,
                    'data' => [
                        'text' => $pageData['title'],
                        'level' => 2,
                    ],
                ],
                [
                    'type' => CmsBlockType::TEXT->value,
                    'sort_order' => 20,
                    'data' => [
                        'text' => $pageData['description'],
                    ],
                ],
            ]);
        }
    }
}
