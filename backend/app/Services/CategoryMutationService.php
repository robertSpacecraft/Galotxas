<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Championship;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CategoryMutationService
{
    public function __construct(private readonly CompetitionImageService $covers) {}

    public function create(Championship $championship, array $attributes, ?UploadedFile $image = null): Category
    {
        return $this->persist(new Category(['championship_id' => $championship->id]), $attributes, $image, false);
    }

    public function update(Category $category, array $attributes, ?UploadedFile $image = null, bool $removeImage = false): Category
    {
        return $this->persist($category, $attributes, $image, $removeImage);
    }

    private function persist(Category $category, array $attributes, ?UploadedFile $image, bool $removeImage): Category
    {
        return $this->covers->mutate($image, $removeImage, function (?string $newKey, callable $obsolete) use ($category, $attributes, $removeImage): Category {
            $category = $category->exists
                ? Category::query()->lockForUpdate()->findOrFail($category->getKey())
                : clone $category;

            $category->fill([
                'name' => $attributes['name'],
                'slug' => Str::slug($attributes['name']),
                'description' => $attributes['description'] ?? null,
                'level' => $attributes['level'] ?? null,
                'gender' => $attributes['gender'],
                'status' => $attributes['status'],
            ]);
            $category->is_public = (bool) $attributes['is_public'];
            $this->covers->saveWithImage($category, $newKey, $removeImage, $obsolete);

            return $category->refresh();
        });
    }
}
