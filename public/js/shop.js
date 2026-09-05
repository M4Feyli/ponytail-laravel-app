/* ============ HELPERS ============ */
const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => [...c.querySelectorAll(s)];
const fmt = v => new Intl.NumberFormat('sv-SE').format(v) + ' KR';
const CART_KEY = 'nsvo_cart';

/* ============ CART STATE ============ */
let cart = JSON.parse(localStorage.getItem(CART_KEY) || '[]');

function saveCart() {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    updateCartCount();
}

function updateCartCount() {
    const el = $('#cartCount');
    if (!el) return;
    const count = cart.reduce((a, c) => a + c.qty, 0);
    el.textContent = count;
    el.classList.toggle('empty', count === 0);
}

/* ============ PRODUCT SORT (category page) ============ */
let currentSort = 'default';

function renderProducts() {
    const grid = $('#products');
    if (!grid || typeof CATEGORY_PRODUCTS === 'undefined') return;

    let list = [...CATEGORY_PRODUCTS];
    if (currentSort === 'price-asc') list.sort((a, b) => a.price - b.price);
    else if (currentSort === 'price-desc') list.sort((a, b) => b.price - a.price);
    else if (currentSort === 'new') list.sort((a, b) => (b.isNew ? 1 : 0) - (a.isNew ? 1 : 0));

    const countEl = $('#prodCount');
    if (countEl) countEl.textContent = list.length;

    if (!list.length) {
        grid.innerHTML = '<div class="empty-cat"><b>INGA VAROR ÄNNU</b>Kika tillbaka snart.</div>';
        return;
    }

    grid.innerHTML = list.map(p => {
        const sizeArr = p.sizes.slice(0, 5);
        const sizeHtml = sizeArr.map(s =>
            `<span class="${s.stock === 0 ? 'out' : ''}">${s.size}</span>`
        ).join('') + (p.sizes.length > 5 ? `<span>+${p.sizes.length - 5}</span>` : '');

        const tags = [];
        if (p.isNew) tags.push('<span class="tag new">NY</span>');
        if (p.old) tags.push('<span class="tag low">REA</span>');
        const lowStock = p.sizes.filter(s => s.stock > 0 && s.stock <= 2).length;
        if (lowStock) tags.push('<span class="tag">LÅG LAGER</span>');

        return `
      <div class="card" data-id="${p.id}">
        <div class="card-tags">${tags.join('')}</div>
        <div class="card-sku">${p.sku}</div>
        <div class="card-media"><img src="${p.img}" alt="${p.name}" loading="lazy"></div>
        <div class="card-body">
          <div class="card-cat">${p.cat}</div>
          <div class="card-name">${p.name}</div>
          ${p.condition ? `<div class="card-condition">${p.condition}</div>` : ''}
          <div class="card-meta">
            <div class="card-price">${p.old ? `<s>${fmt(p.old)}</s>` : ''}${fmt(p.price)}</div>
            <div class="card-sizes">${sizeHtml}</div>
          </div>
        </div>
      </div>`;
    }).join('');

    $$('.card', grid).forEach(card => {
        card.addEventListener('click', () => openModal(card.dataset.id));
    });
}

/* ============ MODAL (quick view + add to cart) ============ */
let currentProduct = null;
let selectedSize = null;

