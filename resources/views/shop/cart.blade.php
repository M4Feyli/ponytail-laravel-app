@extends('layouts.shop')

@section('title', 'Varukorg — NSVO')

@section('content')
<section class="cart-page">
  <div class="cart-page-head">
    <a class="back-btn" href="{{ route('shop.home') }}" style="margin-bottom:1.5rem;display:inline-flex">← FORTSÄTT HANDLA</a>
    <h1>DIN <em>VARUKORG</em></h1>
    <div class="sub" id="cartPageSub">0 PRODUKTER</div>
  </div>
  <div id="cartContent"></div>
</section>
@endsection
