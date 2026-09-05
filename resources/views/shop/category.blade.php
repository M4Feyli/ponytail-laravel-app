@extends('layouts.shop')

@section('title', $category->name . ' — NSVO')

@section('content')
<section class="cat-view">
  <div class="back-bar">
    <a class="back-btn" href="{{ route('shop.home') }}">ALLA KATEGORIER</a>
    <div class="cat-title">{{ $category->name }} <em>·</em> {{ $category->description }}</div>
  </div>
  <div class="filterbar">
    <div class="filter-info"><b id="prodCount">{{ $products->count() }}</b> PRODUKTER</div>
    <div class="filter-sort">SORTERA:
      <select id="sortSel">
        <option value="default">RELEVANS</option>
        <option value="price-asc">PRIS ↑</option>
        <option value="price-desc">PRIS ↓</option>
        <option value="new">NYHETER</option>
      </select>
    </div>
  </div>
  <div class="products" id="products"></div>
</section>

@push('scripts')
<script>
  const CATEGORY_PRODUCTS = @json($productsJson);
</script>
@endpush
@endsection
