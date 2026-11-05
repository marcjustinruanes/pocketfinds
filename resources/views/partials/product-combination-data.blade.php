@if($combinationGroup)
    <script type="application/json" id="pdCombinationData">@json($combinationGroup)</script>
    <script defer src="{{ asset('js/product-combinations.js') }}?v={{ filemtime(public_path('js/product-combinations.js')) }}"></script>
@endif
