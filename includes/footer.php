</main>
<footer class="site-footer">Allana Coffee · Dibuat dengan hangat.</footer>
<script>
(() => {
    const cart = JSON.parse(localStorage.getItem('kafe_cart_v1') || '[]');
    const count = cart.reduce((total, item) => total + (Number(item.qty) || 0), 0);
    const target = document.querySelector('#cart-count');
    if (target) target.textContent = String(count);
})();
</script>
</body>
</html>
