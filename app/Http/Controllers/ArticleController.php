<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale  = $this->resolveLocale($request);
        $perPage = (int) $request->input('per_page', 12);

        // Load both the requested locale and EN so we can fall back to EN
        // when no translation exists for the requested locale.
        $locales = array_unique([$locale, 'en']);

        $query = Article::with(['translations' => fn ($q) => $q->whereIn('locale', $locales)])
            ->where('is_published', true)
            ->orderByDesc('published_at');

        if ($request->filled('category')) {
            $query->whereHas('translations', function ($q) use ($request, $locale) {
                $q->where('locale', $locale)->where('category', $request->category);
            });
        }

        $paginated = $query->paginate($perPage);

        $data = $paginated->map(fn ($a) => $this->formatArticleList($a, $locale));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale  = $this->resolveLocale($request);
        $locales = array_unique([$locale, 'en']);

        $article = Article::with(['translations' => fn ($q) => $q->whereIn('locale', $locales)])
            ->where('is_published', true)
            ->where('slug', $slug)
            ->firstOrFail();

        // Prefer requested locale; fall back to EN
        $translation = $article->translations->firstWhere('locale', $locale)
            ?? $article->translations->firstWhere('locale', 'en');

        return response()->json([
            'data' => [
                'id'           => $article->id,
                'slug'         => $article->slug,
                'image'        => $article->image ? url('storage/' . $article->image) : null,
                'published_at' => $article->published_at?->toDateString(),
                'category'     => $translation?->category,
                'title'        => $translation?->title,
                'read_time'    => $translation?->read_time,
                'summary'      => $translation?->summary,
                'body'         => $translation?->body ?? [],
                'locale'       => $locale,
            ],
        ]);
    }

    private function formatArticleList(Article $a, string $locale): array
    {
        // Prefer requested locale; fall back to EN
        $t = $a->translations->firstWhere('locale', $locale)
            ?? $a->translations->firstWhere('locale', 'en');

        return [
            'id'           => $a->id,
            'slug'         => $a->slug,
            'image'        => $a->image ? url('storage/' . $a->image) : null,
            'published_at' => $a->published_at?->toDateString(),
            'category'     => $t?->category,
            'title'        => $t?->title,
            'read_time'    => $t?->read_time,
            'summary'      => $t?->summary,
            'locale'       => $locale,
        ];
    }

    private function resolveLocale(Request $request): string
    {
        return in_array($request->query('locale'), ['en', 'de', 'fr', 'es'])
            ? $request->query('locale')
            : 'en';
    }
}
