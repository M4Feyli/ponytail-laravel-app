@extends('layouts.shop')

@section('content')
<section class="landing">
  <div class="landing-hero">
    <h1>Utility <em>engineering.</em></h1>
  </div>

  <div class="cat-grid">
    @forelse ($categories as $i => $cat)
      <a class="cat-card cat-card--{{ $i }}" href="{{ route('shop.category', $cat) }}">
        <div class="cat-bg">
          @if ($cat->imageUrl())
            <img src="{{ $cat->imageUrl() }}" alt="{{ $cat->name }}" loading="lazy">
          @endif
        </div>
        <div class="cat-content">
          <div class="cat-top">
            <div class="cat-no">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
            <div class="cat-count">{{ $cat->products_count }} ST</div>
          </div>
          <div class="cat-bottom">
            <div class="cat-name">{{ $cat->name }}</div>
            <div class="cat-cta">SE KOLLEKTION</div>
          </div>
        </div>
      </a>
    @empty
      <div class="empty-cat"><b>INGA KATEGORIER ÄNNU</b>Lägg till kategorier i admin-panelen.</div>
    @endforelse
  </div>
</section>
@endsection
