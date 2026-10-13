<?php

namespace App\Http\Controllers;

use App\Models\SupportFaq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function buyer(Request $request)
    {
        abort_unless($request->user()->account_type === 'buyer' && !$request->user()->is_admin, 403);
        $data = $request->validate(['faq' => ['sometimes', 'integer', 'min:1']]);
        if (isset($data['faq'])) SupportFaq::publishedFor('buyer')->findOrFail($data['faq']);
        return view('buyer.faqs', ['faqs' => SupportFaq::publishedFor('buyer')
            ->when(isset($data['faq']), fn ($query) => $query->where('id', $data['faq']))->paginate(30)->withQueryString()]);
    }

    public function save(Request $request, ?SupportFaq $faq = null)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate([
            'audience' => ['required', 'in:buyer,admin'], 'question' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'answer' => ['required', 'string', 'max:3000', 'regex:/\S/u'], 'keywords_text' => ['nullable', 'string', 'max:500'],
            'is_published' => ['sometimes', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
        $data['keywords'] = collect(explode(',', $data['keywords_text'] ?? ''))->map(fn ($word) => trim(mb_strtolower($word)))
            ->filter()->unique()->take(15)->values()->all();
        unset($data['keywords_text']);
        $data['question'] = trim(strip_tags($data['question']));
        $data['answer'] = trim(strip_tags($data['answer']));
        abort_if($data['question'] === '' || $data['answer'] === '', 422);
        $data['is_published'] = $request->boolean('is_published');
        $data['updated_by'] = (string) $request->user()->id;
        ($faq ?? new SupportFaq())->fill($data)->save();
        return redirect()->route('admin.faqs', ['audience' => $data['audience']])->with('success', 'FAQ saved.');
    }

    public function delete(Request $request, SupportFaq $faq)
    {
        abort_unless($request->user()->is_admin, 403);
        $audience = $faq->audience; $faq->delete();
        return redirect()->route('admin.faqs', ['audience' => $audience])->with('success', 'FAQ deleted.');
    }
}
