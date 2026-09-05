<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'NSVO — Technical Streetwear')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=JetBrains+Mono:wght@400;500;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/shop.css') }}">
</head>
<body>

<div class="ticker"><div class="ticker-track"><span>
    <b>NSVO</b> SVERIGES BÄSTA SIDA FÖR STREETWEAR ◆
    <b>12 DAGARS FRAKT</b> ÖVER HELA NORDEN ◆
    VARJE PLAGG SKICKBEDÖMT INNAN DET LÄGGS UPP ◆
    <b>{{ \App\Models\Product::where('is_active', true)->count() }}</b> VAROR I LAGER ◆
</span></div></div>

<header>
  <div class="nav">
    <a class="brand" href="{{ route('shop.home') }}"><span class="brand-mark">N</span><span>NSVO<small>TECH · STREETWEAR</small></span></a>
    <div></div>
    <div class="nav-right">
      <a class="cart-btn" href="{{ route('shop.cart') }}">CART <span class="count empty" id="cartCount">0</span></a>
    </div>
  </div>
</header>

@yield('content')

<div class="modal" id="modal">
  <div class="modal-card">
    <button class="modal-close" id="closeModal">✕</button>
    <div class="modal-img"><img id="mImg" src="" alt=""></div>
    <div class="modal-body">
      <div class="sku" id="mSku"></div>
      <div class="cat" id="mCat"></div>
      <h2 id="mName"></h2>
      <div class="condition" id="mCondition"></div>
      <div class="price-row"><span class="p" id="mPrice"></span><s id="mOld"></s></div>
      <p class="desc" id="mDesc"></p>
      <div class="specs" id="mSpecs"></div>
      <div class="size-block">
        <div class="size-head"><span class="lbl">STORLEK</span><button id="sizeGuide">SIZE GUIDE</button></div>
        <div class="sizes" id="mSizes"></div>
        <div class="size-error" id="sizeErr"></div>
      </div>
      <button class="add-btn" id="addBtn">+ LÄGG I VARUKORG</button>
    </div>
  </div>
</div>

<footer>
  <div class="foot">
    <div>
      <h4>NSVO / STOCKHOLM</h4>
      <p>Begagnad teknisk streetwear & utility-gear. Varje plagg är skickbedömt innan det läggs upp.</p>
      <p style="margin-top:1rem">Sveavägen 88 · 113 59 Stockholm<br>hej@nsvo.se · +46 8 555 01 23</p>
    </div>
    <div>
      <h4>KATEGORIER</h4>
      @foreach (\App\Models\Category::orderBy('sort_order')->get() as $footerCat)
        <a href="{{ route('shop.category', $footerCat) }}">{{ $footerCat->name }}</a>
      @endforeach
    </div>
    <div><h4>INFO</h4><a href="#">Storleksguide</a><a href="#">Frakt & retur</a><a href="#">Om NSVO</a><a href="#">Kontakt</a></div>
    <div><h4>FÖLJ</h4><a href="#">Instagram</a><a href="#">TikTok</a><a href="#">Newsletter</a></div>
  </div>
  <div class="foot-bottom"><span>© {{ date('Y') }} NSVO</span><span>INTEGRITET · VILLKOR · COOKIES</span></div>
  <div class="foot-big" aria-hidden="true">NSVO<em>.</em></div>
</footer>

<div class="toast" id="toast">✓ TILLAGD I VARUKORG</div>

<script src="{{ asset('js/shop.js') }}"></script>
@stack('scripts')
</body>
</html>
