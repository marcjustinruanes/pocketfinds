<?php

namespace App\Services;

use App\Models\SupportFaq;
use Illuminate\Support\Facades\Schema;

class FaqKnowledge
{
    public function retrieve(string $audience, string $question): array
    {
        if (!Schema::hasTable('support_faqs')) return [];
        $tokens = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)))->filter(fn ($token) => mb_strlen($token) > 2)->unique()->take(15);
        return SupportFaq::publishedFor($audience)->get()->sortByDesc(fn ($faq) => $tokens->sum(fn ($token) =>
            str_contains(mb_strtolower($faq->question.' '.$faq->answer.' '.implode(' ', $faq->keywords ?? [])), $token) ? 1 : 0))
            ->take(6)->map(fn ($faq) => ['id' => $faq->id, 'question' => $faq->question, 'answer' => $faq->answer])->values()->all();
    }

    public function match(string $audience, string $message): ?SupportFaq
    {
        if (!Schema::hasTable('support_faqs')) return null;
        $normalize = fn ($text) => trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)), " \t\r\n?!.");
        $question = $normalize($message);
        $faqs = SupportFaq::publishedFor($audience)->get();
        foreach ($faqs as $faq) if ($normalize($faq->question) === $question) return $faq;
        if (preg_match('/\b(compare|versus|vs|recommend|better|paghambingin)\b/iu', $message)) return null;
        foreach ($faqs as $faq) foreach ($faq->keywords ?? [] as $keyword) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/iu', $message)) return $faq;
        }
        return null;
    }
}
