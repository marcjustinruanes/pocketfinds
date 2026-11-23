<?php
// Apply the same narrow chat compatibility fix after refreshing the pinned runtime.
$base = $argv[1] ?? dirname(__DIR__).'/api/storage/buyer-website';
$file = $base.'/app/Http/Controllers/BuyerController.php';
$source = file_get_contents($file);
if (!str_contains($source, 'function buyerChatContact(')) {
    $helper = <<<'PHP'
    private function buyerChatContact(int $id): User
    {
        $contact = User::findOrFail($id);
        $hasThread = Message::where(function ($q) use ($id) {
            $q->where('sender_id', auth()->id())->where('receiver_id', $id);
        })->orWhere(function ($q) use ($id) {
            $q->where('sender_id', $id)->where('receiver_id', auth()->id());
        })->exists();
        abort_unless($contact->account_type === 'seller' ||
            ($contact->account_type === 'rider' && $hasThread), 404);
        return $contact;
    }

PHP;
    $source = str_replace('    public function messages(Request $request)', $helper."\n    public function messages(Request \$request)", $source);
    $source = str_replace("User::whereKey(\$data['receiver_id'])->where('account_type', 'seller')->firstOrFail()", "\$this->buyerChatContact((int) \$data['receiver_id'])", $source);
    $source = str_replace("\$seller = User::where('username', \$request->query('seller'))->where('account_type', 'seller')->first();", "\$contact = User::where('username', \$request->query('seller'))->first();\n            \$seller = \$contact ? \$this->buyerChatContact((int) \$contact->id) : null;", $source);
    $source = str_replace("\$contact->account_type === 'seller' && filled(\$contact->username)", "in_array(\$contact->account_type, ['seller', 'rider'], true) && filled(\$contact->username)", $source);
    $source = str_replace('// Only seller conversations can be opened by the buyer messaging routes.', '// Buyer conversations include sellers and riders who have messaged this buyer.', $source);
    file_put_contents($file, $source);
}
$view = $base.'/resources/views/buyer/partials/messages-panel.blade.php';
$html = file_get_contents($view);
if (!str_contains($html, "@if(\$seller->account_type === 'seller')")) {
    $html = str_replace('<a href="{{ route(\'guest.shop\', $seller->username) }}">View shop</a>', '@if($seller->account_type === \'seller\')<a href="{{ route(\'guest.shop\', $seller->username) }}">View shop</a>@endif', $html);
}
file_put_contents($view, $html);
