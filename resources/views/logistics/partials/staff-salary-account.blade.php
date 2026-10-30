<section class="card" id="salary-receiving" aria-label="Salary receiving account">
  <div class="card-head"><div><h2>Salary GCash account</h2><p>Only you can save these details. Existing payment instructions retain their original receiving account.</p></div></div>
  <div class="card-pad">
    @forelse($salaryDestinations as $destination)
      <p>{{ $destination->account_name }} / GCash {{ '*******'.substr($destination->account_number,-4) }}</p>
    @empty
      <p>Add your GCash details before your Admin prepares salary payments.</p>
    @endforelse
    <details><summary>Add or update receiving account</summary>
      <form method="POST" action="{{ route('logistics.my-salary.destination') }}" onsubmit="return confirm('Save these GCash details? Existing payment instructions retain their original account.');">
        @csrf
        @include('logistics.partials.finance-account-fields',['prefix'=>'staff-salary-account'])
        <label class="salary-account-confirmation"><input type="checkbox" name="confirmed" value="1" required><span>I checked the account name and GCash number and confirm they belong to me.</span></label>
        <button class="btn btn-primary">Save receiving account</button>
      </form>
    </details>
    <p><a href="{{ route('logistics.my-salary') }}">View My Salary and payment history</a></p>
  </div>
</section>
