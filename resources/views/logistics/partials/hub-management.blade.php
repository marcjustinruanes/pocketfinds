<section id="add-hub" class="card card-pad coverage-create">
  <h2>Add municipal hub</h2>
  <p>Create a hub in your company's coverage area. Hiring settings will appear on its hub card.</p>
  <form method="POST" action="{{ route('logistics.hubs.store') }}">
    @csrf
    @include('logistics.partials.hub-address-fields')
    <button type="submit" class="btn btn-primary" data-hub-save disabled>Create hub</button>
  </form>
</section>
