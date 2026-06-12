<?php

namespace App\Models\Traits;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

trait HasTags
{
    /**
     * Filter models that contain all given tags.
     */
    public function scopeWithAllTags(Builder $query, string|array $tags): Builder
    {
        foreach (static::normalizeTags($tags) as $tag) {
            $query->whereHas('tags', function (Builder $q) use ($tag) {
                $q->where('name', $tag);
            });
        }

        return $query;
    }

    /**
     * Filter models that contain at least one of the given tags.
     */
    public function scopeWithAnyTags(Builder $query, string|array $tags): Builder
    {
        $tags = static::normalizeTags($tags);

        return $query->whereHas('tags', function (Builder $q) use ($tags) {
            $q->whereIn('name', $tags);
        });
    }

    /**
     * Attach a tag to the model.
     */
    public function attachTag(string|Tag $tag): static
    {
        $tag = $tag instanceof Tag
            ? $tag
            : static::findOrCreate($tag);

        $this->tags()->syncWithoutDetaching([$tag->id]);

        return $this;
    }

    /**
     * Attach multiple tags to the model.
     */
    public function attachTags(string|array $tags): static
    {
        $tagIds = static::findOrCreateFromString($tags)
            ->pluck('id')
            ->all();

        $this->tags()->syncWithoutDetaching($tagIds);

        return $this;
    }

    /**
     * Alias for attachTag().
     */
    public function addTag(string|Tag $tag): static
    {
        return $this->attachTag($tag);
    }

    /**
     * Alias for attachTags().
     */
    public function addTags(string|array $tags): static
    {
        return $this->attachTags($tags);
    }

    /**
     * Detach a tag from the model.
     */
    public function detachTag(string|Tag $tag): static
    {
        $tag = $tag instanceof Tag
            ? $tag
            : Tag::query()
                ->where('name', trim($tag))
                ->first();

        if ($tag) {
            $this->tags()->detach($tag->id);
        }

        return $this;
    }

    /**
     * Detach multiple tags from the model.
     */
    public function detachTags(string|array $tags): static
    {
        $tagIds = static::findFromString($tags)
            ->pluck('id')
            ->all();

        if (!empty($tagIds)) {
            $this->tags()->detach($tagIds);
        }

        return $this;
    }

    /**
     * Detach all tags from the model.
     */
    public function detachAllTags(): static
    {
        $this->tags()->detach();

        return $this;
    }

    /**
     * Alias for detachTag().
     */
    public function removeTag(string|Tag $tag): static
    {
        return $this->detachTag($tag);
    }

    /**
     * Alias for detachTags().
     */
    public function removeTags(string|array $tags): static
    {
        return $this->detachTags($tags);
    }

    /**
     * Synchronize model tags.
     */
    public function syncTags(string|array $tags): static
    {
        $tagIds = static::findOrCreateFromString($tags)
            ->pluck('id')
            ->all();

        $this->tags()->sync($tagIds);

        return $this;
    }

    /**
     * Synchronize tags without detaching existing tags.
     */
    public function syncTagsWithoutDetaching(string|array $tags): static
    {
        $tagIds = static::findOrCreateFromString($tags)
            ->pluck('id')
            ->all();

        $this->tags()->syncWithoutDetaching($tagIds);

        return $this;
    }

    /**
     * Synchronize tags by IDs.
     *
     * @param array<int> $tagIds
     */
    public function syncTagsByIds(array $tagIds): static
    {
        $this->tags()->sync($tagIds);

        return $this;
    }

    /**
     * Synchronize tags from Tag models.
     *
     * @param iterable<Tag> $tags
     */
    public function syncTagsFromModels(iterable $tags): static
    {
        $tagIds = collect($tags)
            ->pluck('id')
            ->all();

        $this->tags()->sync($tagIds);

        return $this;
    }

    /**
     * Alias for syncTags().
     */
    public function replaceTags(string|array $tags): static
    {
        return $this->syncTags($tags);
    }

    /**
     * Remove all tags from the model.
     */
    public function clearTags(): static
    {
        return $this->detachAllTags();
    }

    /**
     * Determine whether the model has a tag.
     */
    public function hasTag(string|Tag $tag): bool
    {
        $name = $tag instanceof Tag
            ? $tag->name
            : trim($tag);

        return $this->tags()
            ->where('name', $name)
            ->exists();
    }

    /**
     * Determine whether the model has all given tags.
     */
    public function hasTags(string|array $tags): bool
    {
        foreach (static::normalizeTags($tags) as $tag) {
            if (!$this->hasTag($tag)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the model has at least one of the given tags.
     */
    public function hasAnyTag(string|array $tags): bool
    {
        $tags = static::normalizeTags($tags);

        return $this->tags()
            ->whereIn('name', $tags)
            ->exists();
    }

    /**
     * Get all tag names.
     *
     * @return array<int, string>
     */
    public function getTagNames(): array
    {
        return $this->tags()
            ->pluck('name')
            ->all();
    }

    /**
     * Get tags as a delimited string.
     */
    public function getTagsAsString(string $separator = ', '): string
    {
        return implode(
            $separator,
            $this->getTagNames()
        );
    }

    /**
     * Find existing tags.
     *
     * @return Collection<int, Tag>
     */
    public static function findFromString(string|array $tags): Collection
    {
        return Tag::query()
            ->whereIn('name', static::normalizeTags($tags))
            ->get();
    }

    /**
     * Find or create multiple tags.
     *
     * @return Collection<int, Tag>
     */
    public static function findOrCreateFromString(string|array $tags): Collection
    {
        return collect(
            static::normalizeTags($tags)
        )->map(
                fn(string $tag): Tag => static::findOrCreate($tag)
            );
    }

    /**
     * Find or create a tag.
     */
    public static function findOrCreate(string $tag): Tag
    {
        return Tag::query()->firstOrCreate([
            'name' => trim($tag),
        ]);
    }

    /**
     * Normalize tags.
     *
     * @return array<int, string>
     */
    protected static function normalizeTags(string|array $tags): array
    {
        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn(mixed $tag): string => trim((string) $tag),
                        is_string($tags)
                        ? explode(',', $tags)
                        : $tags
                    )
                )
            )
        );
    }
}
