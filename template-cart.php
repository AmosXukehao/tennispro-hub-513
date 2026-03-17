<?php
/**
 * Template Name: Cart / Order (Capstone)
 * AJAX-powered cart: quantity +/- and clear without page reload.
 */
defined( 'ABSPATH' ) || exit;
get_header();

$products = function_exists( 'tennispro_get_products' ) ? tennispro_get_products() : [];
$product_map = [];
foreach ( $products as $p ) {
    $product_map[ (int) ( $p['id'] ?? 0 ) ] = $p;
}

$cart       = $_SESSION['cart'] ?? [];
$total      = 0.0;
$item_count = 0;

$products_url = esc_url( get_permalink( get_page_by_path( 'products' ) ) ?: home_url( '/products/' ) );
$checkout_url = esc_url( get_permalink( get_page_by_path( 'checkout' ) ) ?: home_url( '/checkout/' ) );
?>
<h1>Shopping cart</h1>

<div id="cart-container">
<?php if ( empty( $cart ) ) : ?>
    <p id="cart-empty-msg">Your cart is empty. <a href="<?php echo $products_url; ?>">Browse products</a>.</p>
<?php else : ?>
    <div class="cart-layout">
        <section class="cart-items-panel">
            <h2>Cart Items</h2>
            <table class="table" id="cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Unit price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $cart as $pid => $qty ) : ?>
                        <?php
                        $pid = (int) $pid;
                        $qty = (int) $qty;
                        if ( ! isset( $product_map[ $pid ] ) ) continue;
                        $p        = $product_map[ $pid ];
                        $unit     = (float) ( $p['price'] ?? 0 );
                        $subtotal = $unit * $qty;
                        $total   += $subtotal;
                        $item_count += $qty;
                        ?>
                        <tr data-pid="<?php echo $pid; ?>">
                            <td><?php echo esc_html( $p['name'] ?? '' ); ?></td>
                            <td>$<?php echo number_format( $unit, 2 ); ?></td>
                            <td>
                                <div class="qty-control qty-control-compact">
                                    <button type="button" class="qty-btn" onclick="cartUpdate(<?php echo $pid; ?>, -1)" aria-label="Decrease">−</button>
                                    <span class="qty-value"><?php echo $qty; ?></span>
                                    <button type="button" class="qty-btn" onclick="cartUpdate(<?php echo $pid; ?>, 1)" aria-label="Increase">+</button>
                                </div>
                            </td>
                            <td class="row-subtotal">$<?php echo number_format( $subtotal, 2 ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" class="btn btn-secondary" onclick="cartClear()" style="margin-top:12px;">Clear cart</button>
        </section>

        <aside class="cart-summary-panel">
            <h2>Order Summary</h2>
            <?php $shipping = $total > 0 ? 10.0 : 0.0; $grand = $total + $shipping; ?>
            <p><span id="summary-count"><?php echo $item_count; ?></span> item(s) in cart.</p>
            <ul class="cart-summary-list">
                <li><span>Subtotal</span><span id="summary-subtotal">$<?php echo number_format( $total, 2 ); ?></span></li>
                <li><span>Shipping</span><span id="summary-shipping">$<?php echo number_format( $shipping, 2 ); ?></span></li>
                <li class="cart-summary-total"><span>Total</span><span id="summary-total">$<?php echo number_format( $grand, 2 ); ?></span></li>
            </ul>
            <a href="<?php echo $checkout_url; ?>" class="btn" style="width:100%;margin-top:12px;">Proceed to checkout</a>
            <a href="<?php echo $products_url; ?>" class="btn btn-secondary" style="width:100%;margin-top:8px;">Continue shopping</a>
        </aside>
    </div>
<?php endif; ?>
</div>

<script>
(function(){
    var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    var productMap = <?php echo wp_json_encode( $product_map ); ?>;
    var productsUrl = <?php echo wp_json_encode( $products_url ); ?>;
    var checkoutUrl = <?php echo wp_json_encode( $checkout_url ); ?>;

    function fmt(n){ return '$' + parseFloat(n).toFixed(2); }

    function cartAjax(cartAction, pid, delta, qty) {
        var fd = new FormData();
        fd.append('action', 'tennispro_cart');
        fd.append('cart_action', cartAction);
        if (pid) fd.append('product_id', pid);
        if (delta !== undefined) fd.append('delta', delta);
        if (qty !== undefined) fd.append('quantity', qty);

        return fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); });
    }

    function renderCart(data) {
        var container = document.getElementById('cart-container');
        if (!data.items || data.items.length === 0) {
            container.innerHTML = '<p>Your cart is empty. <a href="' + productsUrl + '">Browse products</a>.</p>';
            return;
        }

        var rows = '';
        data.items.forEach(function(it){
            rows += '<tr data-pid="' + it.id + '">'
                + '<td>' + it.name + '</td>'
                + '<td>' + fmt(it.price) + '</td>'
                + '<td><div class="qty-control qty-control-compact">'
                + '<button type="button" class="qty-btn" onclick="cartUpdate(' + it.id + ',-1)">−</button>'
                + '<span class="qty-value">' + it.qty + '</span>'
                + '<button type="button" class="qty-btn" onclick="cartUpdate(' + it.id + ',1)">+</button>'
                + '</div></td>'
                + '<td>' + fmt(it.subtotal) + '</td>'
                + '</tr>';
        });

        container.innerHTML =
            '<div class="cart-layout">'
            + '<section class="cart-items-panel"><h2>Cart Items</h2>'
            + '<table class="table"><thead><tr><th>Product</th><th>Unit price</th><th>Quantity</th><th>Subtotal</th></tr></thead>'
            + '<tbody>' + rows + '</tbody></table>'
            + '<button type="button" class="btn btn-secondary" onclick="cartClear()" style="margin-top:12px;">Clear cart</button>'
            + '</section>'
            + '<aside class="cart-summary-panel"><h2>Order Summary</h2>'
            + '<p>' + data.item_count + ' item(s) in cart.</p>'
            + '<ul class="cart-summary-list">'
            + '<li><span>Subtotal</span><span>' + fmt(data.subtotal) + '</span></li>'
            + '<li><span>Shipping</span><span>' + fmt(data.shipping) + '</span></li>'
            + '<li class="cart-summary-total"><span>Total</span><span>' + fmt(data.total) + '</span></li>'
            + '</ul>'
            + '<a href="' + checkoutUrl + '" class="btn" style="width:100%;margin-top:12px;">Proceed to checkout</a>'
            + '<a href="' + productsUrl + '" class="btn btn-secondary" style="width:100%;margin-top:8px;">Continue shopping</a>'
            + '</aside></div>';
    }

    window.cartUpdate = function(pid, delta) {
        cartAjax('update', pid, delta).then(function(res){
            if (res.success) renderCart(res.data);
        });
    };

    window.cartClear = function() {
        cartAjax('clear').then(function(res){
            if (res.success) renderCart(res.data);
        });
    };
})();
</script>

<?php get_footer(); ?>
