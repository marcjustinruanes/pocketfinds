@extends('seller.layout')
@section('title', 'My Account')
@section('page-title', 'My Account')
@section('page-sub', 'Manage your seller profile and settings')

@section('content')
<div class="dash-grid">
  <div class="stack">
    {{-- Profile --}}
    <div class="card">
      <div class="card-head"><div><h2>Profile Information</h2><p>Update your personal details</p></div></div>
      <div class="card-pad">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px">
          <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(145deg,var(--pink),#7a2a56);display:grid;place-items:center;color:#fff;font-weight:700;font-size:22px;flex:none">
            {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
          </div>
          <div>
            <div style="font-size:16px;font-weight:700">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</div>
            <div style="font-size:12px;color:var(--muted)">{{ auth()->user()->email }}</div>
            <span class="stamp stamp-active" style="margin-top:4px">Seller</span>
          </div>
        </div>
        <form>
          <div class="form-grid-2">
            <div class="form-row"><label>First Name</label><input type="text" value="{{ auth()->user()->first_name }}"></div>
            <div class="form-row"><label>Last Name</label><input type="text" value="{{ auth()->user()->last_name }}"></div>
          </div>
          <div class="form-row"><label>Email Address</label><input type="email" value="{{ auth()->user()->email }}"></div>
          <div class="form-row"><label>Phone Number</label><input type="text" placeholder="+63 9XX XXX XXXX"></div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    {{-- Shop info --}}
    <div class="card">
      <div class="card-head"><div><h2>Shop Information</h2><p>Your store details visible to buyers</p></div></div>
      <div class="card-pad">
        <form>
          <div class="form-row"><label>Shop Name</label><input type="text" placeholder="Your Shop Name"></div>
          <div class="form-row"><label>Shop Description</label><textarea rows="3" placeholder="Tell buyers about your shop…"></textarea></div>
          <div class="form-row"><label>Shop Category</label>
            <select><option>Food & Drinks</option><option>Clothing</option><option>Beauty</option><option>Electronics</option><option>Home & Living</option><option>Hobbies</option></select>
          </div>
          <div class="form-row"><label>Business Address</label><input type="text" placeholder="Street, Barangay, City, Province"></div>
          <button type="submit" class="btn btn-primary">Update Shop</button>
        </form>
      </div>
    </div>
  </div>

  <div class="stack">
    {{-- Change password --}}
    <div class="card">
      <div class="card-head"><div><h2>Change Password</h2></div></div>
      <div class="card-pad">
        <form>
          <div class="form-row"><label>Current Password</label><input type="password" placeholder="••••••••"></div>
          <div class="form-row"><label>New Password</label><input type="password" placeholder="••••••••"></div>
          <div class="form-row"><label>Confirm New Password</label><input type="password" placeholder="••••••••"></div>
          <button type="submit" class="btn btn-outline">Update Password</button>
        </form>
      </div>
    </div>

    {{-- Account stats --}}
    <div class="card">
      <div class="card-head"><h2>Account Overview</h2></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
        @foreach([['Account Type','Seller'],['Status','Active'],['Member Since',auth()->user()->created_at?->format('M Y') ?? '—'],['Total Products','0'],['Total Orders','0'],['Avg. Rating','—']] as [$label,$val])
        <div style="display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid var(--border)">
          <span style="color:var(--muted)">{{ $label }}</span>
          <span style="font-weight:600">{{ $val }}</span>
        </div>
        @endforeach
      </div>
    </div>

    {{-- Danger zone --}}
    <div class="card" style="border-color:var(--danger-line)">
      <div class="card-head" style="border-color:var(--danger-line)"><div><h2 style="color:var(--danger)">Danger Zone</h2><p>Irreversible actions</p></div></div>
      <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
        <button class="btn btn-danger btn-block">Deactivate Shop</button>
        <button class="btn btn-danger btn-block" data-logout>@include('seller.partials.icon', ['name' => 'logout', 'size' => 14]) Sign Out</button>
      </div>
    </div>
  </div>
</div>
@endsection
