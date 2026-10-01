<?php

namespace App\Support;

/**
 * One Landing Page as the registry describes it: what a search result shows,
 * what the page is about, and which questions it answers.
 *
 * The body copy is not here. It lives in `resources/views/landing/{slug}`,
 * because it is prose with prices interpolated into it, and Blade is where
 * this site already writes that.
 */
final readonly class LandingPage
{
    /**
     * @param  array<int, string>  $related  Slugs of sibling pages, most relevant first.
     * @param  array<int, string|array{id: string, question: string, answer: string}>  $faq
     *                                                                                       An existing Faq id to quote as written, or a question of this page's own.
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $h1,
        public string $keyword,
        public array $related,
        public array $faq,
        public bool $published,
    ) {}

    /**
     * This page as if a person had published it, for the test seam in
     * `site.landing_pages.publish`.
     */
    public function asPublished(): self
    {
        return clone ($this, ['published' => true]);
    }

    public function routeName(): string
    {
        return 'landing.'.$this->slug;
    }

    public function url(): string
    {
        return route($this->routeName());
    }

    public function view(): string
    {
        return 'landing.'.$this->slug;
    }

    public function isHub(): bool
    {
        return $this->slug === LandingPages::HUB;
    }

    /**
     * The questions as the page renders them, with every reused id resolved
     * to the answer the FAQ page gives, word for word.
     *
     * @return array<int, array{id: string, question: string, answer: string}>
     */
    public function faq(): array
    {
        return array_map(
            fn (string|array $entry): array => is_string($entry) ? Faq::find($entry) : $entry,
            $this->faq,
        );
    }
}
