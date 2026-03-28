<?php
/**
 * Snippet: TennisPro Products Grid
 * Shortcode: [tennispro_products_grid]
 * Description: Product listing from products.json with AJAX add-to-cart and product detail modal.
 */

add_shortcode( 'tennispro_products_grid', function () {
    ob_start();

    $products = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];
    ?>
    <h1>Services &amp; Products</h1>
    <p>Browse our curated selection of coaching packages, premium equipment, and court reservations.</p>

    <div class="pill-row">
        <span class="pill pill-soft">Coaching</span>
        <span class="pill pill-soft">Equipment</span>
        <span class="pill pill-soft">Court booking</span>
    </div>

    <div id="cart-toast" class="alert alert-success" style="display:none;position:fixed;top:80px;right:20px;z-index:9999;padding:12px 24px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);transition:opacity .3s;"></div>

    <?php if ( empty( $products ) ) : ?>
        <p>No products available.</p>
    <?php else : ?>
        <div class="products-grid">
            <?php foreach ( $products as $p ) : ?>
                <?php
                $img = $p['image_path'] ?? $p['image'] ?? '';
                if ( $img && strpos( $img, 'http' ) !== 0 && strpos( $img, '//' ) !== 0 ) {
                    if ( strpos( $img, 'images/' ) === 0 ) {
                        $img = 'image/' . substr( $img, strlen( 'images/' ) );
                    }
                    $img = get_stylesheet_directory_uri() . '/' . ltrim( $img, '/' );
                }
                $id          = (int) ( $p['id'] ?? 0 );
                $name        = $p['name'] ?? '';
                $price       = (float) ( $p['price'] ?? 0 );
                $description = $p['description'] ?? '';
                ?>
                <div class="product-card">
                    <?php if ( $img ) : ?>
                        <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $name ); ?>">
                    <?php endif; ?>
                    <div class="product-name"><?php echo esc_html( $name ); ?></div>
                    <div class="product-price">$<?php echo number_format( $price, 2 ); ?></div>
                    <div class="product-desc"><?php echo esc_html( $description ); ?></div>
                    <div class="product-actions">
                        <div>
                            <div class="qty-control" aria-label="Quantity selector">
                                <button type="button" class="qty-btn" onclick="tennisproQtyStep(this, -1)" aria-label="Decrease quantity">−</button>
                                <input class="qty-input" type="number" value="1" min="1" max="99" inputmode="numeric" aria-label="Quantity" data-pid="<?php echo $id; ?>">
                                <button type="button" class="qty-btn" onclick="tennisproQtyStep(this, 1)" aria-label="Increase quantity">+</button>
                            </div>
                            <button type="button" class="btn" onclick="tennisproAddToCart(<?php echo $id; ?>, this)">Add to cart</button>
                        </div>
                        <button type="button" class="btn btn-secondary" data-product-id="<?php echo $id; ?>" onclick="tennisproOpenProductModal(this)">View details</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
            (function () {
                var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

                window.tennisproQtyStep = function (btn, delta) {
                    try {
                        var wrap = btn && btn.parentElement;
                        if (!wrap) return;
                        var input = wrap.querySelector('input.qty-input');
                        if (!input) return;
                        var v = parseInt(input.value || '1', 10);
                        if (isNaN(v)) v = 1;
                        v = v + (parseInt(delta, 10) || 0);
                        if (v < 1) v = 1;
                        if (v > 99) v = 99;
                        input.value = String(v);
                    } catch (e) {}
                };

                function showToast(msg) {
                    var t = document.getElementById('cart-toast');
                    if (!t) return;
                    t.textContent = msg;
                    t.style.display = 'block';
                    t.style.opacity = '1';
                    clearTimeout(t._timer);
                    t._timer = setTimeout(function(){ t.style.opacity = '0'; setTimeout(function(){ t.style.display = 'none'; }, 300); }, 2000);
                }

                window.tennisproAddToCart = function (pid, btn) {
                    var card = btn.closest('.product-card');
                    var input = card ? card.querySelector('input.qty-input') : null;
                    var qty = input ? parseInt(input.value, 10) || 1 : 1;
                    btn.disabled = true;
                    btn.textContent = 'Adding...';

                    var fd = new FormData();
                    fd.append('action', 'tennispro_cart');
                    fd.append('cart_action', 'add');
                    fd.append('product_id', pid);
                    fd.append('quantity', qty);

                    fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            btn.disabled = false;
                            btn.textContent = 'Add to cart';
                            if (input) input.value = '1';
                            if (res.success) {
                                showToast('Added to cart! (' + res.data.cart_count + ' items total)');
                            } else {
                                showToast('Failed to add.');
                            }
                        })
                        .catch(function(){
                            btn.disabled = false;
                            btn.textContent = 'Add to cart';
                            showToast('Network error.');
                        });
                };

                var products = <?php echo wp_json_encode( array_values( $products ) ); ?>;
                var byId = {};
                for (var i = 0; i < products.length; i++) {
                    var pid = parseInt(products[i].id, 10);
                    if (!isNaN(pid)) byId[pid] = products[i];
                }

                var modal = null, imgEl = null, nameEl = null, priceEl = null, descEl = null, metaEl = null, lastFocus = null;

                function ensureModal() {
                    if (modal) return;
                    var wrap = document.createElement('div');
                    wrap.id = 'tennispro-product-modal';
                    wrap.className = 'product-modal';
                    wrap.setAttribute('aria-hidden', 'true');
                    wrap.setAttribute('role', 'dialog');
                    wrap.setAttribute('aria-modal', 'true');
                    wrap.innerHTML =
                        '<div class="product-modal-backdrop"></div>' +
                        '<div class="product-modal-content" role="document">' +
                            '<button type="button" class="product-modal-close" aria-label="Close">×</button>' +
                            '<h2>Product Details</h2>' +
                            '<div class="product-modal-body">' +
                                '<div class="product-modal-image"><img id="tennispro-modal-img" src="" alt=""></div>' +
                                '<div class="product-modal-info">' +
                                    '<h3 id="tennispro-modal-name"></h3>' +
                                    '<p id="tennispro-modal-price" class="product-modal-price"></p>' +
                                    '<p id="tennispro-modal-desc"></p>' +
                                    '<dl id="tennispro-modal-meta" class="product-modal-meta"></dl>' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    document.body.appendChild(wrap);
                    modal = wrap;
                    imgEl = document.getElementById('tennispro-modal-img');
                    nameEl = document.getElementById('tennispro-modal-name');
                    priceEl = document.getElementById('tennispro-modal-price');
                    descEl = document.getElementById('tennispro-modal-desc');
                    metaEl = document.getElementById('tennispro-modal-meta');
                    var backdrop = modal.querySelector('.product-modal-backdrop');
                    var closeBtn = modal.querySelector('.product-modal-close');
                    if (backdrop) backdrop.addEventListener('click', window.tennisproCloseProductModal);
                    if (closeBtn) closeBtn.addEventListener('click', window.tennisproCloseProductModal);
                }

                function normalizeImagePath(p) {
                    var img = (p.image_path || p.image || '') + '';
                    if (!img) return '';
                    if (img.indexOf('http') === 0 || img.indexOf('//') === 0) return img;
                    if (img.indexOf('images/') === 0) img = 'image/' + img.substring('images/'.length);
                    return <?php echo wp_json_encode( get_stylesheet_directory_uri() . '/' ); ?> + img.replace(/^\/+/, '');
                }

                function setMetaRow(label, value) {
                    if (!value) return;
                    var dt = document.createElement('dt'); dt.textContent = label;
                    var dd = document.createElement('dd'); dd.textContent = value;
                    metaEl.appendChild(dt); metaEl.appendChild(dd);
                }

                window.tennisproOpenProductModal = function (btn) {
                    ensureModal();
                    if (!modal || modal.style.display === 'flex') return;
                    var id = parseInt(btn && btn.getAttribute('data-product-id'), 10);
                    var p = byId[id];
                    if (!p) return;
                    lastFocus = document.activeElement;
                    var img = normalizeImagePath(p);
                    imgEl.src = img || ''; imgEl.alt = (p.name || 'Product') + '';
                    nameEl.textContent = (p.name || '') + '';
                    var price = parseFloat(p.price || 0);
                    priceEl.textContent = '$' + (isNaN(price) ? '0.00' : price.toFixed(2));
                    descEl.textContent = (p.description || '') + ' This package is part of the TennisPro Hub capstone store. All bookings are for demonstration only and will not charge your real card.';
                    metaEl.innerHTML = '';
                    setMetaRow('Category', p.category || '');
                    setMetaRow('Origin', p.origin || '');
                    setMetaRow('Supplier', p.supplier || '');
                    setMetaRow('Stock level', p.stock_level || '');
                    setMetaRow('Discount', p.discount || '');
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    var closeBtn = modal.querySelector('.product-modal-close');
                    if (closeBtn) closeBtn.focus();
                };

                window.tennisproCloseProductModal = function () {
                    if (!modal) return;
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                    if (lastFocus && lastFocus.focus) lastFocus.focus();
                };

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
                        window.tennisproCloseProductModal();
                    }
                });
            })();
        </script>
    <?php endif; ?>

    <?php
    return ob_get_clean();
} );