function openModal(id) {
    if (typeof CATEGORY_PRODUCTS === 'undefined') return;
    const p = CATEGORY_PRODUCTS.find(x => String(x.id) === String(id));
    if (!p) return;

    currentProduct = p;
    selectedSize = null;

    $('#mImg').src = p.img;
    $('#mImg').alt = p.name;
    $('#mSku').textContent = 'SKU · ' + p.sku;
    $('#mCat').textContent = p.cat;
    $('#mName').textContent = p.name;
    $('#mCondition').textContent = p.condition ? 'SKICK · ' + p.condition : '';
    $('#mPrice').textContent = fmt(p.price);
    $('#mOld').textContent = p.old ? fmt(p.old) : '';
    $('#mOld').style.display = p.old ? 'inline' : 'none';
    $('#mDesc').textContent = p.desc || '';

    const specsHtml = Object.entries(p.specs || {}).map(([k, v]) => `<div><b>${k}</b><span>${v}</span></div>`).join('');
    $('#mSpecs').innerHTML = specsHtml;

    const sizesHtml = p.sizes.map(s => {
        const low = s.stock > 0 && s.stock <= 2;
        return `<button data-size="${s.size}" ${s.stock === 0 ? 'disabled' : ''} class="${low ? 'low' : ''}">${s.size}</button>`;
    }).join('');
    $('#mSizes').innerHTML = sizesHtml;
    $('#sizeErr').textContent = '';

    $$('#mSizes button').forEach(btn => {
        btn.addEventListener('click', () => {
            $$('#mSizes button').forEach(x => x.classList.remove('selected'));
            btn.classList.add('selected');
            selectedSize = btn.dataset.size;
            $('#sizeErr').textContent = '';
        });
    });

    $('#addBtn').classList.remove('added');
    $('#addBtn').textContent = '+ LÄGG I VARUKORG';
    $('#modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    $('#modal').classList.remove('open');
    document.body.style.overflow = '';
}

if ($('#closeModal')) {
    $('#closeModal').addEventListener('click', closeModal);
    $('#modal').addEventListener('click', e => { if (e.target.id === 'modal') closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    $('#addBtn').addEventListener('click', () => {
        if (!selectedSize) { $('#sizeErr').textContent = '⚠ VÄLJ STORLEK FÖRST'; return; }
        const key = currentProduct.id + '_' + selectedSize;
        const existing = cart.find(x => x.key === key);
        if (existing) { existing.qty++; }
        else {
            cart.push({
                key, id: currentProduct.id, size: selectedSize, name: currentProduct.name,
                price: currentProduct.price, img: currentProduct.img, sku: currentProduct.sku,
                cat: currentProduct.cat, qty: 1,
            });
        }
        saveCart();
        $('#addBtn').classList.add('added');
        $('#addBtn').innerHTML = '✓ TILLAGD · ' + selectedSize;
        showToast();
        setTimeout(closeModal, 900);
    });

    $('#sizeGuide')?.addEventListener('click', () => {
        alert('STORLEKSGUIDE\n\nKLÄDER & JACKOR: S/M/L/XL/XXL\nSKOR: EU 36-45\nBÄLTEN: 85/90/95/100/105 (midjemått cm)\nVÄSKOR & KEPSAR: One size\n\nMät bröst, midja och höfter.');
    });
}

/* ============ CART PAGE ============ */
function renderCart() {
    const content = $('#cartContent');
    if (!content) return;

    const count = cart.reduce((a, c) => a + c.qty, 0);
    const sub = $('#cartPageSub');
    if (sub) sub.textContent = count + (count === 1 ? ' PRODUKT' : ' PRODUKTER');

    if (!cart.length) {
        content.innerHTML = `
      <div class="cart-empty">
        <b>DIN VARUKORG ÄR TOM</b>
        <p>Lägg till produkter för att fortsätta till kassan</p>
        <a href="/" class="continue-btn" style="max-width:300px;margin:0 auto;display:block;text-align:center">← FORTSÄTT HANDLA</a>
      </div>`;
        return;
    }

    const subtotal = cart.reduce((a, c) => a + c.price * c.qty, 0);
    const ship = subtotal >= 999 ? 0 : 69;
    const grand = subtotal + ship;

    content.innerHTML = `
    <div class="cart-layout">
      <div class="cart-items-list">
        ${cart.map(c => `
          <div class="cart-item" data-key="${c.key}">
            <img src="${c.img}" alt="${c.name}">
            <div class="info">
              <h4>${c.name}</h4>
              <div class="ci-meta">
                <span>${c.cat} · ${c.sku}</span>
                <span>STORLEK: ${c.size}</span>
              </div>
              <div class="ci-price">${fmt(c.price * c.qty)}</div>
            </div>
            <div class="actions">
              <div class="qty-ctrl">
                <button data-act="dec">−</button>
                <span>${c.qty}</span>
                <button data-act="inc">+</button>
              </div>
              <button class="rm" data-act="rm">TA BORT</button>
            </div>
          </div>
        `).join('')}
      </div>
      <div class="cart-summary">
        <h3>ORDERÖVERSIKT</h3>
        <div class="cart-totals">
          <div class="sub"><span>DELSUMMA</span><span>${fmt(subtotal)}</span></div>
          <div class="sub"><span>FRAKT</span><span>${ship === 0 ? 'GRATIS' : fmt(ship)}</span></div>
          <div class="sub"><span>MOMS (25%)</span><span>INGÅR</span></div>
          <div class="grand"><span>TOTALT</span><b>${fmt(grand)}</b></div>
        </div>
        <div class="cart-actions">
          <button class="checkout-btn" id="checkoutBtn">→ TILL KASSAN</button>
          <a href="/" class="continue-btn" style="display:block;text-align:center">← FORTSÄTT HANDLA</a>
        </div>
        <div class="cart-note">30 DAGARS RETUR<br>FRI FRAKT ÖVER 999 KR</div>
      </div>
    </div>`;

    $$('.cart-item').forEach(el => {
        const key = el.dataset.key;
        el.querySelectorAll('button').forEach(b => {
            b.addEventListener('click', () => {
                const item = cart.find(x => x.key === key);
                if (!item) return;
                const act = b.dataset.act;
                if (act === 'inc') item.qty++;
                else if (act === 'dec') { item.qty--; if (item.qty <= 0) cart = cart.filter(x => x.key !== key); }
                else if (act === 'rm') cart = cart.filter(x => x.key !== key);
                saveCart();
                renderCart();
            });
        });
    });

    $('#checkoutBtn').addEventListener('click', () => {
        // Betalsystem inte kopplat ännu -- se render.yaml/README.
        alert('🔒 KASSAN\n\nBetalning är inte påkopplad ännu.\n\nTotalt: ' + fmt(grand) + '\nAntal produkter: ' + count);
    });
}

/* ============ TOAST ============ */
function showToast() {
    const t = $('#toast');
    if (!t) return;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2000);
}

/* ============ INIT ============ */
document.addEventListener('DOMContentLoaded', () => {
    updateCartCount();
    renderProducts();
    renderCart();

    const sortSel = $('#sortSel');
    if (sortSel) sortSel.addEventListener('change', e => { currentSort = e.target.value; renderProducts(); });

    const track = $('.ticker-track');
    if (track) track.innerHTML += track.innerHTML;
});
